<?php
session_start();
require_once 'db.php';

$conn->set_charset("utf8mb4");

$username = strtolower($_SESSION['username'] ?? '');
if (!$username) {
    die("Not logged in.");
}

function sanitizeName($name) {
    $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
    return trim(preg_replace('/_+/', '_', $name), '_');
}

function processCsvFile($tmpName, $originalName, $finalTableName, $conn) {
    if (!is_uploaded_file($tmpName)) return false;
    if (filesize($tmpName) > 2 * 1024 * 1024) return false;

    $mime = mime_content_type($tmpName);
    if (!in_array($mime, ['text/plain', 'text/csv', 'application/vnd.ms-excel'])) return false;

    // Check if table already exists
    $exists = $conn->query("SHOW TABLES LIKE '$finalTableName'");
    if ($exists && $exists->num_rows > 0) {
        file_put_contents('upload_errors.log', "Table '$finalTableName' already exists. Skipped.\n", FILE_APPEND);
        return false;
    }

    $handle = fopen($tmpName, 'r');
    if (!$handle) return false;

    // Try comma or semicolon separator
    $headers = fgetcsv($handle);
    if (!$headers || count($headers) < 2) {
        rewind($handle);
        $headers = fgetcsv($handle, 1000, ';');
    }
    if (!$headers || count($headers) < 2) {
        file_put_contents('upload_errors.log', "HEADER ERROR: $originalName\n", FILE_APPEND);
        fclose($handle);
        return false;
    }

    // Detect 'Czech' column
    $czechIndex = array_search('Czech', array_map('trim', array_map('ucfirst', $headers)));
    if ($czechIndex === false) {
        file_put_contents('upload_errors.log', "NO 'Czech' column in $originalName\n", FILE_APPEND);
        fclose($handle);
        return false;
    }

    // Get second language column
    $secondIndex = -1;
    foreach ($headers as $i => $h) {
        if ($i !== $czechIndex) {
            $secondIndex = $i;
            break;
        }
    }
    if ($secondIndex === -1) {
        file_put_contents('upload_errors.log', "NO second language in $originalName\n", FILE_APPEND);
        fclose($handle);
        return false;
    }

    $lang1 = sanitizeName($headers[$czechIndex]);
    $lang2 = sanitizeName($headers[$secondIndex]);

    // Create table
    $create_sql = "CREATE TABLE `$finalTableName` (
        `$lang1` VARCHAR(255) NOT NULL,
        `$lang2` VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";

    if (!$conn->query($create_sql)) {
        file_put_contents('upload_errors.log', "CREATE ERROR: $finalTableName - " . $conn->error . "\n", FILE_APPEND);
        fclose($handle);
        return false;
    }

    // Insert rows
    $rowNum = 1;
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        $rowNum++;
        if (count($data) < 2) $data = str_getcsv(implode('', $data), ';');
        if (!isset($data[$czechIndex]) || !isset($data[$secondIndex])) continue;

        $val1 = trim($conn->real_escape_string($data[$czechIndex]));
        $val2 = trim($conn->real_escape_string($data[$secondIndex]));

        if ($val1 === '' || $val2 === '') continue;

        $sql = "INSERT INTO `$finalTableName` (`$lang1`, `$lang2`) VALUES ('$val1', '$val2')";
        if (!$conn->query($sql)) {
            file_put_contents('upload_errors.log', "INSERT ERROR ($finalTableName, row $rowNum): $val1 | $val2\n", FILE_APPEND);
        } else {
            file_put_contents('upload_success.log', "INSERTED ($finalTableName, row $rowNum): $val1 | $val2\n", FILE_APPEND);
        }
    }

    fclose($handle);
    unlink($tmpName);
    file_put_contents('upload_success.log', "✔ Uploaded: $originalName → $finalTableName\n", FILE_APPEND);
    return true;
}

// 🔁 Handle single file upload (from upload.php)
if (isset($_FILES['csv_file']) && isset($_POST['table_name'])) {
    $tableNameInput = sanitizeName($_POST['table_name']);
    if (!$tableNameInput) {
        die("Invalid table name.");
    }

    $finalTableName = "{$username}_{$tableNameInput}";
    $tmpName = $_FILES['csv_file']['tmp_name'];
    $originalName = $_FILES['csv_file']['name'];

    $_SESSION['uploaded_filename'] = $originalName;

    processCsvFile($tmpName, $originalName, $finalTableName, $conn);
    header("Location: upload.php");
    exit;
}

// 🔁 Handle bulk upload (from main.php etc.)

if (isset($_FILES['csv_files'])) {
    $_SESSION['uploaded_tables'] = [];

    foreach ($_FILES['csv_files']['tmp_name'] as $i => $tmpName) {
        $originalName = $_FILES['csv_files']['name'][$i];
        $filenameOnly = pathinfo($originalName, PATHINFO_FILENAME);
        $table = sanitizeName($filenameOnly);
        $finalTableName = "{$username}_{$table}";

        if (processCsvFile($tmpName, $originalName, $finalTableName, $conn)) {
            $_SESSION['uploaded_tables'][] = $finalTableName;
        }
    }

    header("Location: upload.php");
    exit;
}


$conn->close();
?>
