<?php
require_once 'db.php';
require_once 'session.php';

// Handle table deletion BEFORE any output
if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['delete_table'])) {
    \$conn = new mysqli(\$host, \$user, \$password, \$database);
    \$conn->set_charset("utf8mb4");

    if (\$conn->connect_error) {
        die("Connection failed: " . \$conn->connect_error);
    }

    \$tableToDelete = \$conn->real_escape_string(\$_POST['delete_table']);
    \$tables = [];
    \$result = \$conn->query("SHOW TABLES");
    while (\$row = \$result->fetch_array()) {
        \$tables[] = \$row[0];
    }

    if (in_array(\$tableToDelete, \$tables) && !in_array(\$tableToDelete, ['difficult_words', 'mastered_words'])) {
        \$conn->query("DROP TABLE `\$tableToDelete`");
        \$audioPath = "cache/\$tableToDelete.mp3";
        if (file_exists(\$audioPath)) {
            unlink(\$audioPath);
        }
    }

    \$conn->close();
    header("Location: " . \$_SERVER['PHP_SELF']);
    exit;
}

function getUserFoldersAndTables(\$conn, \$username) {
    \$allTables = [];
    \$result = \$conn->query("SHOW TABLES");
    while (\$row = \$result->fetch_array()) {
        \$table = \$row[0];
        if (stripos(\$table, \$username . '_') === 0) {
            \$suffix = substr(\$table, strlen(\$username) + 1);
            \$suffix = preg_replace('/_+/', '_', \$suffix);
            \$parts = explode('_', \$suffix, 2);
            if (count(\$parts) === 2 && trim(\$parts[0]) !== '') {
                \$folder = \$parts[0];
                \$file = \$parts[1];
            } else {
                \$folder = 'Uncategorized';
                \$file = \$suffix;
            }
            \$allTables[\$folder][] = [
                'table_name' => \$table,
                'display_name' => \$file
            ];
        }
    }
    return \$allTables;
}

\$username = strtolower(\$_SESSION['username'] ?? '');

\$conn->set_charset("utf8mb4");
\$folders = getUserFoldersAndTables(\$conn, \$username);

\$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
\$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

\$selectedFullTable = \$_POST['table'] ?? \$_GET['table'] ?? '';

\$column1 = '';
\$column2 = '';
\$heading1 = '';
\$heading2 = '';

