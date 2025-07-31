<?php
session_start();
require_once 'db.php';

$conn->set_charset("utf8mb4");

// $username = $_SESSION['username'] ?? '';
$username = strtolower($_SESSION['username'] ?? '');

if (!$username) {
    die("Not logged in.");
}

$folder = $_POST['folder'] ?? '';
if (!$folder || !isset($_FILES['csv_files'])) {
    die("Missing folder or files.");
}

foreach ($_FILES['csv_files']['tmp_name'] as $i => $tmpName) {
    $originalName = $_FILES['csv_files']['name'][$i];
    $filenameOnly = pathinfo($originalName, PATHINFO_FILENAME);

    // Determine table name
    if (stripos($filenameOnly, $username . "_") === 0) {
        $table = $filenameOnly;
    } else {
        $table = "{$username}_{$folder}_{$filenameOnly}";
    }
    $table = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $table));

    // Check if table already exists
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        file_put_contents('upload_errors.log', "Table '$table' already exists. Skipped.\n", FILE_APPEND);
        continue;
    }

    // Validate uploaded file
    if (!is_uploaded_file($tmpName)) continue;
    if ($_FILES['csv_files']['size'][$i] > 2 * 1024 * 1024) continue;
    $mime = mime_content_type($tmpName);
    if (!in_array($mime, ['text/plain', 'text/csv', 'application/vnd.ms-excel'])) continue;

    // Open file
    $handle = fopen($tmpName, 'r');
    if (!$handle) continue;

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers || count($headers) < 2) {
        rewind($handle);
        $headers = fgetcsv($handle, 1000, ';');
    }
    if (!$headers || count($headers) < 2) {
        file_put_contents('upload_errors.log', "HEADER ERROR: $originalName\n", FILE_APPEND);
        fclose($handle);
        continue;
    }

    // Detect 'Czech' column (case-insensitive)
    $czechIndex = -1;
    foreach ($headers as $index => $header) {
        if (strcasecmp(trim($header), 'Czech') === 0) {
            $czechIndex = $index;
            break;
        }
    }
    if ($czechIndex === -1) {
        file_put_contents('upload_errors.log', "NO 'Czech' column in $originalName\n", FILE_APPEND);
        fclose($handle);
        continue;
    }

    // Find second column
    $secondIndex = -1;
    foreach ($headers as $index => $header) {
        if ($index !== $czechIndex) {
            $secondIndex = $index;
            break;
        }
    }
    if ($secondIndex === -1) {
        file_put_contents('upload_errors.log', "NO second language in $originalName\n", FILE_APPEND);
        fclose($handle);
        continue;
    }

    // Sanitize column names
    $lang1 = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($headers[$czechIndex]));
    $lang2 = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($headers[$secondIndex]));

    // Create table
    $create_sql = "CREATE TABLE `$table` (
        `$lang1` VARCHAR(255) NOT NULL,
        `$lang2` VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";

    if (!$conn->query($create_sql)) {
        file_put_contents('upload_errors.log', "CREATE ERROR: $table - " . $conn->error . "\n", FILE_APPEND);
        fclose($handle);
        continue;
    }

    // Insert data
    $rowNum = 1;
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        $rowNum++;

        if (count($data) < 2) {
            $data = str_getcsv(implode('', $data), ';');
        }

        if (!isset($data[$czechIndex]) || !isset($data[$secondIndex])) continue;

        $val1 = trim($conn->real_escape_string($data[$czechIndex]));
        $val2 = trim($conn->real_escape_string($data[$secondIndex]));

        if ($val1 === '' || $val2 === '') continue;

        $sql = "INSERT INTO `$table` (`$lang1`, `$lang2`) VALUES ('$val1', '$val2')";
        if (!$conn->query($sql)) {
            file_put_contents('upload_errors.log', "INSERT ERROR ($table, row $rowNum): $val1 | $val2\n", FILE_APPEND);
        } else {
            file_put_contents('upload_success.log', "INSERTED ($table, row $rowNum): $val1 | $val2\n", FILE_APPEND);
        }
    }

    fclose($handle);
    unlink($tmpName);
    file_put_contents('upload_success.log', "✔ Uploaded: $originalName → $table\n", FILE_APPEND);
}

$conn->close();
header("Location: main.php");
exit;
?>
