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
// echo "<a href='generate_mp3.php'><button>🎿 Generate Audio</button></a> "; 
echo "<a href='generate_mp3_google_ssml.php'>🎧 Generate MP3</a> ";
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
    echo "<div class='folder' onclick=\"toggleFolder('$folder')\">📁 $folder</div>";
    echo "<div class='subtable' id='sub_$folder'>";
    foreach ($tableList as $entry) {
        $fullTable = $entry['table_name'];
        $display = $entry['display_name'];
        echo "<span onclick=\"selectTable('$fullTable')\">📄 $display</span>";
    }
    echo "</div>";
}

echo "</div></div>";  // Close folder-view and directory-panel

echo "<input type='hidden' name='table' id='selectedTableInput' value='" . htmlspecialchars($selectedFullTable) . "'>";
echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";
echo "</form><br><br>";

echo "<div>"; 
echo "<h3>Selected Table: <span class='selected-table'>" . htmlspecialchars($selectedFullTable) . "</span></h3>";

$audioFile = "cache/$selectedFullTable.mp3";
if (file_exists($audioFile)) {
    echo "<audio controls src='$audioFile'></audio><br>";
    echo "<a href='$audioFile' download class='button'>Download MP3</a><br><br>";
} else {
    echo "<em>No audio generated yet for this table.</em><br><br>";
}

if (!empty($selectedFullTable) && $res && $res->num_rows > 0) {
    echo "<form method='POST' action='update_table.php'>";
    echo "<input type='hidden' name='table' value='" . htmlspecialchars($selectedFullTable) . "'>";
    echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
    echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th><th>Action</th></tr>";
    $res->data_seek(0);
    $rowIndex = 0;
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<input type='hidden' name='rows[$rowIndex][orig_col1]' value='" . htmlspecialchars($row[$column1]) . "'>";
        echo "<input type='hidden' name='rows[$rowIndex][orig_col2]' value='" . htmlspecialchars($row[$column2]) . "'>";
        echo "<td><textarea name='rows[$rowIndex][col1]' oninput='autoResize(this)' style='min-height: 40px; width: 100%; resize: none;'>" . htmlspecialchars($row[$column1]) . "</textarea></td>";
        echo "<td><textarea name='rows[$rowIndex][col2]' oninput='autoResize(this)' style='min-height: 40px; width: 100%; resize: none;'>" . htmlspecialchars($row[$column2]) . "</textarea></td>";
        echo "<td><input type='checkbox' name='rows[$rowIndex][delete]'> Delete</td>";
        $rowIndex++;
        echo "</tr>";
    }
    echo "<tr>";
    echo "<td><textarea name='new_row[col1]' oninput='autoResize(this)' placeholder='New " . htmlspecialchars($heading1) . "' style='min-height: 40px; width: 100%; resize: none;'></textarea></td>";
    echo "<td><textarea name='new_row[col2]' oninput='autoResize(this)' placeholder='New " . htmlspecialchars($heading2) . "' style='min-height: 40px; width: 100%; resize: none;'></textarea></td>";
    echo "<td><em>Add New</em></td>";
    echo "</tr>";
    echo "</table><br>";
    echo "<button type='submit'>💾 Save Changes</button>";
    echo "</form><br>";
}

$protectedTables = ['difficult_words', 'mastered_words', 'users', 'example_table'];
if (in_array($selectedFullTable, $protectedTables)) {
    echo "<p style='color: red;'><strong>⚠️ The '$selectedFullTable' table is protected and cannot be deleted.</strong></p><br><br>";
} elseif (!empty($selectedFullTable)) {
    echo "<form method='post' onsubmit=\"return confirm('Are you sure you want to permanently delete the table " . htmlspecialchars($selectedFullTable) . "? This action cannot be undone.');\">";
    echo "<input type='hidden' name='delete_table' value='" . htmlspecialchars($selectedFullTable) . "'>";
    echo "<button type='submit' class='delete-button'>🗑️ Delete This Table</button>";
    echo "</form><br><br>";
}

echo <<<HTML
<h2>Upload New CSV Table</h2>
<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
    <label>Table Name: <input type="text" name="new_table_name" required></label><br><br>
    <label>Naming convention: FolderName_FileName</label><br><br> 
    <label>CSV files must have Utf8mb4 (Czech) encoding without BOM</label><br><br> 
    <label>The first column must have a "Czech" header and the other one German, English or other language.</label><br><br>
    <label>CSV File: <input type="file" name="csv_file" accept=".csv" required></label><br><br>
    <button type="submit">Upload</button>
</form>
HTML;

echo "</div>";
echo "</div>";

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
</body></html>
