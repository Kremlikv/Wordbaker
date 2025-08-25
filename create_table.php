<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$username = strtolower($_SESSION['username'] ?? '');

$conn->set_charset("utf8mb4");

// --- Helpers ---
function sanitize_table_name(string $raw): string {
    $t = strtolower($raw);
    $t = preg_replace('/[^a-z0-9_]+/', '_', $t);
    return trim($t, '_');
}

function sanitize_col_name(string $raw): string {
    $c = preg_replace('/[^a-z0-9_]+/i', '_', $raw);
    return trim($c, '_');
}

// Split text into card strings using either newline or semicolon as row separators,
// but only split on semicolons that are OUTSIDE of quotes (CSV-style quotes).
function split_cards(string $raw, string $row_sep): array {
    $raw = str_replace("\r\n", "\n", $raw); // normalize
    if ($row_sep === 'newline') {
        $parts = explode("\n", $raw);
    } else { // 'semicolon'
        $parts = [];
        $buf = '';
        $inQuote = false;
        $len = mb_strlen($raw, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($raw, $i, 1, 'UTF-8');

            if ($ch === '"') {
                // handle doubled quotes inside quoted section ("")
                $next = ($i + 1 < $len) ? mb_substr($raw, $i + 1, 1, 'UTF-8') : '';
                if ($inQuote && $next === '"') { $buf .= '"'; $i++; continue; }
                $inQuote = !$inQuote;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';' && !$inQuote) {
                $parts[] = $buf;
                $buf = '';
            } else {
                $buf .= $ch;
            }
        }
        if (trim($buf) !== '') $parts[] = $buf;
    }

    $cards = [];
    foreach ($parts as $p) {
        $line = trim($p);
        if ($line === '') continue;
        // tolerate trailing semicolons in either mode
        $line = rtrim($line, "; \t");
        if ($line !== '') $cards[] = $line;
    }
    return $cards;
}

// Parse a single "card string" into [term, definition] using CSV parsing with chosen delimiter.
function parse_card(string $card, string $field_sep): ?array {
    $fields = str_getcsv($card, $field_sep, '"');
    if (count($fields) < 2) return null;
    $term = trim($fields[0]);
    $def  = trim($fields[1]);
    if ($term === '' || $def === '') return null;
    return [$term, $def];
}

