<?php
// Database connection
require_once 'db.php';

// $conn = new mysqli('sql113.byethost15.com', 'b15_39452825', '5761VkRpAk', 'b15_39452825_KremlikDatabase01');
$conn->set_charset("utf8mb4"); // ✅ Ensure charset matches DB

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get table name and validate
$table = $_POST['new_table_name'] ?? '';
if (!$table || !isset($_FILES['csv_file'])) {
    die("Missing table name or CSV file.");
}
$table = preg_replace('/[^a-zA-Z0-9_]/', '_', $table);

// Check if table already exists
$result = $conn->query("SHOW TABLES LIKE '$table'");
if ($result->num_rows > 0) {
    die("Table '$table' already exists. Please choose another name.");
}

// Validate uploaded file
$tmpName = $_FILES['csv_file']['tmp_name'];
if (!is_uploaded_file($tmpName)) {
    die("File upload failed.");
}
if ($_FILES['csv_file']['size'] > 2 * 1024 * 1024) { // 2MB limit
    die("File too large. Max 2MB allowed.");
}
$mime = mime_content_type($tmpName);
if (!in_array($mime, ['text/plain', 'text/csv', 'application/vnd.ms-excel'])) {
    die("Invalid file type. Only CSV files are allowed.");
}

// Open file
$handle = fopen($tmpName, 'r');
if (!$handle) {
    die("Failed to open uploaded file.");
}

// Read header row
$headers = fgetcsv($handle);
if (!$headers || count($headers) < 2) {
    rewind($handle);
    $headers = fgetcsv($handle, 1000, ';');
}
if (!$headers || count($headers) < 2) {
    file_put_contents('upload_errors.log', "HEADER ERROR: Detected headers: " . json_encode($headers) . "\n", FILE_APPEND);
    die("CSV must have at least two columns in the header row.");
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
    die("No 'Czech' column found in header row.");
}

// Determine the second column
$secondIndex = -1;
foreach ($headers as $index => $header) {
    if ($index !== $czechIndex) {
        $secondIndex = $index;
        break;
    }
}
if ($secondIndex === -1) {
    die("No second language column found.");
}

// Sanitize column names
$lang1 = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($headers[$czechIndex]));
$lang2 = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($headers[$secondIndex]));

// Create table with proper charset and collation
$create_sql = "CREATE TABLE `$table` (
    `$lang1` VARCHAR(255) NOT NULL,
    `$lang2` VARCHAR(255) NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";

if (!$conn->query($create_sql)) {
    die("Failed to create table: " . $conn->error);
}

// Insert CSV rows
$rowNum = 1;
while (($data = fgetcsv($handle, 1000, ",")) !== false) {
    $rowNum++;

    // Try semicolon fallback
    if (count($data) < 2) {
        $data = str_getcsv(implode('', $data), ';');
    }

    if (!isset($data[$czechIndex]) || !isset($data[$secondIndex])) {
        file_put_contents('upload_errors.log', "Row $rowNum: Missing columns. Data: " . json_encode($data) . "\n", FILE_APPEND);
        continue;
    }

    $val1 = trim($conn->real_escape_string($data[$czechIndex]));
    $val2 = trim($conn->real_escape_string($data[$secondIndex]));

    if ($val1 === '' || $val2 === '') {
        file_put_contents('upload_errors.log', "Row $rowNum: Skipped empty values: '$val1', '$val2'\n", FILE_APPEND);
        continue;
    }

    $sql = "INSERT INTO `$table` (`$lang1`, `$lang2`) VALUES ('$val1', '$val2')";
    $res = $conn->query($sql);

    if (!$res) {
        file_put_contents('upload_errors.log', "Row $rowNum: Insert failed: $sql\nError: " . $conn->error . "\n", FILE_APPEND);
    } else {
        file_put_contents('upload_success.log', "Row $rowNum: Inserted: $val1, $val2\n", FILE_APPEND);
    }
}

fclose($handle);
unlink($tmpName); // ✅ Clean up temp file
$conn->close();

header("Location: main.php");
exit;
?>
