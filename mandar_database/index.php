<?php
session_start();

// DB connection
$mysqli = new mysqli(
    "localhost",             // DB_HOSTNAME
    "ebinarwe_ocart87",      // DB_USERNAME
    "5xpAS1G3--",            // DB_PASSWORD
    "ebinarwe_ocart87"       // DB_DATABASE
);

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Clear previous logs on refresh
if (isset($_SESSION['import_log'])) {
    unset($_SESSION['import_log']);
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $filename = $_FILES['csv_file']['name'];

    if (($handle = fopen($file, "r")) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ";")) !== false) {
            if ($row === 0) { 
                $row++; 
                continue; // skip header row
            }

            $name   = trim($data[0] ?? '');
            $model  = trim($data[1] ?? '');
            $sku    = trim($data[2] ?? '');
            $price  = trim($data[3] ?? '');
            $tax    = trim($data[4] ?? '');
            $seo    = trim($data[5] ?? '');

            $errors = [];

            // Basic validations
            if (empty($model)) $errors[] = "Model is required.";
            if (!is_numeric($price)) $errors[] = "Price must be numeric.";

            // Check for duplicate model
            $check = $mysqli->query("SELECT product_id FROM oct8_product WHERE model = '" . $mysqli->real_escape_string($model) . "'");
            if ($check && $check->num_rows > 0) {
                $errors[] = "Duplicate model: $model.";
            }

            // Tax mapping
            $tax_class_id = 0;
            if ($tax === "0") $tax_class_id = 11; // IVA NORMAL - 23%
            elseif ($tax === "1") $tax_class_id = 12; // IVA REDUZIDO - 13%
            elseif ($tax === "2") $tax_class_id = 13; // IVA INTERMÉDIO - 6%
            elseif ($tax === "3") $tax_class_id = 0;  // IVA ISENTO

            if (empty($errors)) {
                // Insert into oct8_product
                $mysqli->query("INSERT INTO oct8_product SET 
                    model = '" . $mysqli->real_escape_string($model) . "', 
                    sku = '" . $mysqli->real_escape_string($sku) . "',
                    price = '" . (float)$price . "',
                    tax_class_id = '" . (int)$tax_class_id . "',
                    date_added = NOW(),
                    status = 1
                ");

                $product_id = $mysqli->insert_id;

                // Insert into oct8_product_description
                $mysqli->query("INSERT INTO oct8_product_description SET 
                    product_id = " . (int)$product_id . ",
                    language_id = 1,
                    name = '" . $mysqli->real_escape_string($name) . "',
                    meta_title = '" . $mysqli->real_escape_string($name) . "'
                ");

                // Link product to store
                $mysqli->query("INSERT INTO oct8_product_to_store SET 
                    product_id = " . (int)$product_id . ",
                    store_id = 0
                ");

                // Insert SEO keyword
                if (!empty($seo)) {
                    $mysqli->query("INSERT INTO oct8_seo_url SET 
                        store_id = 0,
                        language_id = 1,
                        `key` = 'product_id',
                        `value` = " . (int)$product_id . ",
                        keyword = '" . $mysqli->real_escape_string($seo) . "',
                        sort_order = 0
                    ");
                }

                // Log success
                $mysqli->query("INSERT INTO oct8_logs SET 
                    success = 1,
                    log = 'Inserted product $name (ID $product_id)',
                    excel_file_name = '" . $mysqli->real_escape_string($filename) . "',
                    data = NOW()
                ");

                $_SESSION['import_log'][] = "✅ Inserted product: $name (ID $product_id)";
            } else {
                // Log failure
                $error_msg = implode("; ", $errors);
                $mysqli->query("INSERT INTO oct8_logs SET 
                    success = 0,
                    error = '" . $mysqli->real_escape_string($error_msg) . "',
                    log = 'Failed to insert product with model $model',
                    excel_file_name = '" . $mysqli->real_escape_string($filename) . "',
                    data = NOW()
                ");

                $_SESSION['import_log'][] = "❌ Skipped ($model): $error_msg";
            }

            $row++;
        }
        fclose($handle);
    }
    header("Location: index.php?uploaded=1");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Products from CSV</title>
</head>
<body>
    <h1>Import Products from CSV</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload</button>
    </form>

    <?php if (isset($_SESSION['import_log'])): ?>
        <h2>Import Results</h2>
        <ul>
            <?php foreach ($_SESSION['import_log'] as $log): ?>
                <li><?php echo htmlspecialchars($log); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php unset($_SESSION['import_log']); ?>
    <?php endif; ?>
    <a href="reports.php" target="_blank">View Detailed Reports</a> | 
    <a href="current_reports.php" target="_blank">View Current Reports</a>
</body>
</html>