$message = null;
$details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['folder'], $_POST['filename'], $_POST['second_language'])) {

    $folder         = trim($_POST['folder']);
    $filename       = trim($_POST['filename']);
    $secondLanguage = trim($_POST['second_language']);

    // Import options (optional)
    $doImport  = isset($_POST['do_import']);
    $bulk      = $_POST['bulk'] ?? '';
    $fieldSep  = ($_POST['field_sep'] ?? 'tab') === 'comma' ? ',' : "\t";
    $rowSep    = $_POST['row_sep'] ?? 'newline';     // 'newline' | 'semicolon'
    $czSide    = $_POST['cz_side'] ?? 'left';        // 'left' | 'right'

    if (!$folder || !$filename || !$secondLanguage) {
        $message = "All fields are required.";
    } else {
        // Build table + columns
        $tableBase = $username . "_" . $folder . "_" . $filename;
        $table = sanitize_table_name($tableBase);
        $col1  = 'Czech';
        $col2  = sanitize_col_name($secondLanguage);

        // Existence check
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        if ($result && $result->num_rows > 0) {
            $message = "Table '$table' already exists.";
        } else {
            // Create table
            $sql = "CREATE TABLE `$table` (
                        `$col1` VARCHAR(255) NOT NULL,
                        `$col2` VARCHAR(255) NOT NULL
                    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci";

            if ($conn->query($sql)) {
                $message = "✔ Table '$table' created successfully.";

                // Optional: Import data if requested and not empty
                if ($doImport && trim($bulk) !== '') {
                    $cards = split_cards($bulk, $rowSep);
                    $pairs = [];
                    foreach ($cards as $c) {
                        $p = parse_card($c, $fieldSep);
                        if ($p) $pairs[] = $p;
                    }

                    if (count($pairs) > 0) {
                        $stmt = $conn->prepare("INSERT INTO `$table` (`$col1`, `$col2`) VALUES (?, ?)");
                        if ($stmt) {
                            $imported = 0;
                            foreach ($pairs as [$term, $def]) {
                                // Map Czech side as chosen
                                $cz = ($czSide === 'left') ? $term : $def;
                                $fx = ($czSide === 'left') ? $def  : $term;
                                $stmt->bind_param('ss', $cz, $fx);
                                if ($stmt->execute()) $imported++;
                            }
                            $stmt->close();
                            $details = "Imported $imported rows into '$table'.";
                        } else {
                            $details = "Import skipped (prepare failed): " . htmlspecialchars($conn->error);
                        }
                    } else {
                        $details = "Nothing to import (no valid pairs found).";
                    }
                }
            } else {
                $message = "Error creating table: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vytvořit novou tabulku</title>
    <style>
        body { font-family: sans-serif; margin: 0; }
        label { display: block; margin-top: 1em; }
        input[type="text"] { width: 320px; }
        textarea { width: 100%; height: 220px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        button { margin-top: 1em; padding: 10px 16px; }
        .message { margin-top: 1em; color: darkgreen; }
        .error { color: red; }
        .content { padding: 1rem; max-width: 900px; margin: 0 auto; }
        .row { display: flex; flex-wrap: wrap; gap: 16px; }
        .row > div { flex: 1 1 260px; }
        small { color: #555; }
        fieldset { border: 1px solid #ddd; padding: 12px; margin-top: 16px; }
        legend { padding: 0 6px; color: #333; }
    </style>
</head>
<body>
    <div class='content'>
        <p>👤Přihlášený uživatel: <strong><?= htmlspecialchars($_SESSION['username'] ?? '') ?></strong> | <a href='logout.php'>Odhlásit</a></p>

        <h2>Vytvořit novou tabulku</h2>
        <form method="POST">
            <label>Jméno adresáře (např. <em>Zvířata</em>):</label>
            <input type="text" name="folder" required>

            <label>Jméno souboru (např. <em>Vodní</em>):</label>
            <input type="text" name="filename" required>

            <label>Druhý jazykový sloupec (např. <em>German</em>, <em>English</em>, atd.):</label>
            <input type="text" name="second_language" required>

            <fieldset>
                <legend>Volitelné: Importovat karty (vystřihnout a vlepit)</legend>

                <label><input type="checkbox" name="do_import" id="do_import"> Importovat vystřižený text do nové tabulky</label>

                <label for="bulk">Vlepit kartičky (Termín + Definice)</label>
                <textarea id="bulk" name="bulk" placeholder="děkuji<TAB>thank you
kočka<TAB>cat
&quot;chléb, rohlík&quot;<TAB>bread

nebo (rows by semicolon):
děkuji, thank you; kočka, cat; &quot;chléb, rohlík&quot;, bread;"></textarea>

                <div class="row">
                    <div>
                        <label>Mezi termínem a jeho definicí</label>
                        <label><input type="radio" name="field_sep" value="tab" checked> Tabulátor</label>
                        <label><input type="radio" name="field_sep" value="comma"> Čárka</label>
                        <small>Podporované uvozovky: <code>"kočka, malá"</code> v jednom poli.</small>
                    </div>
                    <div>
                        <label>Mezi řádky</label>
                        <label><input type="radio" name="row_sep" value="newline" checked> New line</label>
                        <label><input type="radio" name="row_sep" value="semicolon"> Středník (;)</label>
                        <small>Když zvolíte středník, může být více karet na jednom řádku.</small>
                    </div>
                    <div>
                        <label>Na které straně je čeština?</label>
                        <select name="cz_side">
                            <option value="left" selected>Levá (otázka)</option>
                            <option value="right">Pravá (odpověď)</option>
                        </select>
                        <small>Levý sloupec musí být <b>čeština</b> , aby se text zapsal do souboru správně.</small>
                    </div>
                </div>
            </fieldset>

            <button type="submit">➕ Vytvořit tabulku (a importovat text, je-li to zaškrtnuto)</button>
        </form>

        <?php if (isset($message)): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <?php if (isset($details)): ?>
            <p class="message"><?= htmlspecialchars($details) ?></p>
        <?php endif; ?>

        <br>
        <a href="upload.php">⬅ Zpět k nahrávání</a>
    </div>

<script>
// Convenience: if user typed "\t" literally, convert to a real tab on submit.
document.querySelector('form').addEventListener('submit', function(){
  const ta = document.getElementById('bulk');
  if (ta) ta.value = ta.value.replace(/\\t/g, "\t");
});
</script>
</body>
</html>
