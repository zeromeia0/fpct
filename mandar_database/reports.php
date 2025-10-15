<?php
session_start();

// DB connection
$mysqli = new mysqli("localhost", "ebinarwe_ocart87", "5xpAS1G3--", "ebinarwe_ocart87");
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

$date_filter = $_GET['date'] ?? '';
$sql = "SELECT * FROM oct8_logs";
if ($date_filter) {
    $sql .= " WHERE DATE(data) = '" . $mysqli->real_escape_string($date_filter) . "'";
}
$sql .= " ORDER BY data DESC";
$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Reports</title>
</head>
<body>
<h1>Detailed Import Reports</h1>
<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <div>
            <strong><?php echo date("Y-m-d H:i:s", strtotime($row['data'])); ?></strong>
            → <?php echo htmlspecialchars($row['excel_file_name']); ?><br>
            <?php if ($row['success']): ?>
                <span style="color:green;">Success</span>
            <?php else: ?>
                <span style="color:red;">Failure: <?php echo htmlspecialchars($row['error']); ?></span>
            <?php endif; ?><br>
            <?php echo htmlspecialchars($row['log']); ?>
        </div>
        <hr>
    <?php endwhile; ?>
<?php else: ?>
    <p>No logs found.</p>
<?php endif; ?>
</body>
</html>
