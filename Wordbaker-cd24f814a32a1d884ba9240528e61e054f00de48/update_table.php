<?php
require_once 'db.php';

$table = $_POST['table'] ?? '';
$col1 = $_POST['col1'] ?? '';
$col2 = $_POST['col2'] ?? '';
$rows = $_POST['rows'] ?? [];
$newRow = $_POST['new_row'] ?? [];

if (!$table || !$col1 || !$col2) {
    die("❌ Missing table or column definitions.");
}

$conn->set_charset("utf8mb4");

// Process existing rows
foreach ($rows as $row) {
    $new1 = trim($row['col1']);
    $new2 = trim($row['col2']);
    $orig1 = trim($row['orig_col1'] ?? '');
    $orig2 = trim($row['orig_col2'] ?? '');

    // Skip if original values are missing (sanity check)
    if ($orig1 === '' && $orig2 === '') {
        continue;
    }

    if (isset($row['delete'])) {
        // Safe delete using original values
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$col1` = ? AND `$col2` = ?");
        $stmt->bind_param("ss", $orig1, $orig2);
        $stmt->execute();
        $stmt->close();
    } elseif ($new1 !== $orig1 || $new2 !== $orig2) {
        // Only update if something changed
        $stmt = $conn->prepare("UPDATE `$table` SET `$col1` = ?, `$col2` = ? WHERE `$col1` = ? AND `$col2` = ?");
        $stmt->bind_param("ssss", $new1, $new2, $orig1, $orig2);
        $stmt->execute();
        $stmt->close();
    }
}

// Insert new row if both fields are filled
if (!empty($newRow['col1']) && !empty($newRow['col2'])) {
    $new1 = trim($newRow['col1']);
    $new2 = trim($newRow['col2']);

    $stmt = $conn->prepare("INSERT INTO `$table` (`$col1`, `$col2`) VALUES (?, ?)");
    $stmt->bind_param("ss", $new1, $new2);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
