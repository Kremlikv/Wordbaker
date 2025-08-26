<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// also log to a file next to the script (make writable by PHP)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/translator_error.log');

// catch fatal errors on shutdown
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    error_log("FATAL: {$e['message']} in {$e['file']}:{$e['line']}");
  }
});



require_once 'session.php';
require_once 'config.php'; // expects $GOOGLE_API_KEY (and optional $LIBRETRANSLATE_URL)
include 'styling.php';

$text_lines = '';
$lines = [];
$translated = [];
$engineUsed = null; // which engine actually produced the preview
$sourceLang = $_POST['sourceLang'] ?? '';
$targetLang = $_POST['targetLang'] ?? '';

/* ---------- Username + table-name helpers (PREFIX ENFORCED) ---------- */
$username_raw = $_SESSION['username'] ?? 'user';
function sanitize_key_str($s) {
    // Keep letters, digits, underscore; collapse repeats; trim; lowercase
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9_]+/i', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}
function prefixed_table_name($username, $userInput) {
    $u = sanitize_key_str($username);
    $n = sanitize_key_str($userInput);
    $final = (strpos($n, $u . '_') === 0) ? $n : ($u . '_' . $n);
    // MySQL table name max length 64
    return substr($final, 0, 64);
}
$username_safe = sanitize_key_str($username_raw);

/* ---------- Lang handling ---------- */
function norm_lang($code) {
    $map = ['sp' => 'es'];
    return $map[$code] ?? $code;
}
$sourceLang = norm_lang($sourceLang);
$targetLang = norm_lang($targetLang);

$langLabels = [
    'en'   => 'English',
    'de'   => 'German',
    'fr'   => 'French',
    'it'   => 'Italian',
    'es'   => 'Spanish',
    'cs'   => 'Czech',
    'auto' => 'Auto Detect',
    ''     => 'Foreign'
];

$sourceLabel = $langLabels[$sourceLang] ?? 'Foreign';
$targetLabel = $langLabels[$targetLang] ?? 'Czech';

$tableNameInput = $_POST['new_table_name'] ?? '';
$deletePdfPath  = $_POST['delete_pdf_path'] ?? '';

/* ---------- HTTP helpers ---------- */
function http_post_json($url, $payloadArr, $headers = []) {
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
            'content' => json_encode($payloadArr),
            'timeout' => 20
        ]
    ];
    return @file_get_contents($url, false, stream_context_create($options));
}
function http_get_simple($url) {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 20]]);
    return @file_get_contents($url, false, $ctx);
}

/* ---------- Translation engines ---------- */
function translate_google($text, $source, $target) {
    global $GOOGLE_API_KEY;
    if (empty($GOOGLE_API_KEY)) return null;
    $query = [
        'q'      => $text,
        'target' => $target ?: 'cs',
        'format' => 'text',
        'key'    => $GOOGLE_API_KEY
    ];
    if ($source && $source !== 'auto') $query['source'] = $source;
    $url = 'https://translation.googleapis.com/language/translate/v2?' . http_build_query($query);
    $resp = http_get_simple($url);
    if (!$resp) { error_log('Google Translate HTTP error or empty response'); return null; }
    $data = json_decode($resp, true);
    if (isset($data['error'])) { error_log('Google Translate error: ' . ($data['error']['message'] ?? json_encode($data['error']))); return null; }
    return $data['data']['translations'][0]['translatedText'] ?? null;
}
function translate_libre($text, $source, $target) {
    global $LIBRETRANSLATE_URL;
    if (empty($LIBRETRANSLATE_URL)) return null;
    $payload = [
        'q'      => $text,
        'source' => ($source && $source !== 'auto') ? $source : 'auto',
        'target' => $target ?: 'cs',
        'format' => 'text'
    ];
    $resp = http_post_json($LIBRETRANSLATE_URL, $payload);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    return $data['translatedText'] ?? null;
}
function translate_mymemory($text, $source, $target) {
    $src = $source ?: 'auto';
    $tgt = $target ?: 'cs';
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$src}|{$tgt}";
    $response = @file_get_contents($url);
    if (!$response) return null;
    $data = json_decode($response, true);
    return $data['responseData']['translatedText'] ?? null;
}
// Return [text, engineName]
function translate_text_with_engine($text, $source, $target) {
    if (($g = translate_google($text, $source, $target)) !== null && $g !== '') return [$g, 'Google'];
    if (($l = translate_libre($text, $source, $target)) !== null && $l !== '') return [$l, 'LibreTranslate'];
    if (($m = translate_mymemory($text, $source, $target)) !== null && $m !== '') return [$m, 'MyMemory'];
    return ['[Translation failed]', 'None'];
}

