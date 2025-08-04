<?php
require_once 'session.php';
include 'styling.php';

$text_lines = '';
$lines = [];
$translated = [];
$sourceLang = $_POST['sourceLang'] ?? '';
$targetLang = $_POST['targetLang'] ?? '';

$langLabels = [
    'en' => 'English',
    'de' => 'German',
    'fr' => 'French',
    'it' => 'Italian',
    'cs' => 'Czech',
    'auto' => 'Auto Detect',
    '' => 'Foreign'
];

$sourceLabel = $langLabels[$sourceLang] ?? 'Foreign';
$targetLabel = $langLabels[$targetLang] ?? 'Czech';

$tableName = $_POST['new_table_name'] ?? '';
$deletePdfPath = $_POST['delete_pdf_path'] ?? '';

function translate_text($text, $source, $target) {
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$source}|{$target}";
    $response = @file_get_contents($url);
    if (!$response) return '[Translation failed]';
    $data = json_decode($response, true);
    return $data['responseData']['translatedText'] ?? '[Translation failed]';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text_lines'])) {
    $text_lines = trim($_POST['text_lines']);
    $mergedText = preg_replace("/\s+\n\s+|\n+/", ' ', $text_lines);
    $sentences = preg_split('/(?<=[.!?:])\s+(?=[A-Z\xC0-\xFF])/', $mergedText);
    $lines = array_filter(array_map('trim', $sentences));

    foreach ($lines as $line) {
        $cz = translate_text($line, $sourceLang, $targetLang);
        $translated[] = ['cz' => $cz, 'foreign' => $line];
        usleep(500000);
    }

    if ($deletePdfPath && file_exists($deletePdfPath)) {
        unlink($deletePdfPath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_data'])) {
    $col1 = $_POST['target_lang_label'] ?? 'Czech';
    $col2 = $_POST['source_lang_label'] ?? 'Foreign';
    $tableName = $_POST['new_table_name'] ?? '';

    $tableData = $_POST['table_data'];
    if (!is_array($tableData)) die("❌ Invalid table data format.");

    // $conn = new mysqli('sql113.byethost15.com', 'b15_39452825', '5761VkRpAk', 'b15_39452825_KremlikDatabase01');
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName);
    $col1_safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $col1);
    $col2_safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $col2);

    $result = $conn->query("SHOW TABLES LIKE '$safeTable'");
    if ($result->num_rows > 0) die("Table '$safeTable' already exists.");

    $create_sql = "CREATE TABLE `$safeTable` (
        `$col1_safe` VARCHAR(255) NOT NULL,
        `$col2_safe` VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$conn->query($create_sql)) die("Create failed: " . $conn->error);

    $stmt = $conn->prepare("INSERT INTO `$safeTable` (`$col1_safe`, `$col2_safe`) VALUES (?, ?)");
    $count = 0;
    foreach ($tableData as $row) {
        $cz = trim($row['cz'] ?? '');
        $foreign = trim($row['foreign'] ?? '');
        if ($cz && $foreign) {
            $stmt->bind_param("ss", $cz, $foreign);
            $stmt->execute();
            $count++;
        }
    }

    $stmt->close();
    $conn->close();

    echo "<p style='color: green;'>✅ Table '$safeTable' saved with $count rows.</p>";
    echo "<a href='main.php'>Return to Main</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Translate and Import Table</title>
  <style>
    textarea { width: 90%; font-size: 1em; margin-top: 10px; }
    table { margin-top: 20px; border-collapse: collapse; width: 90%; margin: auto; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    form { text-align: center; margin-top: 30px; }
  </style>
  <script>
    function breakSentences() {
      const textarea = document.getElementById("text_lines");
      let text = textarea.value;
      text = text.replace(/\s+\n\s+|\n+/g, ' ');
      text = text.replace(/([.!?:])\s+(?=[A-Z\xC0-\xFF])/g, "$1\n");
      textarea.value = text;
    }

    function checkTableName() {
      const tableInput = document.getElementById("new_table_name");
      const warning = document.getElementById("tableWarning");
      const tableName = tableInput.value.trim();

      if (!tableName) {
        warning.textContent = "⚠️ Please enter a table name.";
        warning.style.color = "red";
        warning.setAttribute("data-valid", "false");
        return;
      }

      fetch("check_table_name.php?name=" + encodeURIComponent(tableName))
        .then(res => res.json())
        .then(data => {
          if (data.exists) {
            warning.textContent = "❌ Table '" + tableName + "' already exists.";
            warning.style.color = "red";
            warning.setAttribute("data-valid", "false");
          } else {
            warning.textContent = "✅ Table name is available.";
            warning.style.color = "green";
            warning.setAttribute("data-valid", "true");
          }
        })
        .catch(() => {
          warning.textContent = "⚠️ Could not verify table name.";
          warning.style.color = "orange";
          warning.setAttribute("data-valid", "false");
        });
    }

    function validateLangSelection(event) {
      const source = document.getElementById("sourceLang").value;
      const target = document.getElementById("targetLang").value;
      const tableOk = document.getElementById("tableWarning").getAttribute("data-valid") === "true";

      if (!source || !target) {
        alert("⚠️ Please select both source and target languages.");
        event.preventDefault();
        return false;
      }

      if (!tableOk) {
        alert("❌ Table name is already used. Please choose another.");
        event.preventDefault();
        return false;
      }

      return true;
    }
  </script>
</head>
<body>

<!-- Login info -->
<?php echo "<div class='content'>";
echo "👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>"; ?>

<h2>🌍 Translate Sentences to Table</h2>

<form method="POST" onsubmit="return validateLangSelection(event)">
  <label>New Table Name:
    <input type="text" name="new_table_name" id="new_table_name" value="<?php echo htmlspecialchars($tableName ?: 'translated_table'); ?>" required oninput="checkTableName()">
  </label>
  <div id="tableWarning" data-valid="false" style="font-weight: bold; margin-bottom: 10px;"></div>

  <label>Paste or review lines:<br>
    <textarea name="text_lines" id="text_lines" rows="10"><?php echo htmlspecialchars($text_lines); ?></textarea>
  </label><br>
  <button type="button" onclick="breakSentences()">✂️ Break into Sentences</button><br><br>

  <label>Source Language:
    <select name="sourceLang" id="sourceLang">
      <option value="" disabled selected>Select source language</option>
      <option value="auto" <?= $sourceLang === 'auto' ? 'selected' : '' ?>>Auto Detect</option>
      <option value="en">English</option>
      <option value="de">German</option>
      <option value="fr">French</option>
      <option value="it">Italian</option>
    </select>
  </label>

  <label>Target Language:
    <select name="targetLang" id="targetLang">
      <option value="" disabled selected>Select target language</option>
      <option value="cs" <?= $targetLang === 'cs' ? 'selected' : '' ?>>Czech</option>
      <option value="en">English</option>
      <option value="de">German</option>
      <option value="fr">French</option>
      <option value="it">Italian</option>
    </select>
  </label><br><br>

  <input type="hidden" name="source_lang_label" value="<?= htmlspecialchars($sourceLabel) ?>">
  <input type="hidden" name="target_lang_label" value="<?= htmlspecialchars($targetLabel) ?>">
  <input type="hidden" name="delete_pdf_path" value="<?= htmlspecialchars($deletePdfPath) ?>">

  <button type="submit">🌐 Translate</button>
  <label>mymemory.translated.net</label><br><br>

</form>

<?php if (!empty($translated)): ?>
  <form method="POST">
    <h3>Translated Preview</h3>
    <table>
      <thead>
        <tr><th><?php echo htmlspecialchars($targetLabel); ?></th><th><?php echo htmlspecialchars($sourceLabel); ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($translated as $index => $pair): ?>
          <tr>
            <td><input type="text" name="table_data[<?= $index ?>][cz]" value="<?= htmlspecialchars($pair['cz']) ?>" style="width: 100%;"></td>
            <td><input type="text" name="table_data[<?= $index ?>][foreign]" value="<?= htmlspecialchars($pair['foreign']) ?>" style="width: 100%;"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table><br>

    <input type="hidden" name="new_table_name" value="<?= htmlspecialchars($tableName) ?>">
    <input type="hidden" name="target_lang_label" value="<?= htmlspecialchars($targetLabel) ?>">
    <input type="hidden" name="source_lang_label" value="<?= htmlspecialchars($sourceLabel) ?>">

    <button type="submit">💾 Save Table to Database</button>
  </form>
<?php endif; ?>

</body>
</html>
