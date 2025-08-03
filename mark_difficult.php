<?php

file_put_contents("error_log_mark_difficult.txt", print_r($_POST, true), FILE_APPEND);

require_once 'session.php';
require_once 'db.php';  

$user_id = $_SESSION['user_id'] ?? null;

$source = trim($_POST['source_word'] ?? '');
$target = trim($_POST['target_word'] ?? '');
$language = trim($_POST['language'] ?? '');
$table = trim($_POST['table_name'] ?? '');

if (!$user_id || !$source || !$target || !$language || !$table) {
    http_response_code(400);
    echo "Missing data.";
    exit;
}

// Check if already marked for this user and table
$stmt = $conn->prepare("SELECT 1 FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ? AND table_name = ?");
$stmt->bind_param("isss", $user_id, $source, $target, $table);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    // Insert if not exists
    $insert = $conn->prepare("INSERT INTO difficult_words (source_word, target_word, language, user_id, table_name) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("sssiss", $source, $target, $language, $user_id, $table);
    $insert->execute();
}

$stmt->close();
$conn->close();
echo "Marked as difficult.";
