<?php
$mysqli = new mysqli("localhost", "root", "", "opencart_db");
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Filter by date (Y-m-d)

$date_filter = $_GET['date'] ?? '';
$sql = "SELECT * FROM oc_logs";
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
    <style>
        body { font-family: Arial, sans-serif; }
        .success { color: green; font-weight: bold; }
        .failure { color: red; font-weight: bold; }
        hr { margin: 12px 0; }
    </style>
</head>
<body>
    <h1>Import Reports</h1>
    <form method="get" action="reports.php">
        <label>Filter by date: 
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
        </label>
        <button type="submit">Filter</button>
    </form>
    <hr>
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div>
                <strong><?php echo date("Y-m-d H:i:s", strtotime($row['data'])); ?></strong> 
                → <?php echo htmlspecialchars($row['excel_file_name']); ?><br>
                <?php if ($row['success']): ?>
                    <span class="success">Success</span>
                <?php else: ?>
                    <span class="failure">Failure (<?php echo htmlspecialchars($row['error']); ?>)</span>
                <?php endif; ?>
                <br>
                <?php echo htmlspecialchars($row['log']); ?>
            </div>
            <hr>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No logs found for the selected filter.</p>
    <?php endif; ?>
</body>
</html>
