<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';

// --- Helpers ---
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

// Quiz table schema (choices)
function ensureQuizChoicesTable(mysqli $conn, string $table): bool {
    $esc = $conn->real_escape_string($table);
    if (tableExists($conn, $table)) return true;
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

// ---------- Handle SAVE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_quiz') {
    $conn->set_charset('utf8mb4');
    $username   = strtolower($_SESSION['username'] ?? 'user');
    $folder     = safeTablePart($_POST['save_folder'] ?? '');
    $name       = safeTablePart($_POST['save_name'] ?? '');
    $overwrite  = !empty($_POST['save_overwrite']);
    $rows       = $_POST['rows'] ?? [];

    if ($folder === '' || $name === '') {
        $err = "Please enter both Folder and Name.";
    } else {
        $quizTable = "quiz_choices_{$username}_{$folder}_{$name}";

        // Overwrite support
        if ($overwrite && tableExists($conn, $quizTable)) {
            $conn->query("DROP TABLE `".$conn->real_escape_string($quizTable)."`");
        }

        if (!ensureQuizChoicesTable($conn, $quizTable)) {
            $err = "Could not create quiz table: ".htmlspecialchars($conn->error);
        } else {
            // Insert rows (skip completely empty lines)
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

                    // require at least a question+correct to insert
                    if ($q === '' || $cor === '') continue;

                    $stmt->bind_param('ssssss', $q, $cor, $w1, $w2, $w3, $img);
                    if (!$stmt->execute()) {
                        $err = "Insert failed: ".htmlspecialchars($stmt->error);
                        break;
                    }
                }
                $stmt->close();

                if (!isset($err)) {
                    // redirect to play_quiz or back to main with the new table selected
                    header("Location: play_quiz.php?table=" . urlencode($quizTable));
                    exit;
                }
            }
        }
    }
}

// ---------- Load SOURCE immediately to editable form ----------
$conn->set_charset('utf8mb4');
$sourceTable = $_POST['source_table'] ?? $_GET['table'] ?? '';
$col1 = '';
$col2 = '';
$sourceRows = [];

if ($sourceTable !== '') {
    // find first two columns as question/correct
    $colsRes = $conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($sourceTable)."`");
    if ($colsRes && $colsRes->num_rows >= 2) {
        $cols = $colsRes->fetch_all(MYSQLI_ASSOC);
        $col1 = $cols[0]['Field'];
        $col2 = $cols[1]['Field'];

        // load data
        $dataRes = $conn->query("SELECT `".$conn->real_escape_string($col1)."` AS q, `".$conn->real_escape_string($col2)."` AS c FROM `".$conn->real_escape_string($sourceTable)."`");
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
    }
}

// ---------- Output ----------
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

// Source selector (optional helper)
echo "<form method='GET' style='margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;'>
        <label>Source table:
          <input type='text' name='table' placeholder='e.g. johndoe_animals_de_en' value='".htmlspecialchars($sourceTable)."'>
        </label>
        <button class='btn gray' type='submit'>Load</button>
        <div class='hint'>Takes the first two columns as Question & Correct Answer.</div>
      </form>";

if ($sourceTable === '') {
    echo "<div class='hint'>Pick a source table (from your explorer or enter it above) to start building a quiz. The first two columns will be used as Question and Correct Answer. The three Wrong Answer fields will be empty for you to fill.</div>";
} else {
    echo "<div class='ok'>Source <code>".htmlspecialchars($sourceTable)."</code> loaded. First two columns detected as: <code>".htmlspecialchars($col1)."</code> → Question, <code>".htmlspecialchars($col2)."</code> → Correct Answer.</div>";

    // Editable grid immediately (no read-only preview)
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
        // plus one empty row for convenience
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
