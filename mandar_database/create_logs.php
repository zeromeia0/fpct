<?php
// create_logs.php — creates oct8_logs if missing
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "ebinarwe_ocart87";
$pass = "5xpAS1G3--";
$db   = "ebinarwe_ocart87";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

$table = "oct8_logs";

// Check if table exists
$res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
if ($res && $res->num_rows > 0) {
    echo "Table <strong>" . htmlspecialchars($table) . "</strong> already exists.";
    exit;
}

// Create table
$sql = "
CREATE TABLE `" . $table . "` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `log` text,
  `error` text,
  `excel_file_name` varchar(255) DEFAULT NULL,
  `data` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($mysqli->query($sql)) {
    echo "Table <strong>" . htmlspecialchars($table) . "</strong> created successfully.";
} else {
    echo "Error creating table: " . $mysqli->error;
}

$mysqli->close();
?>
