<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';

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
function ensureQuizChoicesTable(mysqli $conn, string $table): bool {
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

/* ---------------- Folder builder for explorer (normal tables only) ----------------
   - Own tables: <username>_folder_file...
   - Shared (public/private): excludes quiz tables (no 'quiz_choices_%')
----------------------------------------------------------------------------- */
function getSourceFoldersAndTables(mysqli $conn, string $username): array {
    $all = [];

    // 1) Your own normal tables
    $res = $conn->query("SHOW TABLES");
    while ($res && ($row = $res->fetch_array())) {
        $t = $row[0];
        if (strpos($t, 'quiz_choices_') === 0) continue; // skip quiz sets here
        if (stripos($t, $username . '_') === 0) {
            $suffix = substr($t, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts  = explode('_', $suffix, 2);
            $folder = (count($parts) === 2 && trim($parts[0]) !== '') ? $parts[0] : 'Uncategorized';
            $file   = (count($parts) === 2) ? $parts[1] : $suffix;
            $all[$folder][] = ['table_name'=>$t, 'display_name'=>$file];
        }
    }

    // 2) Shared (public + private), but only non-quiz tables
    $addShared = function(string $t, string $owner) use (&$all, $conn) {
        if (strpos($t, 'quiz_choices_') === 0) return; // exclude quiz in this page
        // ensure table exists; silently skip dead pointers
        $exists = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
        if (!$exists || $exists->num_rows === 0) return;

        if (stripos($t, $owner . '_') === 0) {
            $suffix  = substr($t, strlen($owner) + 1); // folder_sub_file
            $display = $owner . '_' . $suffix;
        } else {
            $display = $t;
        }
        $all['Shared'][] = ['table_name'=>$t, 'display_name'=>$display];
    };

    $seen = [];

    // Public shares
    $shares = $conn->query("SELECT table_name, owner FROM shared_tables");
    while ($shares && ($s = $shares->fetch_assoc())) {
        $t = $s['table_name']; $owner = strtolower($s['owner']);
        if (isset($seen[$t])) continue; $seen[$t] = true;
        $addShared($t, $owner);
    }

    // Private shares for me (if table exists)
    $me = strtolower($username);
    $myEmail = null;
    if (!empty($_SESSION['email'])) {
        $myEmail = strtolower($_SESSION['email']);
    } else {
        if ($stmt = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1")) {
            $stmt->bind_param('s', $me);
            if ($stmt->execute()) {
                $r = $stmt->get_result(); $row = $r ? $r->fetch_assoc() : null;
                if (!empty($row['email'])) $myEmail = strtolower($row['email']);
            }
            $stmt->close();
        }
    }
    $chkPriv = $conn->query("SHOW TABLES LIKE 'shared_tables_private'");
    $hasPrivate = $chkPriv && $chkPriv->num_rows > 0;

    if ($hasPrivate && ($me || $myEmail)) {
        if ($myEmail) {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_email IS NOT NULL AND LOWER(target_email)=?)
                    OR (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('ss', $myEmail, $me);
        } else {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('s', $me);
        }
        if ($stmt && $stmt->execute()) {
            $rp = $stmt->get_result();
            while ($rp && ($p = $rp->fetch_assoc())) {
                $t = $p['table_name']; $owner = strtolower($p['owner']);
                if (isset($seen[$t])) continue; $seen[$t] = true;
                $addShared($t, $owner);
            }
            $stmt->close();
        }
    }

    // Sort folders and files
    ksort($all, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($all as &$list) {
        usort($list, fn($a,$b)=>strnatcasecmp($a['display_name'], $b['display_name']));
    }
    return $all;
}

/* ---------------- SAVE QUIZ ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_quiz') {
    $username   = strtolower($_SESSION['username'] ?? 'user');
    $folder     = safeTablePart($_POST['save_folder'] ?? '');
    $name       = safeTablePart($_POST['save_name'] ?? '');
    $overwrite  = !empty($_POST['save_overwrite']);
    $rows       = $_POST['rows'] ?? [];

    if ($folder === '' || $name === '') {
        $err = "Please enter both Folder and Name.";
    } else {
        $quizTable = "quiz_choices_{$username}_{$folder}_{$name}";

        if ($overwrite && tableExists($conn, $quizTable)) {
            $conn->query("DROP TABLE `".$conn->real_escape_string($quizTable)."`");
        }
        if (!ensureQuizChoicesTable($conn, $quizTable)) {
            $err = "Could not create quiz table: ".htmlspecialchars($conn->error);
        } else {
            $sql = "INSERT INTO `".$conn->real_escape_string($quizTable)."`
                    (question, correct_answer, wrong1, wrong2, wrong3, image_url)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $err = "Prepare failed: ".htmlspecialchars($conn->error);
            } else {
                foreach ($rows as $r) {
                    $q   = trim($r['question'] ?? '');
                    $cor = trim($r['correct'] ?? '');
                    $w1  = trim($r['wrong1'] ?? '');
                    $w2  = trim($r['wrong2'] ?? '');
                    $w3  = trim($r['wrong3'] ?? '');
                    $img = trim($r['image_url'] ?? '');

                    if ($q === '' || $cor === '') continue; // require question+correct

                    $stmt->bind_param('ssssss', $q, $cor, $w1, $w2, $w3, $img);
                    if (!$stmt->execute()) {
                        $err = "Insert failed: ".htmlspecialchars($stmt->error);
                        break;
                    }
                }
                $stmt->close();

                if (!isset($err)) {
                    header("Location: play_quiz.php?table=" . urlencode($quizTable));
                    exit;
                }
            }
        }
    }
}

/* ---------------- PAGE STATE (selection via explorer) ---------------- */
$username = strtolower($_SESSION['username'] ?? '');
$folders = getSourceFoldersAndTables($conn, $username);

// Build folderData for file_explorer.php (expects table + display)
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table'   => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

// Selected source table (set by explorer submission)
$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? '';
$column1 = '';
$column2 = '';
$sourceRows = [];

if ($selectedFullTable !== '') {
    $colsRes = $conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($selectedFullTable)."`");
    if ($colsRes && $colsRes->num_rows >= 2) {
        $cols = $colsRes->fetch_all(MYSQLI_ASSOC);
        $column1 = $cols[0]['Field'];
        $column2 = $cols[1]['Field'];

        $dataRes = $conn->query("SELECT `".$conn->real_escape_string($column1)."` AS q, `".$conn->real_escape_string($column2)."` AS c FROM `".$conn->real_escape_string($selectedFullTable)."`");
        if ($dataRes) {
            while ($row = $dataRes->fetch_assoc()) {
                $sourceRows[] = [
                    'question' => $row['q'],
                    'correct'  => $row['c'],
                    'wrong1'   => '',
                    'wrong2'   => '',
                    'wrong3'   => '',
                    'image_url'=> ''
                ];
            }
        }
        // keep last selection available to explorer if it needs it
        $_SESSION['table'] = $selectedFullTable;
        $_SESSION['col1']  = $column1;
        $_SESSION['col2']  = $column2;
    }
}

/* ---------------- OUTPUT ---------------- */
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Create Quiz (Choices)</title>";
include 'styling.php';
echo "<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 16px; }
  table.quiz { width:100%; border-collapse: collapse; }
  table.quiz th, table.quiz td { border:1px solid #e5e7eb; padding:6px; vertical-align:top; }
  table.quiz th { background:#f8fafc; position:sticky; top:0; z-index:1; }
  textarea { width:100%; min-height:44px; resize:vertical; padding:6px; }
  input[type=text], input[type=url] { width:100%; padding:6px; }
  .controls { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin:14px 0; }
  .hint { color:#475569; font-size:12px; }
  .error { color:#b91c1c; background:#fee2e2; border:1px solid #fecaca; padding:8px 10px; border-radius:6px; margin:12px 0; }
  .ok { color:#065f46; background:#d1fae5; border:1px solid #a7f3d0; padding:8px 10px; border-radius:6px; margin:12px 0; }
  .btn { padding:8px 12px; border:0; border-radius:6px; background:#2563eb; color:#fff; cursor:pointer; }
  .btn.gray { background:#475569; }
</style>";
echo "</head><body><div class='wrap'>";

echo "<div style='margin-bottom:10px;'>
        👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a>
      </div>";

echo "<h2>🧩 Create Quiz (Multiple Choice)</h2>";

if (isset($err)) {
    echo "<div class='error'>".$err."</div>";
}

/* -------- Source selection via existing file_explorer -------- */
echo "<div style='margin:10px 0 16px;'>
        <h3 style='margin:6px 0;'>1) Select source table</h3>";
// Keep the explorer exactly as-is:
include 'file_explorer.php';
echo "</div>";

/* -------- Editor appears immediately once a source is chosen -------- */
if ($selectedFullTable === '') {
    echo "<div class='hint'>Pick a source file above. We will use its first two columns as <b>Question</b> and <b>Correct Answer</b>.
          Wrong answers are left empty for you to fill in.</div>";
} else {
    echo "<div class='ok'>Source <code>".htmlspecialchars($selectedFullTable)."</code> loaded. Detected: ".
         "<code>".htmlspecialchars($column1)."</code> → Question, ".
         "<code>".htmlspecialchars($column2)."</code> → Correct Answer.</div>";

    echo "<h3 style='margin:12px 0;'>2) Edit choices (wrong answers start empty)</h3>";

    echo "<form method='POST' action=''>
            <input type='hidden' name='action' value='save_quiz'>
            <div class='controls'>
              <strong>Save As…</strong>
              <label>Folder: <input type='text' name='save_folder' placeholder='e.g., animals'></label>
              <label>Name: <input type='text' name='save_name' placeholder='e.g., de_en_2025'></label>
              <label title='If checked and quiz table exists, it will be replaced'><input type='checkbox' name='save_overwrite' value='1'> Overwrite if exists</label>
            </div>
            <div class='hint' style='margin-top:-8px;margin-bottom:10px;'>Will create <code>quiz_choices_".htmlspecialchars(strtolower($_SESSION['username'] ?? 'user'))."_FOLDER_NAME_FILE_NAME</code></div>";

    echo "<div style='max-height:65vh; overflow:auto; border:1px solid #e5e7eb; border-radius:8px;'>
            <table class='quiz'>
              <thead>
                <tr>
                  <th style='width:26%;'>Question</th>
                  <th style='width:26%;'>Correct Answer</th>
                  <th style='width:16%;'>Wrong 1</th>
                  <th style='width:16%;'>Wrong 2</th>
                  <th style='width:16%;'>Wrong 3</th>
                  <th style='width:22%;'>Image URL (optional)</th>
                </tr>
              </thead>
              <tbody>";
    if (empty($sourceRows)) {
        echo "<tr><td colspan='6'><em>No rows found in the source table.</em></td></tr>";
    } else {
        foreach ($sourceRows as $i => $r) {
            echo "<tr>
                    <td><textarea name='rows[$i][question]' oninput='autoResize(this)'>".htmlspecialchars($r['question'])."</textarea></td>
                    <td><textarea name='rows[$i][correct]'  oninput='autoResize(this)'>".htmlspecialchars($r['correct'])."</textarea></td>
                    <td><textarea name='rows[$i][wrong1]'   oninput='autoResize(this)'></textarea></td>
                    <td><textarea name='rows[$i][wrong2]'   oninput='autoResize(this)'></textarea></td>
                    <td><textarea name='rows[$i][wrong3]'   oninput='autoResize(this)'></textarea></td>
                    <td><input type='url' name='rows[$i][image_url]' placeholder='https://...'></td>
                  </tr>";
        }
        // one extra empty row
        $i = count($sourceRows);
        echo "<tr>
                <td><textarea name='rows[$i][question]' oninput='autoResize(this)'></textarea></td>
                <td><textarea name='rows[$i][correct]'  oninput='autoResize(this)'></textarea></td>
                <td><textarea name='rows[$i][wrong1]'   oninput='autoResize(this)'></textarea></td>
                <td><textarea name='rows[$i][wrong2]'   oninput='autoResize(this)'></textarea></td>
                <td><textarea name='rows[$i][wrong3]'   oninput='autoResize(this)'></textarea></td>
                <td><input type='url' name='rows[$i][image_url]' placeholder='https://...'></td>
              </tr>";
    }
    echo "    </tbody>
            </table>
          </div>
          <div style='margin-top:12px;'>
            <button class='btn' type='submit'>💾 Save Quiz</button>
          </div>
        </form>";
}

echo "</div>
<script>
function autoResize(t){
  t.style.height='auto';
  t.style.overflow='hidden';
  t.style.height=t.scrollHeight+'px';
}
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('textarea').forEach(autoResize);
});
</script>
</body></html>";
