<?php
$mysqli = new mysqli("localhost", "root", "", "opencart_db");
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Filter by date (Y-m-d)
$date_filter = $_GET['date'] ?? '';
$sql = "SELECT excel_file_name, DATE(data) as log_date,
               SUM(success = 1) as total_success,
               SUM(success = 0) as total_failures,
               COUNT(*) as total_rows,
               MAX(data) as last_action
        FROM oc_logs";

if ($date_filter) {
    $sql .= " WHERE DATE(data) = '" . $mysqli->real_escape_string($date_filter) . "'";
}

$sql .= " GROUP BY excel_file_name, DATE(data) 
          ORDER BY last_action DESC";

$result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Current Reports</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; }
        .success { color: green; font-weight: bold; }
        .failure { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Current Reports (Summary)</h1>
    <form method="get" action="current_reports.php">
        <label>Filter by date: 
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
        </label>
        <button type="submit">Filter</button>
    </form>
    <hr>
    <?php if ($result && $result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Date</th>
                <th>Excel File</th>
                <th>Total Rows</th>
                <th>Success</th>
                <th>Failures</th>
                <th>Last Action</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['excel_file_name']); ?></td>
                    <td><?php echo (int)$row['total_rows']; ?></td>
                    <td class="success"><?php echo (int)$row['total_success']; ?></td>
                    <td class="failure"><?php echo (int)$row['total_failures']; ?></td>
                    <td><?php echo date("Y-m-d H:i:s", strtotime($row['last_action'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No logs found for the selected filter.</p>
    <?php endif; ?>
</body>
</html>