\$res = false;
if (!empty(\$selectedFullTable)) {
    \$res = \$conn->query("SELECT * FROM `\$selectedFullTable`");
    if (\$res !== false) {
        \$columns = \$res->fetch_fields();

        if (\$selectedFullTable === "difficult_words") {
            \$column1 = "source_word";
            \$column2 = "target_word";
            \$heading1 = "Czech";
            \$heading2 = "Foreign";
        } else {
            \$column1 = \$columns[0]->name ?? '';
            \$column2 = \$columns[1]->name ?? '';
            \$heading1 = \$column1;
            \$heading2 = \$column2;
        }

        \$_SESSION['table'] = \$selectedFullTable;
        \$_SESSION['col1'] = \$column1;
        \$_SESSION['col2'] = \$column2;
    }
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Manage Tables</title>";
include 'styling.php';
echo "<style>
.folder { cursor: pointer; margin: 5px 0; color: goldenrod; font-weight: bold; }
.subtable { margin-left: 20px; display: none; }
.subtable span { cursor: pointer; display: block; margin: 2px 0; }
.subtable span:hover { background-color: #eef; }
textarea {
    width: 100%;
    min-height: 1.5em;
    resize: none;
    overflow: hidden;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 1em;
}
</style>";
echo "</head><body>";

echo "<div style='text-align: center; margin-bottom: 20px;'>";
echo "<a href='upload.php'><button>⬆ Upload</button></a> ";
echo "<a href='generate_mp3_google_ssml.php'><button>🎧 Create MP3</button></a> ";
echo "<a href='create_table.php'><button>🏗Create Table</button></a> ";
echo "<a href='edit.php'><button>🖋 Edit</button></a> ";
echo "</div>";

echo "<div class='content'>";
echo "👋 Logged in as " . htmlspecialchars(\$username) . " | <a href='logout.php'>Logout</a><br><br>";

echo "<form method='POST' action='' id='tableActionForm'>";
echo "<label>Select a table:</label><br>";
echo "<div class='directory-panel'><div id='folder-view'>";

foreach (\$folders as \$folder => \$tableList) {
    \$safeFolderId = htmlspecialchars(strtolower(\$folder));
    \$displayFolderName = ucfirst(\$folder);

    echo "<details><summary class='folder' onclick=\"toggleFolder('\$safeFolderId')\">📁 " . htmlspecialchars(\$displayFolderName) . "</summary>";
    echo "<div class='subtable' id='sub_\$safeFolderId'>";
    foreach (\$tableList as \$entry) {
        \$fullTable = \$entry['table_name'];
        \$display = \$entry['display_name'];
        echo "<span onclick=\"selectTable('\$fullTable')\">📄 " . htmlspecialchars(\$display) . "</span>";
    }
    echo "</div></details>";
}

echo "</div></div>";
echo "<input type='hidden' name='table' id='selectedTableInput' value='" . htmlspecialchars(\$selectedFullTable) . "'>";
echo "<input type='hidden' name='col1' value='" . htmlspecialchars(\$column1) . "'>";
echo "<input type='hidden' name='col2' value='" . htmlspecialchars(\$column2) . "'>";
echo "</form><br><br>";

if (!empty(\$selectedFullTable) && \$res !== false) {
    echo "<h3>Selected Table: " . htmlspecialchars(\$selectedFullTable) . "</h3>";

    \$isSharedTable = in_array(\$selectedFullTable, ['difficult_words', 'mastered_words']);

    \$audioFile = "cache/\$selectedFullTable.mp3";
    if (file_exists(\$audioFile)) {
        echo "<audio controls src='\$audioFile'></audio><br>";
        echo "<a href='\$audioFile' download class='button'>Download MP3</a><br><br>";
    } else {
        echo "<em>No audio generated yet for this table.</em><br><br>";
    }

    if (!\$isSharedTable) {
        echo "<form method='POST' action='update_table.php'>";
        echo "<input type='hidden' name='table' value='" . htmlspecialchars(\$selectedFullTable) . "'>";
        echo "<input type='hidden' name='col1' value='" . htmlspecialchars(\$column1) . "'>";
        echo "<input type='hidden' name='col2' value='" . htmlspecialchars(\$column2) . "'>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars(\$heading1) . "</th><th>" . htmlspecialchars(\$heading2) . "</th><th>Action</th></tr>";
        \$res->data_seek(0);
        \$i = 0;
        if (\$res->num_rows === 0) {
            echo "<tr><td colspan='3'><em>This table is empty. Add your first entry below.</em></td></tr>";
        }
        while (\$row = \$res->fetch_assoc()) {
            echo "<tr>";
            echo "<td><textarea name='rows[\$i][col1]' oninput='autoResize(this)'>" . htmlspecialchars(\$row[\$column1]) . "</textarea></td>";
            echo "<td><textarea name='rows[\$i][col2]' oninput='autoResize(this)'>" . htmlspecialchars(\$row[\$column2]) . "</textarea></td>";
            echo "<td><input type='checkbox' name='rows[\$i][delete]'> Delete</td>";
            echo "<input type='hidden' name='rows[\$i][orig_col1]' value='" . htmlspecialchars(\$row[\$column1]) . "'>";
            echo "<input type='hidden' name='rows[\$i][orig_col2]' value='" . htmlspecialchars(\$row[\$column2]) . "'>";
            echo "</tr>";
            \$i++;
        }

        echo '<tr><td><textarea name="new_row[col1]" placeholder="New ' . htmlspecialchars(\$heading1) . '" oninput="autoResize(this)"></textarea></td>
              <td><textarea name="new_row[col2]" placeholder="New ' . htmlspecialchars(\$heading2) . '" oninput="autoResize(this)"></textarea></td><td></td></tr>';

        echo "</table><br><button type='submit'>💾 Save Changes</button></form><br>";

        if (!in_array(\$selectedFullTable, ['difficult_words', 'mastered_words', 'users'])) {
            echo "<form method='POST' action='' onsubmit=\"return confirm('Really delete the table: \$selectedFullTable?');\">";
            echo "<input type='hidden' name='delete_table' value='" . htmlspecialchars(\$selectedFullTable) . "'>";
            echo "<button type='submit' class='delete-button'>🗑️ Delete This Table</button>";
            echo "</form><br>";
        }

    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars(\$heading1) . "</th><th>" . htmlspecialchars(\$heading2) . "</th></tr>";
        \$res->data_seek(0);
        while (\$row = \$res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars(\$row[\$column1]) . "</td>";
            echo "<td>" . htmlspecialchars(\$row[\$column2]) . "</td>";
            echo "</tr>";
        }
        echo "</table><br><em>This table is read-only.</em><br><br>";
    }
}

echo "</div>";
?>
<script>
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.overflow = 'hidden';
    textarea.style.height = textarea.scrollHeight + 'px';
}

function toggleFolder(folder) {
    const el = document.getElementById("sub_" + folder);
    if (el) {
        el.style.display = (el.style.display === "block") ? "none" : "block";
    }
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
