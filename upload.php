<?php
require_once 'db.php';
require_once 'session.php';

$username = strtolower($_SESSION['username'] ?? '');

if (!$username) {
    header("Location: login.php");
    exit;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Nahrát CSV</title>";
include 'styling.php';
echo "</head><body>";

echo "<div class='content'>";
echo "👤 Přihlášený uživatel " . htmlspecialchars($username) . " | <a href='logout.php'>Odhlásit</a><br><br>";

if (!empty($_SESSION['uploaded_tables'])) {
    echo "<div style='color: green; font-weight: bold;'>✅ Úspěšně nahráno:</div><ul>";
    foreach ($_SESSION['uploaded_tables'] as $message) {
        echo "<li>📄 " . htmlspecialchars($message) . "</li>";
    }
    echo "</ul><br>";
    unset($_SESSION['uploaded_tables']);
}


if (!empty($_SESSION['uploaded_filename'])) {
    echo "<p style='color: green;'>✅ Soubor nahrán: " . htmlspecialchars($_SESSION['uploaded_filename']) . "</p>";
    unset($_SESSION['uploaded_filename']);
}

echo <<<HTML
<h2>📤 Nahrát slovníček</h2>

<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
  <label>Vyberte CSV soubory:</label>
  <input type="file" name="csv_files[]" accept=".csv" multiple required><br><br>

  <label><strong>Vyberte CSV soubor:</strong></label><br>
 
  <p style="font-size: 0.9em; color: gray;">
    ➤ Váš slovníček bude uložen jako <strong>[uživatel]_adresář_soubor</strong><br>
    ➤ CSV musí mít jeden sloupec v <strong>"češtině"</strong>  a jedne sloupec v cizím jazyce.<br>
    ➤ Nutné je české kódování znaků <strong>UTF-8</strong> bez značek BOM.<br>
    ➤ V názvech používejte jen písmena, číslice nebo podtržítko.
  </p>

  <button type="submit">📥 Nahrát</button>
  <br><br>

 <a href="foldername_filename.csv" download>
  <button type="button">Stáhněte si správně naformátovaný vzorový CSV soubor.</button>
</a>
</form>


</form>
HTML;

echo "</div></body></html>";
