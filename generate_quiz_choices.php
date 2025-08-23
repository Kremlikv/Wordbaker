<?php
// generate_quiz_choices.php — MANUAL distractors version (no AI)

// Keeps: File explorer, folder system, image_url column, quiz table generation
// Removes: AI calls, daily usage tracking, candidate validation

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$conn->set_charset('utf8mb4');

// ====== Helpers ======
function quizTableExists($conn, $table) {
    $quizTable = (strpos($table, 'quiz_choices_') === 0) ? $table : "quiz_choices_" . $table;
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $res && $res->num_rows > 0;
}

// ====== PRE-EXPLORER LOGIC ======
$username = strtolower($_SESSION['username'] ?? '');

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query('SHOW TABLES');
    if ($result) {
        while ($row = $result->fetch_array()) {
            $table = $row[0];
            if (strpos($table, 'quiz_choices_') === 0) continue; // skip quiz tables
            if (stripos($table, $username . '_') === 0) {
                $suffix = substr($table, strlen($username) + 1);
                $suffix = preg_replace('/_+/', '_', $suffix);
                $parts = explode('_', $suffix, 2);
                if (count($parts) === 2 && trim($parts[0]) !== '') { $folder = $parts[0]; $file = $parts[1]; }
                else { $folder = 'Uncategorized'; $file = $suffix; }
                $allTables[$folder][] = [ 'table_name' => $table, 'display_name' => $file ];
            }
        }
    }
    return $allTables;
}

$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [ 'table' => $entry['table_name'], 'display' => $entry['display_name'] ];
    }
}

// Determine selected table
$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$selectedTable = is_string($selectedTable) ? trim($selectedTable) : '';

// Infer language labels
$autoSourceLang = '';
$autoTargetLang = '';
if ($selectedTable !== '') {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $autoSourceLang = ucfirst($cols[0]['Field']);
        $autoTargetLang = ucfirst($cols[1]['Field']);
    }
}

// ====== UI Header ======
echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate Quiz Choices (Manual)</h2>";
echo "<p style='text-align:center;'>Now you must manually fill in the wrong answers (distractors).</p>";

// ====== File Explorer ======
include 'file_explorer.php';

// ====== EDIT-FIRST WORKFLOW ======
$generatedTable = '';

if ($selectedTable !== '') {
    $stage = $_POST['stage'] ?? 'edit';

    if ($stage === 'generate' && !empty($_POST['items']) && is_array($_POST['items'])) {
        $editedRows = [];
        foreach ($_POST['items'] as $idx => $item) {
            $q   = trim($item['q'] ?? '');
            $c   = trim($item['c'] ?? '');
            $del = isset($item['del']) && $item['del'] === '1';
            if ($del || $q === '' || $c === '') continue;
            $editedRows[] = ['question' => $q, 'correct' => $c];
        }

        if (empty($editedRows)) {
            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>
                    Nothing to generate.
                  </div>";
            $stage = 'edit';
        } else {
            $quizTable = 'quiz_choices_' . $selectedTable;

            if (!quizTableExists($conn, $selectedTable)) {
                $conn->query("CREATE TABLE `$quizTable` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    question TEXT,
                    correct_answer TEXT,
                    wrong1 TEXT,
                    wrong2 TEXT,
                    wrong3 TEXT,
                    source_lang VARCHAR(50),
                    target_lang VARCHAR(50),
                    image_url TEXT
                )");
            }

            $stmt = $conn->prepare("INSERT INTO `$quizTable`
                (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang)
                VALUES (?, ?, '', '', '', ?, ?)");

            foreach ($editedRows as $r) {
                $q = $r['question'];
                $c = $r['correct'];
                $stmt->bind_param('ssss', $q, $c, $autoSourceLang, $autoTargetLang);
                $stmt->execute();
            }

            $stmt->close();
            $generatedTable = $quizTable;
        }
    }

    if ($stage === 'edit') {
        // Load source table
        $rows = [];
        $col1 = $col2 = '';
        $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
        if ($columnsRes && $columnsRes->num_rows >= 2) {
            $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
            $col1 = $cols[0]['Field'];
            $col2 = $cols[1]['Field'];
            $res2 = $conn->query("SELECT `$col1` AS q, `$col2` AS c FROM `$selectedTable`");
            while ($r = $res2->fetch_assoc()) {
                $q = trim($r['q']); $c = trim($r['c']);
                if ($q === '' || $c === '') continue;
                $rows[] = ['q' => $q, 'c' => $c];
            }
        }

        echo "<h3 style='text-align:center;'>Review & Edit: <code>".htmlspecialchars($selectedTable)."</code></h3>";
        echo "<form method='post' style='margin:10px 0;'>";
        echo "<input type='hidden' name='table' value='".htmlspecialchars($selectedTable, ENT_QUOTES)."'>";
        echo "<input type='hidden' name='stage' value='generate'>";

        echo "<div style='overflow-x:auto;'>";
        echo "<table border='1' style='width:100%; border-collapse:collapse;'>";
        $ansHeader = htmlspecialchars($autoTargetLang ?: 'Answer');
        echo "<tr><th>Delete</th><th>Czech</th><th>{$ansHeader}</th></tr>";

        foreach ($rows as $i => $r) {
            $q = htmlspecialchars($r['q'], ENT_QUOTES);
            $c = htmlspecialchars($r['c'], ENT_QUOTES);
            echo "<tr>
                    <td style='text-align:center;'>
                        <input type='checkbox' name='items[$i][del]' value='1'>
                    </td>
                    <td>
                        <input type='text' name='items[$i][q]' value='$q' style='width:100%; padding:6px;'>
                    </td>
                    <td>
                        <input type='text' name='items[$i][c]' value='$c' style='width:100%; padding:6px;'>
                    </td>
                  </tr>";
        }
        echo "</table></div>";

        echo "<div style='text-align:center; margin:14px 0;'>
                <button type='submit' style='padding:10px 14px; background:#4CAF50; color:#fff; border:none; border-radius:6px;'>
                    ▶ Create Quiz Table (manual distractors)
                </button>
              </div>";

        echo "</form>";
    }
}

// ====== Preview if generated ======
if (!empty($generatedTable)) {
    echo "<h3 style='text-align:center;'>Preview: <code>".htmlspecialchars($generatedTable)."</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable` LIMIT 20");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "<tr>
                    <td>".htmlspecialchars($row['question'])."</td>
                    <td>".htmlspecialchars($row['correct_answer'])."</td>
                    <td>".htmlspecialchars($row['wrong1'])."</td>
                    <td>".htmlspecialchars($row['wrong2'])."</td>
                    <td>".htmlspecialchars($row['wrong3'])."</td>
                  </tr>";
        }
    }
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit</a>
          </div>";
}
?>
