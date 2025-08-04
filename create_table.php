<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$username = strtolower($_SESSION['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folder'], $_POST['filename'], $_POST['second_language'])) {
    $folder = trim($_POST['folder']);
    $filename = trim($_POST['filename']);
    $secondLanguage = trim($_POST['second_language']);

    if (!$folder || !$filename || !$secondLanguage) {
        $message = "All fields are required.";
    } else {
        // Sanitize and build table name
        $table = strtolower($username . "_" . $folder . "_" . $filename);
        $table = preg_replace('/[^a-z0-9_]+/', '_', $table);
        $table = trim($table, '_');

        $col1 = 'Czech';
        $col2 = preg_replace('/[^a-z0-9_]+/i', '_', $secondLanguage);
        $col2 = trim($col2, '_');

        $conn->set_charset("utf8mb4");

        // Check if table already exists
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $message = "Table '$table' already exists.";
        } else {
            $sql = "CREATE TABLE `$table` (
                        `$col1` VARCHAR(255) NOT NULL,
                        `$col2` VARCHAR(255) NOT NULL
                    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";

            if ($conn->query($sql)) {
                $message = "✔ Table '$table' created successfully.";
            } else {
                $message = "Error creating table: " . $conn->error;
            }
        }

        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create New Table</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        label { display: block; margin-top: 1em; }
        input[type="text"] { width: 300px; }
        button { margin-top: 1em; }
        .message { margin-top: 1em; color: darkgreen; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Create New Table</h2>
    <form method="POST">
        <label>Folder Name (e.g., <em>prag</em>):</label>
        <input type="text" name="folder" required>

        <label>File Name (e.g., <em>04jacob</em>):</label>
        <input type="text" name="filename" required>

        <label>Second Language Column (e.g., <em>German</em>, <em>English</em>, etc.):</label>
        <input type="text" name="second_language" required>

        <button type="submit">➕ Create Table</button>
    </form>

    <?php if (isset($message)): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <br>
    <a href="upload.php">⬅ Back to Upload</a>
</body>
</html>
