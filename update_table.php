<?php
require_once 'db.php';

$table = $_POST['table'] ?? '';
$rows = $_POST['rows'] ?? [];
$newRow = $_POST['new_row'] ?? [];

if (!$table) {
    die("Missing table name.");
}

$col1 = $_SESSION['col1'] ?? '';
$col2 = $_SESSION['col2'] ?? '';

if (!$col1 || !$col2) {
    die("Missing column definitions.");
}

$conn->set_charset("utf8mb4");

// Update or delete existing rows
foreach ($rows as $row) {
    $value1 = trim($row['col1']);
    $value2 = trim($row['col2']);

    if (isset($row['delete'])) {
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$col1` = ? AND `$col2` = ?");
        $stmt->bind_param("ss", $value1, $value2);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE `$table` SET `$col2` = ? WHERE `$col1` = ?");
        $stmt->bind_param("ss", $value2, $value1);
        $stmt->execute();
        $stmt->close();
    }
}

// Insert new row
if (!empty($newRow['col1']) && !empty($newRow['col2'])) {
    $stmt = $conn->prepare("INSERT INTO `$table` (`$col1`, `$col2`) VALUES (?, ?)");
    $stmt->bind_param("ss", $newRow['col1'], $newRow['col2']);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
