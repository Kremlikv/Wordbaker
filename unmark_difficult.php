<?php
require_once 'session.php';
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$source_word = trim($_POST['source_word'] ?? '');
$target_word = trim($_POST['target_word'] ?? '');
$table_name = trim($_POST['table_name'] ?? '');


if (!$user_id || !$source_word || !$target_word || !$table_name) {
    http_response_code(400);
    echo "❌ Missing data.";
    exit;
}


// Debug log
error_log("UNMARK DEBUG - user_id: $user_id | source: '$source_word' | target: '$target_word'");

// Check if the word exists in difficult_words
$stmt = $conn->prepare("SELECT language FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
$stmt->bind_param("iss", $user_id, $source_word, $target_word);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $language = $row['language'];

    // Check if already in mastered_words
    $check = $conn->prepare("SELECT 1 FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
    $check->bind_param("iss", $user_id, $source_word, $target_word);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO mastered_words (source_word, target_word, language, last_seen, user_id, table_name) VALUES (?, ?, ?, NOW(), ?, ?)");
        $ins->bind_param("sssiss", $source_word, $target_word, $language, $user_id, $table_name);

        if (!$ins->execute()) {
            error_log("❌ Insert into mastered_words failed: " . $ins->error);
            http_response_code(500);
            echo "❌ Insert failed.";
            exit;
        }
        error_log("✅ Inserted into mastered_words for user $user_id: $source_word → $target_word");
    } else {
        error_log("ℹ️ Already in mastered_words: $source_word → $target_word");
    }

    // Delete from difficult_words
    $del = $conn->prepare("DELETE FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
    $del->bind_param("iss", $user_id, $source_word, $target_word);
    if (!$del->execute()) {
        error_log("❌ Delete from difficult_words failed: " . $del->error);
        http_response_code(500);
        echo "❌ Delete failed.";
        exit;
    }

    error_log("✅ Deleted from difficult_words for user $user_id: $source_word → $target_word");
    http_response_code(200);
    echo "✅ Success";
} else {
    error_log("❌ Word not found in difficult_words: $source_word → $target_word (user_id=$user_id)");
    http_response_code(404);
    echo "❌ Word not found in difficult_words.";
}
?>
