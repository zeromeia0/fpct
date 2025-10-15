#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <termios.h>
#include <sys/ioctl.h>
#include <sys/socket.h>
#include <net/if.h>
#include <mysql/mysql.h>
#include <openssl/evp.h>

#define MACSTR_LEN 18

void	noecho_on(void)
{
	struct termios	t;

	tcgetattr(STDIN_FILENO, &t);
	t.c_lflag &= ~ECHO;
	tcsetattr(STDIN_FILENO, TCSANOW, &t);
}
void	noecho_off(void)
{
	struct termios	t;

	tcgetattr(STDIN_FILENO, &t);
	t.c_lflag |= ECHO;
	tcsetattr(STDIN_FILENO, TCSANOW, &t);
}

int	format_mac(unsigned char *mac, char *out)
{
	return (sprintf(out, "%02X:%02X:%02X:%02X:%02X:%02X", mac[0], mac[1],
			mac[2], mac[3], mac[4], mac[5]));
}

int	get_mac_by_ifname(const char *ifname, char *out)
{
	int				fd;
	struct ifreq	ifr;
	unsigned char	*mac;

	fd = socket(AF_INET, SOCK_DGRAM, 0);
	if (fd < 0)
		return (-1);
	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, ifname, IFNAMSIZ - 1);
	if (ioctl(fd, SIOCGIFHWADDR, &ifr) == -1)
	{
		close(fd);
		return (-1);
	}
	mac = (unsigned char *)ifr.ifr_hwaddr.sa_data;
	format_mac(mac, out);
	close(fd);
	return (0);
}

int	get_first_mac(char *out)
{
	FILE	*f;
	char	ifname[IFNAMSIZ];

	f = popen("ls /sys/class/net", "r");
	if (!f)
		return (-1);
	while (fscanf(f, "%s", ifname) == 1)
	{
		if (strcmp(ifname, "lo") == 0)
			continue ;
		if (get_mac_by_ifname(ifname, out) == 0)
		{
			pclose(f);
			return (0);
		}
	}
	pclose(f);
	return (-1);
}

// hex decode helper — expects hex length = outlen*2
int	hex_to_bin(const char *hex, unsigned char *out, size_t outlen)
{
	size_t	hexlen;
		unsigned int v;

	hexlen = strlen(hex);
	if (hexlen != outlen * 2)
		return (-1);
	for (size_t i = 0; i < outlen; ++i)
	{
		if (sscanf(hex + 2 * i, "%2x", &v) != 1)
			return (-1);
		out[i] = (unsigned char)v;
	}
	return (0);
}

int	sha256_bin(const unsigned char *data, size_t datalen, unsigned char *out32)
{
	EVP_MD_CTX		*mdctx;
	unsigned int	olen;

	mdctx = EVP_MD_CTX_new();
	if (!mdctx)
		return (-1);
	if (1 != EVP_DigestInit_ex(mdctx, EVP_sha256(), NULL))
	{
		EVP_MD_CTX_free(mdctx);
		return (-1);
	}
	if (1 != EVP_DigestUpdate(mdctx, data, datalen))
	{
		EVP_MD_CTX_free(mdctx);
		return (-1);
	}
	olen = 0;
	if (1 != EVP_DigestFinal_ex(mdctx, out32, &olen))
	{
		EVP_MD_CTX_free(mdctx);
		return (-1);
	}
	EVP_MD_CTX_free(mdctx);
	return ((olen == 32) ? 0 : -1);
}

