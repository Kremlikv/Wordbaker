<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';

$table = $_GET['table'] ?? '';
if (!$table) die("No table selected.");

// Save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $saveTable = $_POST['save_table'];
    $editedRows = $_POST['edited_rows'] ?? [];
    $deleteRows = $_POST['delete_rows'] ?? [];
    foreach ($editedRows as $id => $row) {
        if (in_array($id, $deleteRows)) {
            $conn->query("DELETE FROM `{$saveTable}` WHERE id=".(int)$id);
            continue;
        }
        $stmt = $conn->prepare("UPDATE `{$saveTable}` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=? WHERE id=?");
        $stmt->bind_param("ssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: quiz_edit.php?table=" . urlencode($saveTable) . "&saved=1");
    exit;
}

// Delete quiz
if (isset($_POST['delete_quiz'])) {
    $conn->query("DROP TABLE IF EXISTS `{$table}`");
    header("Location: generate_quiz_choices.php");
    exit;
}

// Now safe to output HTML
include 'styling.php';
?>

<style>
.table-container { overflow-x:auto; -webkit-overflow-scrolling:touch; }
table { border-collapse:collapse; width:100%; max-width:100%; }
th, td { border:1px solid #ccc; padding:5px; text-align:left; vertical-align:top; }
textarea { width:100%; min-width:100px; box-sizing:border-box; resize:vertical; font-size:14px; }
.ai-note { font-size:12px; color:#334155; line-height:1.35; }
.sticky-controls { position:sticky; top:0; background:#fff; padding:8px 0; z-index:5; }
@media (max-width:600px){
  th, td { font-size:12px; padding:3px; }
  textarea { font-size:12px; }
  .ai-note { font-size:11px; }
}
.hidden { display:none; }
</style>
<?php
echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Edit Quiz: <code>".htmlspecialchars($table)."</code></h2>";
if (isset($_GET['saved'])) {
    echo "<div style='color:green; text-align:center;'>✅ Changes saved</div>";
}

// Does the table have the ai_candidates column? (so we can show/hide it gracefully)
$hasAiCol = false;
$dbNameRes = $conn->query('SELECT DATABASE() AS db');
$dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
$dbName = $dbNameRow ? $dbNameRow['db'] : '';
$chkSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$conn->real_escape_string($dbName)."' AND TABLE_NAME='".$conn->real_escape_string($table)."' AND COLUMN_NAME='ai_candidates'";
$chkRes = $conn->query($chkSql);
if ($chkRes && ($r = $chkRes->fetch_assoc())) { $hasAiCol = ((int)$r['cnt'] > 0); }

// Controls
?>
<div class="sticky-controls content" style="text-align:center;">
  <?php if ($hasAiCol): ?>
    <label style="user-select:none; cursor:pointer;">
      <input type="checkbox" id="toggleAi" checked>
      Show AI candidates (read‑only)
    </label>
  <?php endif; ?>
</div>

<?php
echo "<form method='POST' style='text-align:center;'>
        <input type='hidden' name='save_table' value='".htmlspecialchars($table)."'>
        <div class='table-container'>
        <table>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th>";
if ($hasAiCol) echo "<th class='ai-col'>AI candidates (raw)</th>";
echo "<th>Delete</th></tr>";

$res = $conn->query("SELECT * FROM `{$table}`");
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    echo "<tr>
            <td>".htmlspecialchars($row['question'])."</td>
            <td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>".htmlspecialchars($row['correct_answer'])."</textarea></td>
            <td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>".htmlspecialchars($row['wrong1'])."</textarea></td>
            <td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>".htmlspecialchars($row['wrong2'])."</textarea></td>
            <td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>".htmlspecialchars($row['wrong3'])."</textarea></td>";
    if ($hasAiCol) {
        $ai = isset($row['ai_candidates']) ? $row['ai_candidates'] : '';
        echo "<td class='ai-col'><div class='ai-note'>".htmlspecialchars($ai)."</div></td>";
    }
    echo "  <td><input type='checkbox' name='delete_rows[]' value='{$id}'></td>
          </tr>";
}

echo "</table></div><br>
      <button type='submit'>💾 Save Changes</button>
      </form>

      <div style='text-align:center; margin-top:20px;'>
        <a href='add_images.php?table=".urlencode($table)."' style='padding:10px; background:#2196F3; color:#fff; text-decoration:none;'>🖼 Do you want to add pictures?</a>
      </div>

      <form method='POST' style='text-align:center; margin-top:20px;'>
        <input type='hidden' name='delete_table' value='".htmlspecialchars($table)."'>
        <button type='submit' name='delete_quiz' onclick='return confirm(\"Delete this quiz and all images?\")'>🗑 Delete Quiz</button>
      </form>";
?>
<script>
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = (el.scrollHeight) + 'px';
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea').forEach(el => autoResize(el));
  const toggle = document.getElementById('toggleAi');
  if (toggle) {
    const setVis = () => {
      document.querySelectorAll('.ai-col').forEach(td => {
        td.classList.toggle('hidden', !toggle.checked);
      });
    };
    toggle.addEventListener('change', setVis);
    setVis();
  }
});
</script>
