<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Safe test to confirm file started
use Smalot\PdfParser\Parser;
echo "<!-- PDF Scan Start -->";



// Try-catch to catch fatal errors
try {
    require_once 'pdfparser/alt_autoload.php';
    echo "<!-- PDF Parser loaded -->";
} catch (Throwable $e) {
    echo "❌ PDF parser load error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>


<?php
require_once 'session.php';
include 'styling.php';
require_once 'pdfparser/alt_autoload.php';

$extractedText = '';
$error = '';
$pdfPreviewPath = '';
$defaultTableName = 'pdf_imported_' . date('Ymd_His');
$uploadedPdf = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "❌ Upload failed.";
    } else {
        $uploadDir = __DIR__ . '/uploads/';
        $filename = 'upload_' . time() . '.pdf';
        $fullPath = $uploadDir . $filename;
        $webPath = 'uploads/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $error = "❌ Failed to save uploaded file.";
        } else {
            $pdfPreviewPath = $webPath;
            $uploadedPdf = $fullPath;

            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($fullPath);
                $text = $pdf->getText();

                if (!$text || trim($text) === '') {
                    $error = "❌ No text extracted from the PDF.";
                } else {
                    $text = preg_replace('/\s+/', ' ', $text);
                    $sentences = preg_split('/(?<=[.?!])\s+/', $text);
                    $lines = array_filter(array_map('trim', $sentences));
                    $lines = array_slice($lines, 0, 100);
                    $extractedText = implode("\n", $lines);
                }
            } catch (Exception $e) {
                $error = "❌ PDF parsing error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PDF Scan and Translate</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    form { max-width: 800px; margin: auto; }
    input, select, button, textarea {
      width: 100%;
      box-sizing: border-box;
      margin-bottom: 15px;
      padding: 10px;
      font-size: 1em;
    }
    textarea { height: 300px; resize: vertical; }
    iframe { width: 100%; height: 500px; border: 1px solid #aaa; }
    button {
      background-color: #4CAF50;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover { background-color: #45a049; }
    @media (max-width: 600px) {
      input, select, button, textarea { font-size: 1em; }
      iframe { height: 300px; }
    }
  </style>
  <script>
    function updateLabels() {
      const sourceSelect = document.getElementById("sourceLang");
      const targetSelect = document.getElementById("targetLang");

      const sourceLabel = sourceSelect.options[sourceSelect.selectedIndex].text;
      const targetLabel = targetSelect.options[targetSelect.selectedIndex].text;

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
        alert("Nothing to clean.");
        return;
      }

      textArea.value = "⏳ Cleaning with AI...";
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
          alert("AI cleanup failed.");
          textArea.value = originalText;
        }
      })
      .catch(err => {
        console.error(err);
        alert("Error connecting to AI cleaner.");
        textArea.value = originalText;
      });
    }

    window.addEventListener('DOMContentLoaded', updateLabels);
  </script>
</head>
<body>

<!-- Login info -->
<?php echo "<div class='content'>";
echo "👋 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>"; ?>

<h2 style="text-align:center;">📄 Scan PDF → Review → Translate</h2>

<?php if ($error): ?>
  <p style="color: red; text-align:center;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if (!$extractedText): ?>
  <form method="POST" enctype="multipart/form-data">
    <label>Select PDF File:
      <input type="file" name="pdf_file" accept=".pdf" required>
    </label>
    <button type="submit">📤 Extract Text</button>

    <label>using: https://github.com/smalot/pdfparser</label><br><br>

  </form>
<?php else: ?>
  <form method="POST" action="translator.php" onsubmit="return validateLangSelection(event)">
    <label>New Table Name:
      <input type="text" name="new_table_name" value="<?php echo htmlspecialchars($defaultTableName); ?>" required>
    </label>

    <label>Source Language:
      <select name="sourceLang" id="sourceLang" onchange="updateLabels()" required>
        <option value="" disabled selected>Select source language</option>
        <!-- <option value="auto">Auto Detect</option> -->
        <option value="en">English</option>
        <option value="de">German</option>
        <option value="fr">French</option>
        <option value="it">Italian</option>
      </select>
    </label>

    <label>Target Language:
      <select name="targetLang" id="targetLang" onchange="updateLabels()" required>
        <option value="" disabled selected>Select target language</option>
        <option value="cs">Czech</option>
        <option value="en">English</option>
        <option value="de">German</option>
        <option value="fr">French</option>
        <option value="it">Italian</option>
      </select>
    </label>

    <input type="hidden" name="source_lang_label" id="sourceLabel" value="">
    <input type="hidden" name="target_lang_label" id="targetLabel" value="">
    <input type="hidden" name="delete_pdf_path" value="<?php echo htmlspecialchars($uploadedPdf); ?>">

    <label>Font Size:
      <select id="fontSizeSelect" onchange="updateFontSize()">
        <option value="14px">Small</option>
        <option value="18px" selected>Medium</option>
        <option value="24px">Large</option>
      </select>
    </label>

    <label>Spellcheck Language:
      <select id="spellLangSelect" onchange="updateSpellLang()">
        <option value="">Disable</option>
        <option value="en">English</option>
        <option value="de">German</option>
        <option value="cs">Czech</option>
        <option value="fr">French</option>
        <option value="it">Italian</option>
      </select>
    </label>

    <label>Review or edit extracted text:
      <textarea id="textArea" name="text_lines" spellcheck="false"><?php echo htmlspecialchars($extractedText); ?></textarea>
    </label>

    <button type="button" onclick="cleanWithAI()">🧠 Clean Text with AI</button>
    <button type="submit">🌍 Translate</button>
  </form>

  <h3 style="text-align:center;">🔍 PDF Preview</h3>
  <iframe src="<?php echo htmlspecialchars($pdfPreviewPath); ?>"></iframe>
<?php endif; ?>

</body>
</html>