/* ---------- Build rows: Czech ALWAYS left ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text_lines'])) {
    $text_lines = trim($_POST['text_lines']);

    // Merge lines into one string, normalize whitespace
    $mergedText = preg_replace("/\s+\n\s+|\n+/", ' ', $text_lines);

    // Unicode-aware sentence split: break after . ! ? : when followed by an uppercase letter
    // \p{Lu} = any uppercase letter, 'u' = unicode mode
    $lines = preg_split('/(?<=[.!?:])\s+(?=\p{Lu})/u', $mergedText);
    $lines = array_values(array_filter(array_map('trim', $lines)));

    foreach ($lines as $line) {
        if ($sourceLang === 'cs') {
            // Input is Czech; translate to chosen target (default empty => engine default)
            list($translatedText, $engine) = translate_text_with_engine($line, $sourceLang, $targetLang ?: '');
            $cz = $line;
            $foreign = $translatedText;
        } else {
            // Input is foreign; translate to Czech
            list($cz, $engine) = translate_text_with_engine($line, $sourceLang ?: 'auto', 'cs');
            $foreign = $line;
        }
        if ($engineUsed === null) $engineUsed = $engine;
        $translated[] = ['cz' => $cz, 'foreign' => $foreign];

        // gentle pacing
        usleep(500000);
    }

    if ($deletePdfPath && file_exists($deletePdfPath)) {
        @unlink($deletePdfPath);
    }
}

/* ---------- Save: enforce username_ prefix ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_data'])) {
    $post_source = norm_lang($_POST['sourceLang'] ?? '');
    $post_target = norm_lang($_POST['targetLang'] ?? '');

    $col1 = 'Czech'; // always left
    $col2 = ($post_source === 'cs')
        ? ($langLabels[$post_target] ?? 'Foreign')
        : ($langLabels[$post_source] ?? 'Foreign');

    // Raw user input name (from preview form)
    $rawInputName   = $_POST['new_table_name'] ?? '';
    $finalTableName = prefixed_table_name($username_raw, $rawInputName);

    $tableData = $_POST['table_data'];
    if (!is_array($tableData)) die("❌ Invalid table data format.");

    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $safeTable = $finalTableName; // already sanitized + prefixed + length-capped
    $col1_safe = sanitize_key_str($col1);
    $col2_safe = sanitize_key_str($col2);

    $result = $conn->query("SHOW TABLES LIKE '$safeTable'");
    if ($result && $result->num_rows > 0) die("Table '$safeTable' already exists.");

    $create_sql = "CREATE TABLE `$safeTable` (
        `$col1_safe` VARCHAR(255) NOT NULL,
        `$col2_safe` VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";
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

    echo "<p style='color: green;'>✅ Table '<strong>$safeTable</strong>' saved with $count rows.</p>";
    echo "<a href='main.php'>Return to Main</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Překlad a import slovníčku</title>
<style>
textarea { width: 90%; font-size: 1em; margin-top: 10px; overflow: hidden; resize: vertical; }
table { margin-top: 20px; border-collapse: collapse; width: 90%; margin: auto; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
form { text-align: center; margin-top: 30px; }
.engine-badge { margin-top: 8px; font-size: 0.95em; opacity: 0.8; }
.hint { font-size: 0.9em; opacity: 0.85; }
</style>
<script>
// Client-side mirror of sanitization to preview/check availability
const USERNAME_PREFIX = "<?= addslashes($username_safe . '_') ?>";
function sanitizeKeyStr(s) {
  s = (s || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/_+/g, '_');
  return s.replace(/^_+|_+$/g, '');
}
function makeFinalName(input) {
  const base = sanitizeKeyStr(input);
  let finalName = base.startsWith(USERNAME_PREFIX) ? base : (USERNAME_PREFIX + base);
  if (finalName.length > 64) finalName = finalName.substring(0, 64);
  return finalName;
}
function checkTableName() {
  const tableInput = document.getElementById("new_table_name");
  const warning = document.getElementById("tableWarning");
  const preview = document.getElementById("finalNamePreview");

  const typed = tableInput.value.trim();
  if (!typed) {
    warning.textContent = "⚠️ Zadejte prosím název slovníčku.";
    warning.style.color = "red";
    warning.setAttribute("data-valid", "false");
    preview.textContent = "";
    return;
  }

  const finalName = makeFinalName(typed);
  preview.textContent = "Bude uloženo jako: " + finalName;

  fetch("check_table_name.php?name=" + encodeURIComponent(finalName))
    .then(res => res.json())
    .then(data => {
      if (data.exists) {
        warning.textContent = "❌ Tabulka '" + finalName + "' už existuje.";
        warning.style.color = "red";
        warning.setAttribute("data-valid", "false");
      } else {
        warning.textContent = "✅ Název je volný.";
        warning.style.color = "green";
        warning.setAttribute("data-valid", "true");
      }
    })
  .catch(() => {
    warning.textContent = "⚠️ Nelze ověřit název tabulky.";
    warning.style.color = "orange";
    warning.setAttribute("data-valid", "false");
  });
}
function validateLangSelection(event) {
  const source = document.getElementById("sourceLang").value;
  const target = document.getElementById("targetLang").value;
  const tableOk = document.getElementById("tableWarning").getAttribute("data-valid") === "true";
  if (!source || !target) {
    alert("⚠️ Vyberte prosím zdrojový i cílový jazyk.");
    event.preventDefault(); return false;
  }
  if (!tableOk) {
    alert("❌ Název tabulky je již použit (nebo nebyl ověřen). Zvolte jiný.");
    event.preventDefault(); return false;
  }
  return true;
}
function breakSentences() {
  const textarea = document.getElementById("text_lines");
  let text = textarea.value;
  text = text.replace(/\s+\n\s+|\n+/g, ' ');
  // Unicode-aware: split when next char is an uppercase letter
  text = text.replace(/([.!?:])\s+(?=\p{Lu})/gu, "$1\n");
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
</script>
</head>
<body>
<div class='content'>
  👤 Přihlášený uživatel: <?= htmlspecialchars($username_raw) ?> | <a href='logout.php'>Odhlásit</a>
  <h2>🌍 Překlad frází do slovníčku</h2>
</div>

<form method="POST" onsubmit="return validateLangSelection(event)">
  <label>Název nového slovníčku:
    <input type="text" name="new_table_name" id="new_table_name"
           value="<?= htmlspecialchars($tableNameInput ?: 'adresář_soubor') ?>"
           required oninput="checkTableName()">
  </label>
  <div class="hint" id="finalNamePreview" style="margin-top:4px;"></div>
  <div id="tableWarning" data-valid="false" style="font-weight: bold; margin: 8px 0 10px;"></div>

  <label>Zkopírujte a/nebo zkontrolujte řádky:<br>
    <p>Jeden překlad smí mít max 500 znaků.</p>
  </label><br>
  <textarea name="text_lines" id="text_lines" rows="10"><?= htmlspecialchars($text_lines) ?></textarea><br>
  <button type="button" onclick="breakSentences()">✂️ Rozdělit do vět</button><br><br>

  <label>Zdrojový jazyk:
    <select name="sourceLang" id="sourceLang">
      <option value="" disabled <?= $sourceLang === '' ? 'selected' : '' ?>>Vyberte zdrojový jazyk</option>
      <option value="auto" <?= $sourceLang === 'auto' ? 'selected' : '' ?>>Automaticky</option>
      <option value="en"   <?= $sourceLang === 'en'   ? 'selected' : '' ?>>Anglicky</option>
      <option value="de"   <?= $sourceLang === 'de'   ? 'selected' : '' ?>>Německy</option>
      <option value="fr"   <?= $sourceLang === 'fr'   ? 'selected' : '' ?>>Francouzsky</option>
      <option value="it"   <?= $sourceLang === 'it'   ? 'selected' : '' ?>>Italsky</option>
      <option value="es"   <?= $sourceLang === 'es'   ? 'selected' : '' ?>>Španělsky</option>
      <option value="cs"   <?= $sourceLang === 'cs'   ? 'selected' : '' ?>>Česky</option>
    </select>
  </label>

  <label>Cílový jazyk:
    <select name="targetLang" id="targetLang">
      <option value="" disabled <?= $targetLang === '' ? 'selected' : '' ?>>Vyberte cílový jazyk</option>
      <option value="cs"   <?= $targetLang === 'cs'   ? 'selected' : '' ?>>Česky</option>
      <option value="en"   <?= $targetLang === 'en'   ? 'selected' : '' ?>>Anglicky</option>
      <option value="de"   <?= $targetLang === 'de'   ? 'selected' : '' ?>>Německy</option>
      <option value="fr"   <?= $targetLang === 'fr'   ? 'selected' : '' ?>>Francouzsky</option>
      <option value="it"   <?= $targetLang === 'it'   ? 'selected' : '' ?>>Italsky</option>
      <option value="es"   <?= $targetLang === 'es'   ? 'selected' : '' ?>>Španělsky</option>
    </select>
  </label><br><br>

  <div class="engine-badge">
    <?php
      if (!empty($GOOGLE_API_KEY))          echo "Překladové programy dle priority: Google → LibreTranslate → MyMemory";
      elseif (!empty($LIBRETRANSLATE_URL))  echo "Překladové programy dle priority: LibreTranslate → MyMemory";
      else                                  echo "Překladové programy: MyMemory (zdarma, nepřesné)";
    ?>
  </div>

  <input type="hidden" name="delete_pdf_path" value="<?= htmlspecialchars($deletePdfPath) ?>">

  <button type="submit">🌐 Přeložit</button>
</form>

<?php if (!empty($translated)): ?>
  <div class="engine-badge" style="text-align:center; margin-top:10px;">
    Program použitý k tomuto překladu: <strong><?= htmlspecialchars($engineUsed ?: 'Unknown') ?></strong>
  </div>

  <form method="POST">
    <h3>Náhled překladu</h3>
    <table>
      <thead>
        <tr>
          <th>Česky</th>
          <th><?= htmlspecialchars(($sourceLang === 'cs') ? ($langLabels[$targetLang] ?? 'Foreign') : ($langLabels[$sourceLang] ?? 'Foreign')) ?></th>
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

    <!-- Keep the raw name; server will prefix+sanitize on save -->
    <input type="hidden" name="new_table_name" value="<?= htmlspecialchars($tableNameInput) ?>">
    <input type="hidden" name="sourceLang" value="<?= htmlspecialchars($sourceLang) ?>">
    <input type="hidden" name="targetLang" value="<?= htmlspecialchars($targetLang) ?>">

    <button type="submit">💾 Uložit slovníček do databáze</button>
  </form>
<?php endif; ?>
</body>
</html>
