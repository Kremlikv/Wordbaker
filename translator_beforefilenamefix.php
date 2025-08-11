<?php
require_once 'session.php';
include 'styling.php';

/**
 * =========================
 *  CONFIG (edit as needed)
 * =========================
 * If you set GOOGLE_API_KEY, we’ll use Google first (most literal and reliable).
 * Else if you set LIBRETRANSLATE_URL, we’ll use LibreTranslate (free instances vary).
 * Otherwise we fall back to MyMemory (may be “creative”).
 */
const GOOGLE_API_KEY = ''; // e.g. 'AIza...'; leave '' to disable
const LIBRETRANSLATE_URL = ''; // e.g. 'https://libretranslate.com/translate'; leave '' to disable

// ---------------------

$text_lines = '';
$lines = [];
$translated = [];
$sourceLang = $_POST['sourceLang'] ?? '';
$targetLang = $_POST['targetLang'] ?? '';
$engine = $_POST['engine'] ?? ''; // optional UI in future; autodetect below

// Normalize non-ISO codes used in older UI ('sp' -> 'es')
function norm_lang($code) {
    $map = ['sp' => 'es'];
    return $map[$code] ?? $code;
}
$sourceLang = norm_lang($sourceLang);
$targetLang = norm_lang($targetLang);

$langLabels = [
    'en' => 'English',
    'de' => 'German',
    'fr' => 'French',
    'it' => 'Italian',
    'es' => 'Spanish',
    'cs' => 'Czech',
    'auto' => 'Auto Detect',
    '' => 'Foreign'
];

// Labels (used for right column header only; left is always Czech)
$sourceLabel = $langLabels[$sourceLang] ?? 'Foreign';
$targetLabel = $langLabels[$targetLang] ?? 'Czech';

// DB/table params
$tableName = $_POST['new_table_name'] ?? '';
$deletePdfPath = $_POST['delete_pdf_path'] ?? '';

// ---------- HTTP helpers ----------
function http_post_json($url, $payloadArr, $headers = []) {
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
            'content' => json_encode($payloadArr),
            'timeout' => 20
        ]
    ];
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

function http_get($url, $headers = []) {
    $options = [
        'http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", $headers),
            'timeout' => 20
        ]
    ];
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

// ---------- Translators ----------
function translate_google($text, $source, $target) {
    // Google v2 REST; if $source === 'auto' or '', we let Google auto-detect by omitting 'source'
    if (!GOOGLE_API_KEY) return null;
    $params = [
        'q'      => $text,
        'target' => $target ?: 'cs',
        'format' => 'text',
        'key'    => GOOGLE_API_KEY
    ];
    if ($source && $source !== 'auto') {
        $params['source'] = $source;
    }
    $url = 'https://translation.googleapis.com/language/translate/v2';
    $resp = http_post_json($url, $params);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    return $data['data']['translations'][0]['translatedText'] ?? null;
}

function translate_libre($text, $source, $target) {
    if (!LIBRETRANSLATE_URL) return null;
    // LibreTranslate expects ISO codes; 'auto' is usually 'auto'
    $payload = [
        'q' => $text,
        'source' => ($source && $source !== 'auto') ? $source : 'auto',
        'target' => $target ?: 'cs',
        'format' => 'text'
    ];
    $resp = http_post_json(LIBRETRANSLATE_URL, $payload);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    // Some instances return { translatedText: "..." }, others array/other shape
    if (is_array($data) && isset($data['translatedText'])) return $data['translatedText'];
    return null;
}

function translate_mymemory($text, $source, $target) {
    // MyMemory may be "creative"; still useful as last fallback
    $src = $source ?: 'auto';
    $tgt = $target ?: 'cs';
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$src}|{$tgt}";
    $response = @file_get_contents($url);
    if (!$response) return null;
    $data = json_decode($response, true);
    return $data['responseData']['translatedText'] ?? null;
}

