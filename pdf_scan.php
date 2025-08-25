<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- PDF Scan Start -->";

require_once 'session.php';
include 'styling.php';

use Smalot\PdfParser\Parser;

try {
    require_once 'pdfparser/alt_autoload.php';
    echo "<!-- PDF Parser loaded -->";
} catch (Throwable $e) {
    echo "❌ PDF parser load error: " . htmlspecialchars($e->getMessage());
    exit;
}

/** Human-readable size */
function human_filesize(int $bytes, int $decimals = 1): string {
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB','MB','GB','TB'];
    $factor = (int) floor((strlen((string)$bytes) - 1) / 3);
    $factor = max(0, min($factor, count($units)));
    return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor + 1), $units[$factor]);
}

/**
 * Quick preflight of a PDF to catch common failure modes early.
 * Returns ['ok'=>bool, 'reason'=>string|null, 'size'=>int|null, 'encrypted'=>bool]
 */
function pdf_preflight(string $path): array {
    if (!is_file($path)) return ['ok' => false, 'reason' => 'not_found', 'size' => null, 'encrypted' => false];
    $size = @filesize($path);
    $fh = @fopen($path, 'rb');
    if (!$fh) return ['ok' => false, 'reason' => 'unreadable', 'size' => $size ?: null, 'encrypted' => false];

    $head = @fread($fh, 16384); // 16 KB
    @fclose($fh);

    if ($head === false || strlen($head) < 4) {
        return ['ok' => false, 'reason' => 'unreadable', 'size' => $size ?: null, 'encrypted' => false];
    }

    $isEncrypted = (strpos($head, '/Encrypt') !== false);

    if (strpos($head, '%PDF-') !== 0) {
        return ['ok' => false, 'reason' => 'not_pdf', 'size' => $size ?: null, 'encrypted' => $isEncrypted];
    }
    if ($isEncrypted) {
        return ['ok' => false, 'reason' => 'encrypted', 'size' => $size ?: null, 'encrypted' => true];
    }

    return ['ok' => true, 'reason' => null, 'size' => (int)$size, 'encrypted' => false];
}

/**
 * Parses a PDF file with memory/time guardrails and optional page range.
 * Returns:
 *  - ['success'=>true, 'text'=>string]
 *  - ['success'=>false, 'error'=>'code', 'detail'=>'raw message (optional)']
 */
