<?php
// check_mac.php
// Usage: POST username, password, mac
// Response: JSON { "status": "WHITELISTED" | "NOT_WHITELISTED" | "AUTH_FAILED" }

header('Content-Type: application/json');

$dbHost = "sql300.infinityfree.com";
$dbUser = "if0_40173882";
$dbPass = "Vinicius130006";
$dbName = "if0_40173882_fpct_db";

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$mac = $_POST['mac'] ?? '';

if (!$username || !$password || !$mac) {
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

// Fetch salt + hash
$stmt = $mysqli->prepare("SELECT salt, passhash FROM vini_users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(["status" => "AUTH_FAILED"]);
    exit;
}

$stmt->bind_result($salt, $passhash);
$stmt->fetch();

// Compute hash from salt + password
$computed = hash('sha256', $salt . $password, true);

if (!hash_equals($passhash, $computed)) {
    echo json_encode(["status" => "AUTH_FAILED"]);
    exit;
}

$stmt->close();

// Check whitelist
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM vini_whitelist WHERE mac_address = ?");
$stmt->bind_param('s', $mac);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();

if ($count > 0) {
    echo json_encode(["status" => "WHITELISTED"]);
} else {
    echo json_encode(["status" => "NOT_WHITELISTED"]);
}

$stmt->close();
$mysqli->close();
?>
