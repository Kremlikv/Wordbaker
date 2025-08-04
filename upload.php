<?php
require_once 'db.php';
require_once 'session.php';

$username = strtolower($_SESSION['username'] ?? '');

if (!$username) {
    header("Location: login.php");
    exit;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Upload CSV</title>";
include 'styling.php';
echo "</head><body>";

echo "<div class='content'>";
echo "👋 Logged in as " . htmlspecialchars($username) . " | <a href='logout.php'>Logout</a><br><br>";

if (!empty($_SESSION['uploaded_filename'])) {
    echo "<p style='color: green;'>✅ File uploaded: " . htmlspecialchars($_SESSION['uploaded_filename']) . "</p>";
    unset($_SESSION['uploaded_filename']);
}

echo <<<HTML
<h2>📤 Upload New Table</h2>

<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
  <label for="table_name"><strong>Table Name:</strong></label><br>
  <input type="text" id="table_name" name="table_name" required placeholder="e.g. animals_de"><br><br>

  <label><strong>Select CSV File:</strong></label><br>
  <input type="file" name="csv_file" accept=".csv" required><br><br>

  <p style="font-size: 0.9em; color: gray;">
    ➤ Your table will be saved as <strong>[username]_tablename</strong><br>
    ➤ CSV must include a <strong>“Czech”</strong> column and at least one foreign language column.<br>
    ➤ Encoding must be <strong>UTF-8</strong> without BOM.<br>
    ➤ Use only letters, digits, or underscores in the table name.
  </p>

  <button type="submit">📥 Upload</button>
</form>
HTML;

echo "</div></body></html>";
