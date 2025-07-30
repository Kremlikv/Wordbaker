<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// session_start();

require_once 'db.php';
require_once 'session.php';

// Handle table deletion BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $tableToDelete = $conn->real_escape_string($_POST['delete_table']);

    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    if (in_array($tableToDelete, $tables)) {
        $conn->query("DROP TABLE `$tableToDelete`");
        $audioPath = "cache/$tableToDelete.mp3";
        if (file_exists($audioPath)) {
            unlink($audioPath);
        }
    }

    $conn->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get visible tables
function getTables($conn) {
    $hiddenTables = ['users'];  // Tables to hide from dropdown
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        if (!in_array($row[0], $hiddenTables)) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

$tables = getTables($conn);
$selectedTable = $_POST['table'] ?? ($tables[0] ?? '');
$column1 = '';
$column2 = '';
$heading1 = '';
$heading2 = '';

if (!empty($selectedTable)) {
    $res = $conn->query("SELECT * FROM `$selectedTable`");
    if ($res && $res->num_rows > 0) {
        $columns = $res->fetch_fields();

        if ($selectedTable === "difficult_words") {
            $column1 = "source_word";
            $column2 = "target_word";
            $heading1 = "Czech";
            $heading2 = "Foreign";
        } else {
            $column1 = $columns[0]->name ?? '';
            $column2 = $columns[1]->name ?? '';
            $heading1 = $column1;
            $heading2 = $column2;
        }

        // Store selections in session
        $_SESSION['table'] = $selectedTable;
        $_SESSION['col1'] = $column1;
        $_SESSION['col2'] = $column2;
    }
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Manage Tables</title>";
include 'styling.php';
echo "</head><body>";

// MENU BAR
echo "<div style='text-align: center; margin-bottom: 20px;'>";
echo "<a href='flashcards.php'><button>📘 Study Flashcards</button></a> ";
echo "<a href='generate_mp3.php'><button>🎿 Generate Audio</button></a> ";
echo "<a href='review_difficult.php'><button>🧠 Difficult Words</button></a> ";
echo "<a href='mastered.php'><button>🌟 Mastered</button></a> ";
echo "<a href='translator.php'><button>🌐 Translate</button></a> ";
echo "<a href='pdf_scan.php'><button>📄 PDF-to-text</button></a>";
echo "</div>";

// MAIN CONTENT
echo "<div class='content'>";
echo "👋 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a><br><br>";

echo "<form method='POST' action='' id='tableActionForm'>";
echo "<label for='table'>Select a table:</label> ";
echo "<select name='table' id='table'>";
foreach ($tables as $table) {
    $selected = ($table === $selectedTable) ? 'selected' : '';
    echo "<option value='$table' $selected>$table</option>";
}
echo "</select> ";

echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";

echo "<button type='submit' title='Load Table' style='font-size: 1.5em; background-color: #ccc; color: black; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer;'>⬆️</button>";
echo "</form><br><br>";

echo "<h3>Selected Table: <span class='selected-table'>" . htmlspecialchars($selectedTable) . "</span></h3>";

// Audio preview
$audioFile = "cache/$selectedTable.mp3";
if (file_exists($audioFile)) {
    echo "<audio controls src='$audioFile'></audio><br>";
    echo "<a href='$audioFile' download class='button'>Download MP3</a><br><br>";
} else {
    echo "<em>No audio generated yet for this table.</em><br><br>";
}

// Show table
if (!empty($selectedTable) && $res && $res->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th></tr>";
    $res->data_seek(0);
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row[$column1]) . "</td>";
        echo "<td>" . htmlspecialchars($row[$column2]) . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} elseif (!empty($selectedTable)) {
    echo "<p>No data found in the table.</p>";
}

// Protect important tables
$protectedTables = ['difficult_words', 'mastered_words', 'users', 'example_table'];
if (in_array($selectedTable, $protectedTables)) {
    echo "<p style='color: red;'><strong>⚠️ The '$selectedTable' table is protected and cannot be deleted.</strong></p><br><br>";
} else {
    echo "<form method='post' onsubmit=\"return confirm('Are you sure you want to permanently delete the table " . htmlspecialchars($selectedTable) . "? This action cannot be undone.');\">";
    echo "<input type='hidden' name='delete_table' value='" . htmlspecialchars($selectedTable) . "'>";
    echo "<button type='submit' class='delete-button'>🗑️ Delete This Table</button>";
    echo "</form><br><br>";
}

// Upload CSV
echo <<<HTML
<h2>Upload New CSV Table</h2>
<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
    <label>Table Name: <input type="text" name="new_table_name" required></label><br><br>
    <label>CSV File: <input type="file" name="csv_file" accept=".csv" required></label><br><br>
    <button type="submit">Upload</button>
</form>
HTML;

echo "</div></body></html>";
?>
