<?php
// generate_quiz_choices.php — Manual workflow (no AI)
// - Use file_explorer.php to pick a source table
// - Immediately show an editable grid (question/correct prefilled; wrong1/2/3 + image_url empty)
// - Create quiz table ONLY on Save (no AI calls, no preview)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$conn->set_charset('utf8mb4');

/* ---------------- Helpers ---------------- */
function safeTablePart(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9_]+/u', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}
function tableExists(mysqli $conn, string $t): bool {
    $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
    return $res && $res->num_rows > 0;
}
function ensureQuizTableOnSave(mysqli $conn, string $table): bool {
    if (tableExists($conn, $table)) return true;
    $esc = $conn->real_escape_string($table);
    $sql = "CREATE TABLE `{$esc}` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        correct_answer TEXT NOT NULL,
        wrong1 TEXT NULL,
        wrong2 TEXT NULL,
        wrong3 TEXT NULL,
        image_url TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return (bool)$conn->query($sql);
}

/* ---------------- Build folders for explorer (non-quiz only) ---------------- */
function getUserFoldersAndTables(mysqli $conn, string $username): array {
    $allTables = [];
    $result = $conn->query('SHOW TABLES');
    if ($result) {
        while ($row = $result->fetch_array()) {
            $table = $row[0];
            if (strpos($table, 'quiz_choices_') === 0) continue; // skip quiz tables as sources
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

/* ---------------- Page state ---------------- */
$username = strtolower($_SESSION['username'] ?? '');
$folders  = getUserFoldersAndTables($conn, $username);

// built-ins visible under Shared (as in your original)
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words',  'display_name' => 'Mastered Words'];

// folderData for file_explorer.php
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table'   => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

// Source table selected via explorer
$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$selectedTable = is_string($selectedTable) ? trim($selectedTable) : '';

$column1 = '';
$column2 = '';
$sourceRows = [];

/* ---------------- Handle SAVE (create target quiz table only now) ---------------- */
if (($_POST['action'] ?? '') === 'save_quiz') {
    $saveFolder   = safeTablePart($_POST['save_folder'] ?? '');
    $saveName     = safeTablePart($_POST['save_name'] ?? '');
    $overwrite    = !empty($_POST['save_overwrite']);
    $rows         = $_POST['rows'] ?? [];
    $me           = strtolower($_SESSION['username'] ?? 'user');

    if ($saveFolder === '' || $saveName === '') {
        $err = "Please enter both Folder and Name.";
    } else {
        $quizTable = "quiz_choices_{$me}_{$saveFolder}_{$saveName}";
        $quizEsc   = $conn->real_escape_string($quizTable);

        if ($overwrite && tableExists($conn, $quizTable)) {
            $conn->query("DROP TABLE `{$quizEsc}`");
        }
        if (!ensureQuizTableOnSave($conn, $quizTable)) {
            $err = "Could not create quiz table: ".htmlspecialchars($conn->error);
        } else {
            // Insert edited rows
            $sql  = "INSERT INTO `{$quizEsc}` (question, correct_answer, wrong1, wrong2, wrong3, image_url) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $err = "Prepare failed: ".htmlspecialchars($conn->error);
            } else {
                foreach ($rows as $r) {
                    $q   = trim($r['question'] ?? '');
                    $cor = trim($r['correct']  ?? '');
                    $w1  = trim($r['wrong1']   ?? '');
                    $w2  = trim($r['wrong2']   ?? '');
                    $w3  = trim($r['wrong3']   ?? '');
                    $img = trim($r['image_url']?? '');

                    if ($q === '' || $cor === '') continue; // require question + correct
                    $stmt->bind_param('ssssss', $q, $cor, $w1, $w2, $w3, $img);
                    if (!$stmt->execute()) {
                        $err = "Insert failed: ".htmlspecialchars($stmt->error);
                        break;
                    }
                }
                $stmt->close();

                if (!isset($err)) {
                    // go play the quiz
                    header("Location: play_quiz.php?table=" . urlencode($quizTable));
                    exit;
                }
            }
        }
    }
}

/* ---------------- Load source table if selected (for immediate editing) ---------------- */
if ($selectedTable !== '') {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($selectedTable)."`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $column1 = $cols[0]['Field'];
        $column2 = $cols[1]['Field'];
        $dataRes = $conn->query(
            "SELECT `".$conn->real_escape_string($column1)."` AS q, `".$conn->real_escape_string($column2)."` AS c
             FROM `".$conn->real_escape_string($selectedTable)."`"
        );
        if ($dataRes) {
            while ($row = $dataRes->fetch_assoc()) {
                $q = trim($row['q']); $c = trim($row['c']);
                if ($q === '' || $c === '') continue;
                $sourceRows[] = [
                    'question'  => $q,
                    'correct'   => $c,
                    'wrong1'    => '',
                    'wrong2'    => '',
                    'wrong3'    => '',
                    'image_url' => ''
                ];
            }
        }
    }
}

/* ---------------- Output ---------------- */
echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Create Quiz (Manual Distractors)</h2>";
echo "<p style='text-align:center;'>Pick a source file, then fill in the wrong answers. No AI involved.</p>";

if (isset($err)) {
    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>$err</div>";
}

/* File explorer: unchanged include */
include 'file_explorer.php';

/* Editor shows immediately once a source table is selected */
if ($selectedTable === '') {
    echo "<div class='content' style='color:#475569;'>Select a source table above. We’ll use its first two columns as <b>Question</b> and <b>Correct Answer</b>. Wrong answers start empty.</div>";
} else {
    $ansHeader = htmlspecialchars(ucfirst($column2 ?: 'Answer'));
    echo "<div class='content' style='color:#065f46;background:#d1fae5;border:1px solid #a7f3d0;padding:8px 10px;border-radius:6px;margin:12px 0;'>
            Loaded <code>".htmlspecialchars($selectedTable)."</code>. Detected columns:
            <code>".htmlspecialchars($column1 ?: 'col1')."</code> → Question,
            <code>".htmlspecialchars($column2 ?: 'col2')."</code> → Correct Answer.
          </div>";

    echo "<form method='POST' action='' style='margin-top:10px;'>
            <input type='hidden' name='action' value='save_quiz'>
            <div style='display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:6px 0 10px 0;'>
              <strong>Save As…</strong>
              <label>Folder: <input type='text' name='save_folder' placeholder='e.g., animals' style='padding:6px;'></label>
              <label>Name: <input type='text' name='save_name' placeholder='e.g., de_en_2025' style='padding:6px;'></label>
              <label title='If checked and quiz table exists, it will be replaced'><input type='checkbox' name='save_overwrite' value='1'> Overwrite if exists</label>
            </div>
            <div style='font-size:12px; color:#475569; margin-top:-6px; margin-bottom:10px;'>
              Will create <code>quiz_choices_".htmlspecialchars($username ?: 'user')."_FOLDER_NAME_FILE_NAME</code>
            </div>";

    echo "  <div style='max-height:65vh; overflow:auto; border:1px solid #e5e7eb; border-radius:8px;'>
              <table style='width:100%; border-collapse:collapse;'>
                <thead>
                  <tr style='background:#f8fafc; position:sticky; top:0; z-index:1;'>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>Question</th>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>{$ansHeader}</th>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>Wrong 1</th>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>Wrong 2</th>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>Wrong 3</th>
                    <th style='border:1px solid #e5e7eb; padding:6px;'>Image URL (optional)</th>
                  </tr>
                </thead>
                <tbody>";
    if (empty($sourceRows)) {
        echo "<tr><td colspan='6' style='border:1px solid #e5e7eb; padding:8px;'><em>No rows found in the source table.</em></td></tr>";
    } else {
        foreach ($sourceRows as $i => $r) {
            echo "<tr>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <textarea name='rows[$i][question]' style='width:100%; min-height:44px;'>".htmlspecialchars($r['question'])."</textarea>
                    </td>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <textarea name='rows[$i][correct]' style='width:100%; min-height:44px;'>".htmlspecialchars($r['correct'])."</textarea>
                    </td>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <textarea name='rows[$i][wrong1]' style='width:100%; min-height:44px;'></textarea>
                    </td>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <textarea name='rows[$i][wrong2]' style='width:100%; min-height:44px;'></textarea>
                    </td>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <textarea name='rows[$i][wrong3]' style='width:100%; min-height:44px;'></textarea>
                    </td>
                    <td style='border:1px solid #e5e7eb; padding:6px;'>
                      <input type='url' name='rows[$i][image_url]' placeholder='https://...' style='width:100%;'>
                    </td>
                  </tr>";
        }
        // one extra empty row at the end
        $i = count($sourceRows);
        echo "<tr>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <textarea name='rows[$i][question]' style='width:100%; min-height:44px;'></textarea>
                </td>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <textarea name='rows[$i][correct]' style='width:100%; min-height:44px;'></textarea>
                </td>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <textarea name='rows[$i][wrong1]' style='width:100%; min-height:44px;'></textarea>
                </td>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <textarea name='rows[$i][wrong2]' style='width:100%; min-height:44px;'></textarea>
                </td>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <textarea name='rows[$i][wrong3]' style='width:100%; min-height:44px;'></textarea>
                </td>
                <td style='border:1px solid #e5e7eb; padding:6px;'>
                  <input type='url' name='rows[$i][image_url]' placeholder='https://...' style='width:100%;'>
                </td>
              </tr>";
    }
    echo "      </tbody>
              </table>
            </div>
            <div style='margin-top:12px;'>
              <button type='submit' style='padding:10px 14px; background:#2563eb; color:#fff; border:none; border-radius:6px;'>💾 Save Quiz</button>
            </div>
          </form>";
}
?>
