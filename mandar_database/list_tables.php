<?php
$mysqli = new mysqli("localhost", "ebinarwe_ocart87", "5xpAS1G3--", "ebinarwe_ocart87");
if ($mysqli->connect_error) die("DB connection failed: ".$mysqli->connect_error);

$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo $row[0] . "<br>";
}
?>
