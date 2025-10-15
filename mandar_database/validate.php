<?php
header('Content-Type: application/json');

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "opencart_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

$model = isset($_POST['model']) ? trim($_POST['model']) : '';

if ($model === '') {
    echo json_encode(["status" => "error", "message" => "Model field is required."]);
    exit;
}

$model_esc = $conn->real_escape_string($model);
$res = $conn->query("SELECT p.product_id, p.model, d.name 
                     FROM oc_product p
                     LEFT JOIN oc_product_description d ON p.product_id = d.product_id
                     WHERE p.model = '$model_esc'");

if ($res && $res->num_rows > 0) {
    $conflicts = [];
    while ($row = $res->fetch_assoc()) {
        $conflicts[] = "ID: {$row['product_id']} | Name: {$row['name']} | Model: {$row['model']}";
    }
    echo json_encode(["status" => "exists", "message" => implode("\n", $conflicts)]);
} else {
    echo json_encode(["status" => "ok", "message" => "Model is unique."]);
}
