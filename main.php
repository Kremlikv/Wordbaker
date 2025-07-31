<?php
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

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (strpos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1); // Remove username_
            $parts = explode('_', $suffix, 2); // folder_file
            if (count($parts) === 2) {
                $folder = $parts[0];
                $file = $parts[1];
                $allTables[$folder][] = [
                    'table_name' => $table,
                    'display_name' => $file
                ];
            }
        }
    }
    return $allTables;
}

$username = $_SESSION['username'] ?? '';
$folders = getUserFoldersAndTables($conn, $username);
$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? '';

$column1 = '';
$column2 = '';
$heading1 = '';
$heading2 = '';

if (!empty($selectedFullTable)) {
    $res = $conn->query("SELECT * FROM `$selectedFullTable`");
    if ($res && $res->num_rows > 0) {
        $columns = $res->fetch_fields();

        if ($selectedFullTable === "difficult_words") {
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

        $_SESSION['table'] = $selectedFullTable;
        $_SESSION['col1'] = $column1;
        $_SESSION['col2'] = $column2;
    }
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Manage Tables</title>";
include 'styling.php';
echo "<style>
.folder { cursor: pointer; margin: 5px 0; color: goldenrod; font-weight: bold; }
.subtable { margin-left: 20px; display: none; }
.subtable span { cursor: pointer; display: block; margin: 2px 0; }
.subtable span:hover { background-color: #eef; }
</style>";
echo "</head><body>"; 

// MENU BAR
echo "<div style='text-align: center; margin-bottom: 20px;'>";
echo "<a href='flashcards.php'><button>📘 Study Flashcards</button></a> ";
echo "<a href='generate_mp3_google_ssml.php'><button>🎧 Generate MP3</a> ";
echo "<a href='review_difficult.php'><button>🧠 Difficult Words</button></a> ";
echo "<a href='mastered.php'><button>🌟 Mastered</button></a> ";
echo "<a href='translator.php'><button>🌐 Translate</button></a> ";
echo "<a href='pdf_scan.php'><button>📄 PDF-to-text</button></a>";
echo "</div>";

// MAIN CONTENT
echo "<div class='content'>";
echo "👋 Logged in as " . htmlspecialchars($username) . " | <a href='logout.php'>Logout</a><br><br>";

echo "<form method='POST' action='' id='tableActionForm'>";
echo "<label>Select a table:</label><br>";
echo "<div class='directory-panel'><div id='folder-view'>";

foreach ($folders as $folder => $tableList) {
    echo "<details><summary class='folder'>📁 " . htmlspecialchars($folder) . "</summary><div class='subtable' id='sub_$folder'>";
    foreach ($tableList as $entry) {
        $fullTable = $entry['table_name'];
        $display = $entry['display_name'];
        echo "<span onclick=\"selectTable('$fullTable')\">📄 " . htmlspecialchars($display) . "</span>";
    }
    echo "</div></details>";
}

echo "</div></div>";

echo "<input type='hidden' name='table' id='selectedTableInput' value='" . htmlspecialchars($selectedFullTable) . "'>";
echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";
echo "</form><br><br>";

// Upload section
echo <<<HTML
<h2>📤 Upload</h2>
<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
    <label>Select Folder:</label>
    <select name="folder" required>
        <option value="">-- Choose Folder --</option>
HTML;
foreach ($folders as $folder => $tableList) {
    echo "<option value=\"" . htmlspecialchars($folder) . "\">" . htmlspecialchars(ucfirst($folder)) . "</option>";
}
echo <<<HTML
    </select><br><br>
    <label>Select CSV Files:</label>
    <input type="file" name="csv_files[]" accept=".csv" multiple required><br><br>
    <p style="font-size: 0.9em; color: gray;">
        ➤ Filenames will be used to create table names.<br>
        ➤ System will generate: <code>username_folder_filename</code><br>
        ➤ If a filename already starts with your username, it will be used as-is.<br>
        ➤ CSVs must have <strong>“Czech”</strong> as one of the headers and at least one other column.<br>
        ➤ Encoding must be UTF-8 without BOM.
    </p>
    <button type="submit">Upload Files</button>
</form>
HTML;

echo "</div></body></html>";
?>
<script>
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

function toggleFolder(folder) {
    const el = document.getElementById("sub_" + folder);
    el.style.display = (el.style.display === "block") ? "none" : "block";
}

function selectTable(fullTableName) {
    document.getElementById("selectedTableInput").value = fullTableName;
    document.getElementById("tableActionForm").submit();
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach(function (el) {
        autoResize(el);
    });
});
</script>