int	main(int argc, char **argv)
{
	int					auth_ok;
	char				username[128];
	char				password[256];
	char				salt_hex[65] = {0};
	char				sha_hex[129] = {0};
	char				mac[MACSTR_LEN] = {0};
	const char			*dbHost = getenv("DB_HOST") ? getenv("DB_HOST") : "127.0.0.1";
	const char			*dbUser = getenv("DB_USER") ? getenv("DB_USER") : "vini_user";
	const char			*dbPass = getenv("DB_PASS") ? getenv("DB_PASS") : "";
	const char			*dbName = getenv("DB_NAME") ? getenv("DB_NAME") : "vini_agent";
	const char			*wqry = "SELECT COUNT(*) FROM vini_whitelist WHERE mac_address = ?";
	const char			*qry = "SELECT HEX(salt), HEX(passhash) FROM vini_users WHERE username = ? LIMIT 1";
	MYSQL				*conn;
	MYSQL_STMT			*wstmt;
	MYSQL_STMT			*stmt;
	MYSQL_BIND			bind[1];
	MYSQL_BIND			wbind[1];
	MYSQL_BIND			rbind[1];
	MYSQL_BIND			result_bind[2];
	unsigned char		salt_bin[16];
	unsigned char		stored_sha[32];
	unsigned char		*concat;
	unsigned char		computed[32];
	unsigned long		maclen;
	unsigned long		salt_len = 0, sha_len;
	unsigned long		username_len;
	unsigned long long	count;

	printf("Username: ");
	if (!fgets(username, sizeof(username), stdin))
		return (1);
	username[strcspn(username, "\n")] = '\0';
	printf("Password: ");
	fflush(stdout);
	noecho_on();
	if (!fgets(password, sizeof(password), stdin))
	{
		noecho_off();
		return 1;
	}
	noecho_off();
	printf("\n");
	password[strcspn(password, "\n")] = '\0';
	conn = mysql_init(NULL);
	if (!conn)
	{
		fprintf(stderr, "mysql_init failed\n");
		return 10;
	}
	if (!mysql_real_connect(conn, dbHost, dbUser, dbPass, dbName, 0, NULL, 0))
	{
		fprintf(stderr, "MySQL connect error: %s\n", mysql_error(conn));
		mysql_close(conn);
		return 11;
	}
	stmt = mysql_stmt_init(conn);
	if (!stmt)
	{
		fprintf(stderr, "stmt init failed\n");
		mysql_close(conn);
		return 12;
	}
	if (mysql_stmt_prepare(stmt, qry, (unsigned long)strlen(qry)) != 0)
	{
		fprintf(stderr, "Prepare failed: %s\n", mysql_stmt_error(stmt));
		mysql_stmt_close(stmt);
		mysql_close(conn);
		return 13;
	}
	memset(bind, 0, sizeof(bind));
	username_len = (unsigned long)strlen(username);
	bind[0].buffer_type = MYSQL_TYPE_STRING;
	bind[0].buffer = (char *)username;
	bind[0].buffer_length = username_len;
	bind[0].length = &username_len;
	if (mysql_stmt_bind_param(stmt, bind) != 0)
	{
		fprintf(stderr, "Bind param failed\n");
		mysql_stmt_close(stmt);
		mysql_close(conn);
		return 14;
	}
	if (mysql_stmt_execute(stmt) != 0)
	{
		fprintf(stderr, "Execute failed: %s\n", mysql_stmt_error(stmt));
		mysql_stmt_close(stmt);
		mysql_close(conn);
		return 15;
	}

	salt_len = 0, sha_len = 0;
	memset(result_bind, 0, sizeof(result_bind));
	result_bind[0].buffer_type = MYSQL_TYPE_STRING;
	result_bind[0].buffer = salt_hex;
	result_bind[0].buffer_length = sizeof(salt_hex) - 1;
	result_bind[0].length = &salt_len;
	result_bind[1].buffer_type = MYSQL_TYPE_STRING;
	result_bind[1].buffer = sha_hex;
	result_bind[1].buffer_length = sizeof(sha_hex) - 1;
	result_bind[1].length = &sha_len;
	if (mysql_stmt_bind_result(stmt, result_bind) != 0)
	{
		fprintf(stderr, "Bind result failed\n");
		mysql_stmt_close(stmt);
		mysql_close(conn);
		return 16;
	}
	auth_ok = 0;
	if (mysql_stmt_fetch(stmt) == 0)
	{
		salt_hex[salt_len] = '\0';
		sha_hex[sha_len] = '\0';
		if (hex_to_bin(salt_hex, salt_bin, 16) != 0)
		{
			fprintf(stderr, "Salt decode failed\n");
		}
		else if (hex_to_bin(sha_hex, stored_sha, 32) != 0)
		{
			fprintf(stderr, "Hash decode failed\n");
		}
		else
		{
			concat = malloc(16 + strlen(password));
			if (!concat)
			{
				fprintf(stderr, "Memory error\n");
			}
			else
			{
				memcpy(concat, salt_bin, 16);
				memcpy(concat + 16, password, strlen(password));
				if (sha256_bin(concat, 16 + strlen(password), computed) == 0)
				{
					if (memcmp(computed, stored_sha, 32) == 0)
						auth_ok = 1;
				}
				free(concat);
			}
		}
	}
	mysql_stmt_close(stmt);
	if (!auth_ok)
	{
		fprintf(stderr, "Authentication failed\n");
		mysql_close(conn);
		return 2;
	}
	// Authenticated: obter MAC
	if (argc >= 2)
	{
		if (get_mac_by_ifname(argv[1], mac) != 0)
		{
			fprintf(stderr, "Failed to get MAC for interface '%s'\n", argv[1]);
			mysql_close(conn);
			return 20;
		}
	}
	else
	{
		if (get_first_mac(mac) != 0)
		{
			fprintf(stderr, "No usable interface found\n");
			mysql_close(conn);
			return 21;
		}
	}
	// Verificar whitelist
	wstmt = mysql_stmt_init(conn);
	if (!wstmt)
	{
		fprintf(stderr, "stmt init failed\n");
		mysql_close(conn);
		return 22;
	}
	if (mysql_stmt_prepare(wstmt, wqry, (unsigned long)strlen(wqry)) != 0)
	{
		fprintf(stderr, "Prepare failed: %s\n", mysql_stmt_error(wstmt));
		mysql_stmt_close(wstmt);
		mysql_close(conn);
		return 23;
	}
	memset(wbind, 0, sizeof(wbind));
	maclen = (unsigned long)strlen(mac);
	wbind[0].buffer_type = MYSQL_TYPE_STRING;
	wbind[0].buffer = mac;
	wbind[0].buffer_length = maclen;
	wbind[0].length = &maclen;
	if (mysql_stmt_bind_param(wstmt, wbind) != 0)
	{
		fprintf(stderr, "Bind param failed\n");
		mysql_stmt_close(wstmt);
		mysql_close(conn);
		return 24;
	}
	if (mysql_stmt_execute(wstmt) != 0)
	{
		fprintf(stderr, "Execute failed: %s\n", mysql_stmt_error(wstmt));
		mysql_stmt_close(wstmt);
		mysql_close(conn);
		return 25;
	}
	count = 0;
	memset(rbind, 0, sizeof(rbind));
	rbind[0].buffer_type = MYSQL_TYPE_LONGLONG;
	rbind[0].buffer = &count;
	rbind[0].is_unsigned = 1;
	if (mysql_stmt_bind_result(wstmt, rbind) != 0)
	{
		fprintf(stderr, "Bind result failed\n");
		mysql_stmt_close(wstmt);
		mysql_close(conn);
		return 26;
	}
	if (mysql_stmt_fetch(wstmt) == 0)
	{
		if (count > 0)
		{
			printf("WHITELISTED\n");
			mysql_stmt_close(wstmt);
			mysql_close(conn);
			return 0;
		}
		else
		{
			printf("NOT_WHITELISTED\n");
			mysql_stmt_close(wstmt);
			mysql_close(conn);
			return 1;
		}
	}
	mysql_stmt_close(wstmt);
	mysql_close(conn);
	fprintf(stderr, "Unexpected failure\n");
	return 30;
}
