<?php
require_once 'session.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;

$source = trim($_POST['source_word'] ?? '');
$target = trim($_POST['target_word'] ?? '');
$language = trim($_POST['language'] ?? '');

if (!$user_id || !$source || !$target || !$language) {
    http_response_code(400);
    echo "Missing data.";
    exit;
}

// Check if already marked
$stmt = $conn->prepare("SELECT 1 FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
$stmt->bind_param("iss", $user_id, $source, $target);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    // Insert if not exists
    $insert = $conn->prepare("INSERT INTO difficult_words (source_word, target_word, language, user_id) VALUES (?, ?, ?, ?)");
    $insert->bind_param("sssi", $source, $target, $language, $user_id);
    $insert->execute();
}

$stmt->close();
$conn->close();
echo "Marked as difficult.";