function parse_pdf_with_guardrails(string $path, ?string $pageRangeInput = null): array {
    $check = pdf_preflight($path);
    if (!$check['ok']) {
        return ['success' => false, 'error' => $check['reason'] ?? 'unknown'];
    }

    // Adjust limits based on size (tweak for your hosting)
    $size = $check['size'] ?? 0;
    if ($size > 10 * 1024 * 1024) {           // >10 MB
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);
    } else {
        @ini_set('memory_limit', '512M');
        @set_time_limit(60);
    }

    try {
        $parser = new Parser();
        $pdf    = $parser->parseFile($path);

        if ($pageRangeInput) {
            $pages = $pdf->getPages();
            $count = count($pages);
            $pageNumbers = [];
            foreach (explode(',', $pageRangeInput) as $part) {
                $part = trim($part);
                if ($part === '') continue;
                if (strpos($part, '-') !== false) {
                    [$start, $end] = array_map('intval', explode('-', $part, 2));
                    $start = max(1, $start);
                    $end   = min($count, $end);
                    if ($start <= $end) {
                        for ($i = $start; $i <= $end; $i++) $pageNumbers[] = $i;
                    }
                } else {
                    $i = (int)$part;
                    if ($i >= 1 && $i <= $count) $pageNumbers[] = $i;
                }
            }
            $pageNumbers = array_values(array_unique($pageNumbers));
            sort($pageNumbers);

            $text = '';
            foreach ($pageNumbers as $pageIndex) {
                $text .= $pages[$pageIndex - 1]->getText() . ' ';
            }
        } else {
            $text = $pdf->getText();
        }

        if (!is_string($text) || trim($text) === '') {
            // Typical for scanned/image-only PDFs
            return ['success' => false, 'error' => 'no_text_layer'];
        }

        return ['success' => true, 'text' => $text];
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $error = 'unknown';
        $m = strtolower($msg);
        if (str_contains($m, 'encrypt') || str_contains($m, 'password')) {
            $error = 'encrypted';
        } elseif (str_contains($m, 'memory') || str_contains($m, 'allowed memory')) {
            $error = 'memory_limit';
        } elseif (str_contains($m, 'not a pdf') || str_contains($m, 'header')) {
            $error = 'not_pdf';
        } elseif (str_contains($m, 'timeout') || str_contains($m, 'time limit')) {
            $error = 'timeout';
        }
        return ['success' => false, 'error' => $error, 'detail' => $msg];
    }
}

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$extractedText = '';
$error = '';
$pdfPreviewPath = '';
$defaultTableName = 'pdf_imported_' . date('Ymd_His');
$uploadedPdf = '';
$preflightInfo = null; // for the badge
$uploadedFilename = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Upload failed.";
    } else {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

        $uploadedFilename = 'upload_' . time() . '.pdf';
        $fullPath = $uploadDir . $uploadedFilename;
        $webPath  = 'uploads/' . $uploadedFilename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $error = "❌ Failed to save uploaded file.";
        } else {
            // Preflight now so we can show the badge immediately
            $pf = pdf_preflight($fullPath);
            $preflightInfo = [
                'size'      => $pf['size'] ?? null,
                'size_h'    => isset($pf['size']) ? human_filesize((int)$pf['size']) : '—',
                'encrypted' => (bool)($pf['encrypted'] ?? false),
                'ok'        => (bool)($pf['ok'] ?? false),
                'reason'    => $pf['reason'] ?? null,
                'filename'  => $uploadedFilename,
            ];

            $pdfPreviewPath = $webPath;
            $uploadedPdf    = $fullPath;

            $pageRangeInput = $_POST['page_range'] ?? '';

            // Parse (this repeats preflight inside, but keeps things clean)
            $result = parse_pdf_with_guardrails($fullPath, $pageRangeInput);

            if ($result['success']) {
                $text = preg_replace('/\s+/', ' ', $result['text']);
                $sentences = preg_split('/(?<=[.?!])\s+/', $text);
                $lines = array_filter(array_map('trim', $sentences));
                $lines = array_slice($lines, 0, 100); // keep first 100
                $extractedText = implode("\n", $lines);
            } else {
                $map = [
                    'encrypted'     => '🔒 This PDF appears to be password‑protected. Please upload an unencrypted copy.',
                    'memory_limit'  => '💾 The PDF is too large/complex for current memory limits. Try splitting it into smaller parts.',
                    'timeout'       => '⏱️ Parsing timed out. Try a smaller page range or split the file.',
                    'no_text_layer' => '🖼️ The PDF seems to be a scan (no selectable text). OCR is required.',
                    'not_pdf'       => '📄 The file does not appear to be a valid PDF.',
                    'not_found'     => '❌ File not found after upload.',
                    'unreadable'    => '❌ The file could not be read by PHP.',
                    'unknown'       => '❌ Could not parse this PDF (unknown parser error).'
                ];
                $error = $map[$result['error']] ?? '❌ PDF parsing failed.';
                if (!empty($result['detail'])) {
                    error_log('[pdf_scan.php] parser detail: ' . $result['detail']);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sken a překlad z PDF</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0px; }
    form { max-width: 800px; margin: auto; }
    input, select, button, textarea {
      width: 100%;
      box-sizing: border-box;
      margin-bottom: 15px;
      padding: 10px;
      font-size: 1em;
    }
    textarea {
      height: 300px;
      resize: vertical;
      user-select: text;
      -webkit-user-select: text;
      -ms-user-select: text;
      touch-action: manipulation;
      padding: 12px;
      line-height: 1.5;
    }
    iframe { width: 100%; height: 500px; border: 1px solid #aaa; }
    button {
      background-color: #4CAF50;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover { background-color: #45a049; }

    .file-badge {
      display: inline-block;
      background: #f6f8fa;
      border: 1px solid #d0d7de;
      color: #24292f;
      padding: 8px 10px;
      border-radius: 8px;
      font-size: 0.95em;
      margin: 6px 0 10px 0;
    }
    .file-badge strong { font-weight: 600; }

    @media (max-width: 600px) {
      input, select, button, textarea { font-size: 1em; }
      iframe { height: 300px; }
    }
  </style>
  <script>
    function updateLabels() {
      const sourceSelect = document.getElementById("sourceLang");
      const targetSelect = document.getElementById("targetLang");

      const sourceLabel = sourceSelect.options[sourceSelect.selectedIndex]?.text || '';
      const targetLabel = targetSelect.options[targetSelect.selectedIndex]?.text || '';

      document.getElementById("sourceLabel").value = (sourceSelect.value === 'auto') ? "Foreign" : sourceLabel;
      document.getElementById("targetLabel").value = targetLabel;
    }

    function validateLangSelection(event) {
      const source = document.getElementById("sourceLang").value;
      const target = document.getElementById("targetLang").value;

      if (!source || !target) {
        alert("⚠️ Please select both source and target languages.");
        event.preventDefault();
        return false;
      }
      return true;
    }

    function updateFontSize() {
      const size = document.getElementById("fontSizeSelect").value;
      document.getElementById("textArea").style.fontSize = size;
    }

    function updateSpellLang() {
      const lang = document.getElementById("spellLangSelect").value;
      const textarea = document.getElementById("textArea");
      const newTextarea = textarea.cloneNode(true);
      newTextarea.id = "textArea";

      if (lang === "") {
        newTextarea.setAttribute("spellcheck", "false");
        newTextarea.removeAttribute("lang");
      } else {
        newTextarea.setAttribute("spellcheck", "true");
        newTextarea.setAttribute("lang", lang);
      }

      textarea.parentNode.replaceChild(newTextarea, textarea);
    }

    function cleanWithAI() {
      const textArea = document.getElementById("textArea");
      const originalText = textArea.value;

      if (!originalText.trim()) {
        alert("Není co čistit.");
        return;
      }

      textArea.value = "⏳ Čištění pomocí AI...";
      fetch("ai_cleaner.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ text: originalText })
      })
      .then(res => res.json())
      .then(data => {
        if (data.cleaned) {
          textArea.value = data.cleaned.trim();
        } else {
          alert("Čištění selhalo.");
          textArea.value = originalText;
        }
      })
      .catch(err => {
        console.error(err);
        alert("Chyba v připojení k čistícímu programu.");
        textArea.value = originalText;
      });
    }

    function countCharacters() {
      const textArea = document.getElementById("textArea");
      const count = textArea.value.length;
      document.getElementById("charCountDisplay").textContent = "Počet znaků: " + count;
    }

    function copyText() {
      const textArea = document.getElementById("textArea");
      textArea.select();
      textArea.setSelectionRange(0, 99999);
      document.execCommand("copy");
      alert("Text zkopírován do schránky.");
    }

    window.addEventListener('DOMContentLoaded', updateLabels);
  </script>
</head>
<body>

<?php
echo "<div class='content'>";
echo "👤 Přihlášený uživatel " . htmlspecialchars($_SESSION['username'] ?? 'guest') . " | <a href='logout.php'>Odhlásit</a>";
?>

<h2 style="text-align:center;">📄 Sken PDF → Kontrola → Překlad</h2>

<?php if ($error): ?>
  <p style="color: red; text-align:center;">
    <?php echo htmlspecialchars($error); ?>
  </p>
<?php endif; ?>

<?php if (!$extractedText): ?>
  <form method="POST" enctype="multipart/form-data">
    <label>Zvolte PDF soubor:
      <input type="file" name="pdf_file" accept=".pdf" required>
    </label>

    <button type="submit">📤 Extrahovat text</button>

    <?php if ($preflightInfo): ?>
      <div class="file-badge">
        <strong>File:</strong> <?php echo htmlspecialchars($preflightInfo['filename']); ?> ·
        <strong>Size:</strong> <?php echo htmlspecialchars($preflightInfo['size_h']); ?> ·
        <strong>Encrypted:</strong> <?php echo $preflightInfo['encrypted'] ? 'Yes' : 'No'; ?>
        <?php if (!$preflightInfo['ok'] && $preflightInfo['reason']): ?>
          · <strong>Status:</strong> <?php echo htmlspecialchars($preflightInfo['reason']); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <label>Doporučuje se nahrávat jen malé PDF soubory.<br><br>
      PDF soubory lze rozdělit na menší pomocí nástrojů jako https://www.ilovepdf.com/split_pdf
      <br><br>
    </label>

    <label>Rozsah stránek (volitelné):
      <input type="text" name="page_range" placeholder="e.g. 1-3 or 2,4,6">
    </label>

    <label>Používáme: https://github.com/smalot/pdfparser</label><br><br>
  </form>
<?php else: ?>
  <?php if ($preflightInfo): ?>
    <div class="file-badge" style="max-width:800px; margin:0 auto 10px auto;">
      <strong>Original PDF:</strong> <?php echo htmlspecialchars($preflightInfo['filename']); ?> ·
      <strong>Size:</strong> <?php echo htmlspecialchars($preflightInfo['size_h']); ?> ·
      <strong>Encrypted:</strong> <?php echo $preflightInfo['encrypted'] ? 'Yes' : 'No'; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="translator.php" onsubmit="return validateLangSelection(event)">
    <label>Název nového slovníčku:
      <input type="text" name="new_table_name" value="<?php echo htmlspecialchars($defaultTableName); ?>" required>
    </label>

    <label>Zdrojový jazyk:
      <select name="sourceLang" id="sourceLang" onchange="updateLabels()" required>
        <option value="" disabled selected>Vyberte zdrojový jazyk</option>
        <option value="cs">Česky</option>
        <option value="en">Anglicky</option>
        <option value="de">Německy</option>
        <option value="fr">Francouzsky</option>
        <option value="es">Španělsky</option>
        <option value="it">Italsky</option>
      </select>
    </label>

    <label>Cílový jazyk:
      <select name="targetLang" id="targetLang" onchange="updateLabels()" required>
        <option value="" disabled selected>Vyberte cílový jazyk</option>
        <option value="cs">Česky</option>
        <option value="en">Anglicky</option>
        <option value="de">Německy</option>
        <option value="fr">Francouzsky</option>
        <option value="es">Španělsky</option>
        <option value="it">Italsky</option>
      </select>
    </label>

    <input type="hidden" name="source_lang_label" id="sourceLabel" value="">
    <input type="hidden" name="target_lang_label" id="targetLabel" value="">
    <input type="hidden" name="delete_pdf_path" value="<?php echo htmlspecialchars($uploadedPdf); ?>">

    <label>Velikot písma:
      <select id="fontSizeSelect" onchange="updateFontSize()">
        <option value="14px">Malá</option>
        <option value="18px" selected>Střední</option>
        <option value="24px">Velká</option>
      </select>
    </label>

    <label>Jazyk kontroly pravopisu:
      <select id="spellLangSelect" onchange="updateSpellLang()">
        <option value="cs">Česky</option>
        <option value="">Vypnuto</option>
        <option value="en">Anglicky</option>
        <option value="de">Německy</option>
        <option value="fr">Francouzsky</option>
        <option value="es">Španělsky</option>
        <option value="it">Italsky</option>
      </select>
    </label>

    <label>Kontrola či úpravy extrahovaného textu:
      <textarea id="textArea" name="text_lines" spellcheck="false"><?php echo htmlspecialchars($extractedText); ?></textarea>
    </label>

    <button type="button" onclick="cleanWithAI()">🧠 Clean Text with AI</button>

    <label> OpenRouter.ai za den provede max 50 požadavků na čištění textu zdarma </label><br>
    <p>Jeden překlad smí mít max 500 znaků.</p><br>

    <button type="button" onclick="countCharacters()">🔢 Počet znaků</button>
    <div id="charCountDisplay" style="margin-top:5px; font-weight:bold;"></div>

    <br>
    <button type="button" onclick="copyText()">📋 Zkopírovat</button>
    <button type="submit">🌍 Překlad</button>
  </form>

  <!-- Original PDF preview (optional) -->
  <details style="margin-top: 10px;">
    <summary style="cursor: pointer; font-weight: bold;">🗂️ Původní PDF</summary>
    <iframe src="<?= htmlspecialchars($pdfPreviewPath) ?>" style="width:100%; height:400px; border: 1px solid #aaa; margin-top:10px;"></iframe>
  </details>
<?php endif; ?>

</body>
</html>