function translate_text($text, $source, $target) {
    // Priority: Google -> Libre -> MyMemory
    $out = translate_google($text, $source, $target);
    if ($out !== null && $out !== '') return $out;

    $out = translate_libre($text, $source, $target);
    if ($out !== null && $out !== '') return $out;

    $out = translate_mymemory($text, $source, $target);
    if ($out !== null && $out !== '') return $out;

    return '[Translation failed]';
}

// ---------- Build rows: always Czech on the LEFT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text_lines'])) {
    $text_lines = trim($_POST['text_lines']);
    // Merge lines, split into sentences
    $mergedText = preg_replace("/\s+\n\s+|\n+/", ' ', $text_lines);
    $sentences = preg_split('/(?<=[.!?:])\s+(?=[A-Z\xC0-\xFF])/', $mergedText);
    $lines = array_filter(array_map('trim', $sentences));

    foreach ($lines as $line) {
        if ($sourceLang === 'cs') {
            // Czech is source: left = original Czech, right = foreign translation
            $foreign = translate_text($line, $sourceLang, $targetLang ?: '');
            $cz = $line;
        } else {
            // Czech is NOT source: left = Czech translation, right = original foreign
            // Force target to 'cs' for left column
            $cz = translate_text($line, $sourceLang ?: 'auto', 'cs');
            $foreign = $line;
        }
        $translated[] = ['cz' => $cz, 'foreign' => $foreign];
        usleep(500000); // be gentle with free APIs
    }

    if ($deletePdfPath && file_exists($deletePdfPath)) {
        unlink($deletePdfPath);
    }
}

// ---------- Save table: LEFT = Czech, RIGHT = other language label ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_data'])) {
    // We recompute labels here to avoid trusting hidden inputs
    $post_source = norm_lang($_POST['sourceLang'] ?? '');
    $post_target = norm_lang($_POST['targetLang'] ?? '');
    $langLabelsLocal = [
        'en' => 'English','de' => 'German','fr' => 'French','it' => 'Italian','es' => 'Spanish','cs' => 'Czech','auto' => 'Auto Detect','' => 'Foreign'
    ];

    $col1 = 'Czech';
    // If user translated from Czech to X, right column is X; else it's source language
    $col2 = ($post_source === 'cs')
        ? ($langLabelsLocal[$post_target] ?? 'Foreign')
        : ($langLabelsLocal[$post_source] ?? 'Foreign');

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
    if ($result && $result->num_rows > 0) die("Table '$safeTable' already exists.");

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
    textarea { width: 90%; font-size: 1em; margin-top: 10px; overflow: hidden; resize: vertical; }
    table { margin-top: 20px; border-collapse: collapse; width: 90%; margin: auto; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    form { text-align: center; margin-top: 30px; }
    .engine-badge { margin-top: 8px; font-size: 0.95em; opacity: 0.8; }
  </style>
  <script>
    function breakSentences() {
      const textarea = document.getElementById("text_lines");
      let text = textarea.value;
      text = text.replace(/\s+\n\s+|\n+/g, ' ');
      text = text.replace(/([.!?:])\s+(?=[A-Z\xC0-\xFF])/g, "$1\n");
      textarea.value = text;
      autoResize(textarea);
    }
    function autoResize(textarea) {
      textarea.style.height = 'auto';
      textarea.style.overflow = 'hidden';
      textarea.style.height = textarea.scrollHeight + 'px';
    }
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll("textarea").forEach(autoResize);
      document.addEventListener("input", function (e) {
        if (e.target && e.target.tagName === "TEXTAREA") autoResize(e.target);
      });
    });
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
        event.preventDefault(); return false;
      }
      if (!tableOk) {
        alert("❌ Table name is already used. Please choose another.");
        event.preventDefault(); return false;
      }
      return true;
    }
  </script>
</head>
<body>

<?php
echo "<div class='content'>";
echo "👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>";
?>

<h2>🌍 Translate Sentences to Table</h2>

