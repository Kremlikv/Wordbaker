<?php
require_once 'db.php';
require_once 'session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $tableToDelete = $conn->real_escape_string($_POST['delete_table']);
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    if (in_array($tableToDelete, $tables) && !in_array($tableToDelete, ['difficult_words', 'mastered_words'])) {
        $conn->query("DROP TABLE `$tableToDelete`");
        $audioPath = "cache/$tableToDelete.mp3";
        if (file_exists($audioPath)) unlink($audioPath);
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
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts = explode('_', $suffix, 2);
            $folder = count($parts) === 2 && trim($parts[0]) !== '' ? $parts[0] : 'Uncategorized';
            $file = count($parts) === 2 ? $parts[1] : $suffix;
            $allTables[$folder][] = ['table_name' => $table, 'display_name' => $file];
        }
    }
    return $allTables;
}

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? '';
$column1 = '';
$column2 = '';
$heading1 = '';
$heading2 = '';

$res = false;
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
textarea {
    width: 100%;
    min-height: 1.5em;
    resize: none;
    overflow: hidden;
    font-family: inherit;
    font-size: 1em;
}
</style></head><body>";

echo "<div style='text-align: center; margin-bottom: 20px;'>";
echo "<a href='flashcards.php'><button>📘 Study Flashcards</button></a> ";
echo "<a href='generate_mp3_google_ssml.php'><button>🎧 Create MP3</button></a> ";
echo "<a href='review_difficult.php'><button>🧠 Difficult Words</button></a> ";
echo "<a href='mastered.php'><button>🌟 Mastered</button></a> ";
echo "<a href='translator.php'><button>🌐 Translate</button></a> ";
echo "<a href='pdf_scan.php'><button>📄 PDF-to-text</button></a>";
echo "<a href='generate_quiz_choices.php'><button>🎯 Make Quiz</button></a>";
echo "<a href='play_quiz.php'><button>🎯 Play Quiz</button></a>";
echo "</div>";

echo "<div class='content'>";
echo "👋 Logged in as " . htmlspecialchars($username) . " | <a href='logout.php'>Logout</a><br><br>";

echo "<form method='POST' action='' id='tableActionForm'>";
echo "<label>Select a table:</label><br>";
echo "<div class='directory-panel'><div id='folder-view'>";

foreach ($folders as $folder => $tableList) {
    $safeFolderId = htmlspecialchars(strtolower($folder));
    $displayFolderName = ucfirst($folder);
    echo "<details><summary class='folder'>📁 " . htmlspecialchars($displayFolderName) . "</summary>";
    echo "<div class='subtable' id='sub_$safeFolderId'>";
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

if (!empty($selectedFullTable) && $res && $res->num_rows > 0) {
    echo "<h3>Selected Table: " . htmlspecialchars($selectedFullTable) . "</h3>";
    $isSharedTable = in_array($selectedFullTable, ['difficult_words', 'mastered_words']);

    $audioFile = "cache/$selectedFullTable.mp3";
    if (file_exists($audioFile)) {
        echo "<audio controls src='$audioFile'></audio><br>";
        echo "<a href='$audioFile' download class='button'>Download MP3</a><br><br>";
    } else {
        echo "<em>No audio generated yet for this table.</em><br><br>";
    }

    if (!$isSharedTable) {
        echo "<form method='POST' action='update_table.php'>";
        echo "<input type='hidden' name='table' value='" . htmlspecialchars($selectedFullTable) . "'>";
        echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
        echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th><th>Action</th></tr>";
        $res->data_seek(0);
        $i = 0;
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            echo "<td><textarea name='rows[$i][col1]' oninput='autoResize(this)'>" . htmlspecialchars($row[$column1]) . "</textarea></td>";
            echo "<td><textarea name='rows[$i][col2]' oninput='autoResize(this)'>" . htmlspecialchars($row[$column2]) . "</textarea></td>";
            echo "<td><input type='checkbox' name='rows[$i][delete]'> Delete</td>";
            echo "<input type='hidden' name='rows[$i][orig_col1]' value='" . htmlspecialchars($row[$column1]) . "'>";
            echo "<input type='hidden' name='rows[$i][orig_col2]' value='" . htmlspecialchars($row[$column2]) . "'>";
            echo "</tr>";
            $i++;
        }

        echo <<<HTML
<tr>
  <td><textarea name="new_row[col1]" placeholder="New $heading1" oninput="autoResize(this)"></textarea></td>
  <td><textarea name="new_row[col2]" placeholder="New $heading2" id="foreignTextarea" oninput="autoResize(this)"></textarea></td>
  <td><button type="button" onclick="openTranslator()">🌐 Translate</button></td>
</tr>
HTML;

        echo "</table><br><button type='submit'>💾 Save Changes</button></form><br>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th></tr>";
        $res->data_seek(0);
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($row[$column1]) . "</td><td>" . htmlspecialchars($row[$column2]) . "</td></tr>";
        }
        echo "</table><br><em>This table is read-only.</em><br><br>";
    }
}

echo <<<HTML
<h2>📤 Upload</h2>
<form method="POST" action="upload_handler.php" enctype="multipart/form-data">
  <label>Select CSV Files:</label>
  <input type="file" name="csv_files[]" accept=".csv" multiple required><br><br>
  <p style="font-size: 0.9em; color: gray;">
    ➤ Recommended format: FolderName_FileName.csv <br>
    ➤ CSVs must have a <strong>“Czech”</strong> column and at least one other language column.<br>
    ➤ Encoding must be <strong>UTF-8</strong> without BOM.
  </p>
  <button type="submit">Upload Files</button>
</form>
</div>
</body></html>
<script>
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.overflow = 'hidden';
    textarea.style.height = textarea.scrollHeight + 'px';
}
function toggleFolder(folder) {
    const el = document.getElementById("sub_" + folder);
    if (el) el.style.display = (el.style.display === "block") ? "none" : "block";
}
function selectTable(fullTableName) {
    document.getElementById("selectedTableInput").value = fullTableName;
    document.getElementById("tableActionForm").submit();
}
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach(autoResize);
});

function openTranslator() {
    const text = document.getElementById("foreignTextarea").value;
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "translator.php";
    form.target = "_blank";

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "text_lines";
    input.value = text;
    form.appendChild(input);

    const sourceLang = "<?php echo strtolower($heading2); ?>";
    const source = document.createElement("input");
    source.type = "hidden";
    source.name = "sourceLang";
    source.value = sourceLang;
    form.appendChild(source);

    const target = document.createElement("input");
    target.type = "hidden";
    target.name = "targetLang";
    target.value = "cs";
    form.appendChild(target);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
