<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'session.php';
require_once 'db.php';
include 'styling.php';

date_default_timezone_set('Europe/Prague');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$feedback = "";

/**
 * Format a datetime string as Czech month + year (e.g., "Srpen 2025").
 * Uses IntlDateFormatter if available, otherwise falls back to a manual map.
 */
function formatMonthCs(string $datetimeStr): string {
    $ts = strtotime($datetimeStr);
    if ($ts === false) return htmlspecialchars($datetimeStr, ENT_QUOTES, 'UTF-8');

    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'cs_CZ',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Europe/Prague',
            IntlDateFormatter::GREGORIAN,
            'LLLL yyyy'
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return mb_convert_case(mb_substr($out, 0, 1, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
                 . mb_substr($out, 1, null, 'UTF-8');
        }
    }

    $months = [
        1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
        5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
        9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
    ];
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $name = $months[$m] ?? date('F', $ts);
    return $name . ' ' . $y;
}

/* ========= Actions ========= */

// Return a mastered word back to difficult_words
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['return'])) {
    $source_word = trim($_POST['source_word'] ?? '');
    $target_word = trim($_POST['target_word'] ?? '');

    $stmt = $conn->prepare("SELECT language FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
    $stmt->bind_param("iss", $user_id, $source_word, $target_word);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $language = $row['language'];

        // Insert back into difficult_words if not present
        $check = $conn->prepare("SELECT 1 FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
        $check->bind_param("iss", $user_id, $source_word, $target_word);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $ins = $conn->prepare("INSERT INTO difficult_words (source_word, target_word, language, last_attempt, user_id) VALUES (?, ?, ?, NOW(), ?)");
            $ins->bind_param("sssi", $source_word, $target_word, $language, $user_id);
            $ins->execute();
        }

        // Delete from mastered_words
        $del = $conn->prepare("DELETE FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
        $del->bind_param("iss", $user_id, $source_word, $target_word);
        $del->execute();

        $feedback = "<p style='color: green;'>✅ Slovo vráceno do „neumím“.</p>";
    }
}

// Delete a mastered word row entirely
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete'])) {
    $source_word = trim($_POST['source_word'] ?? '');
    $target_word = trim($_POST['target_word'] ?? '');

    $del = $conn->prepare("DELETE FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
    $del->bind_param("iss", $user_id, $source_word, $target_word);
    if ($del->execute() && $del->affected_rows > 0) {
        $feedback = "<p style='color: green;'>🗑️ Záznam smazán.</p>";
    } else {
        $feedback = "<p style='color: #b00;'>❌ Nepodařilo se smazat záznam.</p>";
    }
}

/* ========= Fetch ========= */

$stmt = $conn->prepare("SELECT source_word, target_word, language, last_seen, id FROM mastered_words WHERE user_id = ? ORDER BY last_seen DESC");
if (!$stmt) {
    die("Failed to prepare SELECT: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$rowCount = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Mastered Words</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 0; }
    .content { padding: 12px 16px; }
    .loginbar { text-align: left; }
    h2 { text-align: center; margin-top: 10px; }
    .month-title {
      text-align: center;
      font-size: 28px;
      margin: 28px auto 10px auto;
    }
    table { border-collapse: collapse; width: 90%; margin: 0 auto 28px auto; }
    th, td { padding: 12px; border: 1px solid #ccc; text-align: center; }
    th { background: #f5f5f5; }
    .btn {
      padding: 6px 10px;
      border: 1px solid #666;
      background: #f7f7f7;
      border-radius: 6px;
      cursor: pointer;
      margin: 0 2px;
    }
    .btn:hover { background: #eee; }
    .btn-return { border-color: #975a00; background: #fff7ea; }
    .btn-return:hover { background: #ffefd6; }
    .btn-delete { border-color: #a40000; background: #fdecec; }
    .btn-delete:hover { background: #f9d9d9; }
  </style>
</head>
<body>

<?php
echo "<div class='content loginbar'>";
echo "👤 Přihlášený uživatel " . htmlspecialchars($_SESSION['username']) . " | <a href='logout.php'>Odhlásit</a>";
echo "</div>";
?>

<h2>🌟 Umím: <?php echo htmlspecialchars($username); ?></h2>
<?php echo $feedback; ?>

<?php if ($rowCount > 0): ?>
  <br><br>
  <?php
    $currentMonthKey = null; // "YYYY-MM"
    $tableOpen = false;

    while ($row = $result->fetch_assoc()):
        $monthKey   = date('Y-m', strtotime($row['last_seen']));
        $monthTitle = formatMonthCs($row['last_seen']);

        if ($monthKey !== $currentMonthKey) {
            if ($tableOpen) {
                echo "</tbody></table>";
                $tableOpen = false;
            }
            $currentMonthKey = $monthKey;

            echo "<div class='month-title'>📅 " . htmlspecialchars($monthTitle) . "</div>";
            echo "<table>";
            echo "<thead><tr>
                    <th>Český jazyk</th>
                    <th>Cizí jazyk</th>
                    <th>Akce</th>
                    <th>Jazyk</th>
                    <th>Naposledy</th>
                  </tr></thead><tbody>";
            $tableOpen = true;
        }
  ?>
        <tr>
          <td><?php echo htmlspecialchars(trim($row['source_word'])); ?></td>
          <td><?php echo htmlspecialchars(trim($row['target_word'])); ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="source_word" value="<?php echo htmlspecialchars($row['source_word']); ?>">
              <input type="hidden" name="target_word" value="<?php echo htmlspecialchars($row['target_word']); ?>">
              <button class="btn btn-return" type="submit" name="return" onclick="return confirm('Přesunout zpět do „neumím“?')">🔁 Neumím</button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="source_word" value="<?php echo htmlspecialchars($row['source_word']); ?>">
              <input type="hidden" name="target_word" value="<?php echo htmlspecialchars($row['target_word']); ?>">
              <button class="btn btn-delete" type="submit" name="delete" onclick="return confirm('Opravdu smazat tento záznam?')">🗑️ Smazat</button>
            </form>
          </td>
          <td><?php echo htmlspecialchars($row['language']); ?></td>
          <td><?php echo htmlspecialchars($row['last_seen']); ?></td>
        </tr>
  <?php endwhile;

        if ($tableOpen) {
            echo "</tbody></table>";
        }
  ?>
<?php else: ?>
  <p style="text-align:center;">Zatím jste neoznačili žádná slova jako „umím“. ✅</p>
<?php endif; ?>

</body>
</html>