<form method="POST" onsubmit="return validateLangSelection(event)">
  <label>New Table Name:
    <input type="text" name="new_table_name" id="new_table_name" value="<?php echo htmlspecialchars($tableName ?: 'translated_table'); ?>" required oninput="checkTableName()">
  </label>
  <div id="tableWarning" data-valid="false" style="font-weight: bold; margin-bottom: 10px;"></div>

  <label>Paste or review lines:<br>
  <p>One translation request max 500 characters.</label><br>
    <textarea name="text_lines" id="text_lines" rows="10"><?php echo htmlspecialchars($text_lines); ?></textarea>
  </label><br>
  <button type="button" onclick="breakSentences()">✂️ Break into Sentences</button><br><br>

  <label>Source Language:
    <select name="sourceLang" id="sourceLang">
      <option value="" disabled selected>Select source language</option>
      <option value="auto" <?= ($sourceLang === 'auto') ? 'selected' : '' ?>>Auto Detect</option>
      <option value="en" <?= ($sourceLang === 'en') ? 'selected' : '' ?>>English</option>
      <option value="de" <?= ($sourceLang === 'de') ? 'selected' : '' ?>>German</option>
      <option value="fr" <?= ($sourceLang === 'fr') ? 'selected' : '' ?>>French</option>
      <option value="it" <?= ($sourceLang === 'it') ? 'selected' : '' ?>>Italian</option>
      <option value="es" <?= ($sourceLang === 'es') ? 'selected' : '' ?>>Spanish</option>
      <option value="cs" <?= ($sourceLang === 'cs') ? 'selected' : '' ?>>Czech</option>
    </select>
  </label>

  <label>Target Language:
    <select name="targetLang" id="targetLang">
      <option value="" disabled selected>Select target language</option>
      <option value="cs" <?= ($targetLang === 'cs') ? 'selected' : '' ?>>Czech</option>
      <option value="en" <?= ($targetLang === 'en') ? 'selected' : '' ?>>English</option>
      <option value="de" <?= ($targetLang === 'de') ? 'selected' : '' ?>>German</option>
      <option value="fr" <?= ($targetLang === 'fr') ? 'selected' : '' ?>>French</option>
      <option value="it" <?= ($targetLang === 'it') ? 'selected' : '' ?>>Italian</option>
      <option value="es" <?= ($targetLang === 'es') ? 'selected' : '' ?>>Spanish</option>
      <option value="cs" <?= ($targetLang === 'cs') ? 'selected' : '' ?>>Czech</option>
    </select>
  </label><br><br>

  <div class="engine-badge">
    <?php
      if (GOOGLE_API_KEY)       echo "Using: Google Cloud Translation API";
      elseif (LIBRETRANSLATE_URL) echo "Using: LibreTranslate";
      else                        echo "Using: MyMemory (free; may be creative)";
    ?>
  </div>

  <!-- Hidden labels kept for compatibility (not trusted for DB save) -->
  <input type="hidden" name="source_lang_label" value="<?= htmlspecialchars($sourceLabel) ?>">
  <input type="hidden" name="target_lang_label" value="<?= htmlspecialchars($targetLabel) ?>">
  <input type="hidden" name="delete_pdf_path" value="<?= htmlspecialchars($deletePdfPath) ?>">

  <button type="submit">🌐 Translate</button>
  <label style="opacity:0.7;">Engines: Google / Libre / MyMemory</label><br><br>
</form>

<?php if (!empty($translated)): ?>
  <form method="POST">
    <h3>Translated Preview</h3>
    <table>
      <thead>
        <tr>
          <th>Czech</th>
          <th>
            <?php
              // Right column label: if source was Czech, show target; else show source
              $rightLabel = ($sourceLang === 'cs') ? ($langLabels[$targetLang] ?? 'Foreign') : ($langLabels[$sourceLang] ?? 'Foreign');
              echo htmlspecialchars($rightLabel);
            ?>
          </th>
        </tr>
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

    <!-- Keep essentials for save -->
    <input type="hidden" name="new_table_name" value="<?= htmlspecialchars($tableName) ?>">
    <input type="hidden" name="sourceLang" value="<?= htmlspecialchars($sourceLang) ?>">
    <input type="hidden" name="targetLang" value="<?= htmlspecialchars($targetLang) ?>">

    <button type="submit">💾 Save Table to Database</button>
  </form>
<?php endif; ?>

</body>
</html>
