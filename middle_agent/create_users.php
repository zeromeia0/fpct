<?php
// Insere na tabela vini_users: username, salt (16 bytes), passhash = SHA256(salt + password)
if ($argc < 3) {
    fwrite(STDERR, "Usage: php create_user.php <username> <password>\n");
    exit(2);
}
$username = $argv[1];
$password = $argv[2];

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'vini_user';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'vini_agent';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Failed to connect to MySQL: (".$mysqli->connect_errno.") ".$mysqli->connect_error."\n");
    exit(3);
}

$salt = random_bytes(16);
$sha = hash('sha256', $salt . $password, true);

$salt_hex = bin2hex($salt);
$sha_hex = bin2hex($sha);

$stmt = $mysqli->prepare('INSERT INTO vini_users (username, salt, passhash) VALUES (?, UNHEX(?), UNHEX(?))');
if (!$stmt) {
    fwrite(STDERR, "Prepare failed: (".$mysqli->errno.") ".$mysqli->error."\n");
    exit(4);
}
$stmt->bind_param('sss', $username, $salt_hex, $sha_hex);
if (!$stmt->execute()) {
    fwrite(STDERR, "Execute failed: (".$stmt->errno.") ".$stmt->error."\n");
    exit(5);
}

fwrite(STDOUT, "User created: $username\n");
$stmt->close();
$mysqli->close();
