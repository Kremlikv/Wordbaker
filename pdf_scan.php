<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- PDF Scan Start with OCR fallback -->";

require_once 'session.php';
include 'styling.php';
require_once 'config.php';// <- API klíče (OCRSPACE_API_KEY)

use Smalot\PdfParser\Parser;

/* ==========================
   CONFIG
   ========================== */

/** OCR.Space API klíč z .config.php */
$OCRSPACE_API_KEY = defined('OCRSPACE_API_KEY') ? OCRSPACE_API_KEY : '';

/** Max PDF velikost pro OCR (OCR.Space free okolo 20 MB) */
const OCR_MAX_SIZE_BYTES = 20 * 1024 * 1024;

/** Výchozí OCR jazyk (OCR.Space kódy: eng, deu, fra, spa, ita, ces …) */
const OCR_DEFAULT_LANG = 'eng';

/** Mapování UI kódů -> OCR.Space kódy */
function ocrspace_lang_from_ui($ui) {
    $map = [
        'cs' => 'ces',
        'en' => 'eng',
        'de' => 'deu',
        'fr' => 'fra',
        'es' => 'spa',
        'it' => 'ita',
    ];
    return $map[$ui] ?? OCR_DEFAULT_LANG;
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
 * Rychlý preflight PDF
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
 * Smalot parse s guardrails + volitelným rozsahem
 * Returns:
 *  - ['success'=>true, 'text'=>string]
 *  - ['success'=>false, 'error'=>'code', 'detail'=>'message (optional)']
 */
function parse_pdf_with_guardrails(string $path, ?string $pageRangeInput = null): array {
    $check = pdf_preflight($path);
    if (!$check['ok']) {
        return ['success' => false, 'error' => $check['reason'] ?? 'unknown'];
    }

    // Limity podle velikosti
    $size = $check['size'] ?? 0;
    if ($size > 10 * 1024 * 1024) { // >10 MB
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

/* ==========================
   OCR: OCR.Space (bez parametru `pages`)
   ========================== */

/**
 * Stáhne soubor z URL do cílové cesty (cURL)
 */
function download_url_to_file(string $url, string $destPath): bool {
    $fp = @fopen($destPath, 'wb');
    if (!$fp) return false;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    $ok = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if ($ok === false || $http < 200 || $http >= 300) {
        error_log("[pdf_scan.php] download_url_to_file error: HTTP $http $err");
        @unlink($destPath);
        return false;
    }
    return true;
}

/**
 * OCR přes OCR.Space
 * @return array ['ok'=>bool, 'text'=>?string, 'error'=>?string, 'raw'=>mixed, 'searchable_local'=>?string]
 */
function ocrspace_pdf(string $pdfPath, string $language, string $apiKey): array {
    if (!is_file($pdfPath)) {
        return ['ok' => false, 'text' => null, 'error' => 'file_not_found'];
    }
    $size = filesize($pdfPath);
    if ($size === false || $size <= 0) {
        return ['ok' => false, 'text' => null, 'error' => 'invalid_file'];
    }
    if ($size > OCR_MAX_SIZE_BYTES) {
        return ['ok' => false, 'text' => null, 'error' => 'file_too_large'];
    }

    $endpoint = 'https://api.ocr.space/parse/image';
    $cfile = new CURLFile($pdfPath, 'application/pdf', basename($pdfPath));

    // ⚠️ Neposíláme 'pages' – endpoint /parse/image ho nezná
    $post = [
        'language'                      => $language,   // eng/deu/fra/spa/ita/ces...
        'isOverlayRequired'             => 'false',
        'isCreateSearchablePdf'         => 'true',      // zkusí vrátit searchable PDF
        'isSearchablePdfHideTextLayer'  => 'false',
        'scale'                         => 'true',
        'detectOrientation'             => 'true',
        'isTable'                       => 'false',
        'ocrengine'                     => '2',
        'file'                          => $cfile,
    ];

    $headers = [
        'apikey: ' . $apiKey,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'text' => null, 'error' => 'curl: ' . $err];
    }

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['ok' => false, 'text' => null, 'error' => 'bad_json', 'raw' => $response];
    }

    if ($http >= 200 && $http < 300 && ($json['IsErroredOnProcessing'] ?? null) === false) {
        // 1) Sloučit rozpoznaný text
        $combined = [];
        if (!empty($json['ParsedResults']) && is_array($json['ParsedResults'])) {
            foreach ($json['ParsedResults'] as $r) {
                if (isset($r['ParsedText']) && is_string($r['ParsedText'])) {
                    $combined[] = $r['ParsedText'];
                }
            }
        }
        $text = trim(implode("\n", $combined));

        // 2) Pokus o uložení 'searchable PDF' lokálně
        $localSearchable = null;
        $searchableUrl = $json['SearchablePDFURL'] ?? null;
        if ($searchableUrl && is_string($searchableUrl)) {
            $uploads = __DIR__ . '/uploads/';
            if (!is_dir($uploads)) @mkdir($uploads, 0775, true);
            $localName = 'ocr_' . time() . '.pdf';
            $localPath = $uploads . $localName;
            if (download_url_to_file($searchableUrl, $localPath)) {
                $localSearchable = 'uploads/' . $localName; // web cesta pro UI
            }
        }

        return ['ok' => true, 'text' => $text, 'error' => null, 'raw' => $json, 'searchable_local' => $localSearchable];
    }

    $msg = $json['ErrorMessage'] ?? $json['ErrorDetails'] ?? 'ocr_failed';
    if (is_array($msg)) $msg = implode(' | ', $msg);
    return ['ok' => false, 'text' => null, 'error' => (string)$msg, 'raw' => $json];
}

/* -------------------------
   Controller
   ------------------------- */
$extractedText    = '';
$error            = '';
$pdfPreviewPath   = '';
$searchableLocal  = ''; // cesta k uloženému searchable PDF, pokud vznikne
$defaultTableName = 'pdf_imported_' . date('Ymd_His');
$uploadedPdf      = '';
$preflightInfo    = null;
$uploadedFilename = '';

$selectedOcrUiLang = isset($_POST['ocr_ui_lang']) ? $_POST['ocr_ui_lang'] : '';

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
            // Preflight badge
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

            // 1) Smalot nejdřív
            $result = parse_pdf_with_guardrails($fullPath, $pageRangeInput);

            if ($result['success']) {
                $text = preg_replace('/\s+/', ' ', $result['text']);
                $sentences = preg_split('/(?<=[.?!])\s+/', $text);
                $lines = array_filter(array_map('trim', $sentences));
                $lines = array_slice($lines, 0, 100); // prvních 100 vět
                $extractedText = implode("\n", $lines);
            } else {
                // 2) OCR fallback pro skeny
                if (($result['error'] ?? '') === 'no_text_layer') {
                    $ocrLang = ocrspace_lang_from_ui($selectedOcrUiLang ?: 'en');

                    if ($OCRSPACE_API_KEY === '') {
                        $error = "🖼️ PDF vypadá jako sken (bez textové vrstvy) a vyžaduje OCR, ale chybí API klíč v .config.php (OCRSPACE_API_KEY).";
                    } else {
                        // Pozn.: OCR.Space /parse/image nepodporuje page_range => ignorováno
                        if (!empty($pageRangeInput)) {
                            error_log('[pdf_scan.php] Info: OCR fallback ignoruje page_range (omezení API).');
                        }

                        $ocr = ocrspace_pdf($fullPath, $ocrLang, $OCRSPACE_API_KEY);
                        if ($ocr['ok'] && is_string($ocr['text']) && trim($ocr['text']) !== '') {
                            $text = preg_replace('/\s+/', ' ', $ocr['text']);
                            $sentences = preg_split('/(?<=[.?!])\s+/', $text);
                            $lines = array_filter(array_map('trim', $sentences));
                            $lines = array_slice($lines, 0, 100);
                            $extractedText = implode("\n", $lines);

                            if (!empty($ocr['searchable_local'])) {
                                $searchableLocal = $ocr['searchable_local'];
                            }
                        } else {
                            $why = $ocr['error'] ?? 'OCR se nezdařilo.';
                            $error = "🖼️ Tento PDF je sken a parsování selhalo. OCR také selhalo: " . htmlspecialchars($why);
                        }
                    }
                } else {
                    $map = [
                        'encrypted'     => '🔒 This PDF appears to be password-protected. Please upload an unencrypted copy.',
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
}
?>
<!DOCTYPE html>
<html lang="cs">
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

    <label>Rozsah stránek (volitelné, u OCR se aktuálně ignoruje z důvodu omezení API):
      <input type="text" name="page_range" placeholder="např. 1-3 či 2,4,6">
    </label>

    <!-- OCR jazyk (použije se jen pokud se spustí OCR fallback) -->
    <label>Jazyk pro OCR:
      <select name="ocr_ui_lang">
        <option value="en" selected>Anglicky</option>
        <option value="cs">Česky</option>
        <option value="de">Německy</option>
        <option value="fr">Francouzsky</option>
        <option value="es">Španělsky</option>
        <option value="it">Italsky</option>
      </select>
    </label>

    <button type="submit">📤 Extrahovat text</button>

    <?php if ($preflightInfo): ?>
      <div class="file-badge">
        <strong>Soubor:</strong> <?php echo htmlspecialchars($preflightInfo['filename']); ?> ·
        <strong>Velikost:</strong> <?php echo htmlspecialchars($preflightInfo['size_h']); ?> ·
        <strong>Zašifrováno:</strong> <?php echo $preflightInfo['encrypted'] ? 'Ano' : 'Ne'; ?>
        <?php if (!$preflightInfo['ok'] && $preflightInfo['reason']): ?>
          · <strong>Stav:</strong> <?php echo htmlspecialchars($preflightInfo['reason']); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <label>Doporučuje se nahrávat jen malé PDF soubory.<br><br>
      PDF soubory lze rozdělit na menší pomocí nástrojů jako https://www.ilovepdf.com/split_pdf
      <br><br>
    </label>

    <label>Používáme: smalot/pdfparser s OCR fallback (OCR.Space)</label><br><br>
  </form>
<?php else: ?>
  <?php if ($preflightInfo): ?>
    <div class="file-badge" style="max-width:800px; margin:0 auto 10px auto;">
      <strong>Původní PDF:</strong> <?php echo htmlspecialchars($preflightInfo['filename']); ?> ·
      <strong>Velikost:</strong> <?php echo htmlspecialchars($preflightInfo['size_h']); ?> ·
      <strong>Zašifrováno:</strong> <?php echo $preflightInfo['encrypted'] ? 'Ano' : 'Ne'; ?>
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

    <?php if (!empty($searchableLocal)): ?>
      <p><a href="<?php echo htmlspecialchars($searchableLocal); ?>" target="_blank">⬇️ Stáhnout searchable PDF (OCR výstup)</a></p>
    <?php endif; ?>

    <button type="button" onclick="cleanWithAI()">🧠 Vyčistit text pomocí umělé inteligence</button>

    <label> OpenRouter.ai za den provede max 50 požadavků na čištění textu zdarma </label><br>
    <p>Jeden překlad smí mít max 500 znaků.</p><br>

    <button type="button" onclick="countCharacters()">🔢 Počet znaků</button>
    <div id="charCountDisplay" style="margin-top:5px; font-weight:bold;"></div>

    <br>
    <button type="button" onclick="copyText()">📋 Zkopírovat</button>
    <button type="submit">🌍 Překlad</button>
  </form>

  <details style="margin-top: 10px;">
    <summary style="cursor: pointer; font-weight: bold;">🗂️ Původní PDF</summary>
    <iframe src="<?= htmlspecialchars($pdfPreviewPath) ?>" style="width:100%; height:400px; border: 1px solid #aaa; margin-top:10px;"></iframe>
  </details>
<?php endif; ?>

</body>
</html>
