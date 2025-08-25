<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'session.php';
require_once 'db.php';
include 'styling.php';

date_default_timezone_set('Europe/Prague'); // ensure correct timezone

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$feedback  = "";

/**
 * Format a timestamp (string) as "Srpen 2025" in Czech.
 * Tries IntlDateFormatter first; falls back to a manual month map.
 */
function formatMonthCs(string $datetimeStr): string {
    $ts = strtotime($datetimeStr);
    if ($ts === false) return htmlspecialchars($datetimeStr, ENT_QUOTES, 'UTF-8');

    // Prefer IntlDateFormatter if available
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'cs_CZ',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Europe/Prague',
            IntlDateFormatter::GREGORIAN,
            'LLLL yyyy' // stand-alone month name + year (Czech months are lowercase by default)
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            // Capitalize first letter for a "big title" look, preserving UTF-8
            return mb_convert_case(mb_substr($out, 0, 1, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
                 . mb_substr($out, 1, null, 'UTF-8');
        }
    }

    // Fallback: manual month names in Czech (nominative)
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

// Handle "mark as mastered" action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['unmark'])) {
    $source_word = trim($_POST['source_word'] ?? '');
    $target_word = trim($_POST['target_word'] ?? '');

    error_log("[DEBUG] Attempting to mark as mastered: user_id=$user_id, source_word=$source_word, target_word=$target_word");

    $stmt = $conn->prepare("SELECT language FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
    $stmt->bind_param("iss", $user_id, $source_word, $target_word);
    $stmt->execute();
    $resultCheck = $stmt->get_result();

    if ($row = $resultCheck->fetch_assoc()) {
        $language = $row['language'];

        $check = $conn->prepare("SELECT 1 FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
        $check->bind_param("iss", $user_id, $source_word, $target_word);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $ins = $conn->prepare("INSERT INTO mastered_words (source_word, target_word, language, last_seen, user_id) VALUES (?, ?, ?, NOW(), ?)");
            $ins->bind_param("sssi", $source_word, $target_word, $language, $user_id);
            if ($ins->execute()) {
                error_log("[DEBUG] ✅ Inserted into mastered_words: $source_word → $target_word");
            } else {
                error_log("[DEBUG] ❌ Insert failed: " . $ins->error);
            }
        } else {
            error_log("[DEBUG] ⚠️ Already in mastered_words");
        }

        $del = $conn->prepare("DELETE FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ?");
        $del->bind_param("iss", $user_id, $source_word, $target_word);
        if ($del->execute()) {
            error_log("[DEBUG] 🗑️ Deleted from difficult_words");
            $feedback = "<p style='color: green;'>✅ Slovo přesunuto do „zvládám“.</p>";
        } else {
            error_log("[DEBUG] ❌ Delete failed: " . $del->error);
        }
    } else {
        error_log("[DEBUG] ❌ Word not found in difficult_words");
    }
}

// Fetch user's difficult words (latest first)
$stmt = $conn->prepare("SELECT source_word, target_word, language, last_attempt, id FROM difficult_words WHERE user_id = ? ORDER BY last_attempt DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Neumím</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 0; }
    .content { padding: 12px 16px; }
    .month-title {
      text-align: center;
      font-size: 28px;
      margin: 28px auto 10px auto;
    }
    table { border-collapse: collapse; width: 90%; margin: 0 auto 28px auto; }
    th, td { padding: 12px; border: 1px solid #ccc; text-align: center; }
    th { background: #f5f5f5; }
    h2 { text-align: center; margin-top: 10px; }
    .btn-mastered {
      padding: 6px 10px;
      border: 1px solid #0a7d34;
      background: #e8f7ee;
      border-radius: 6px;
      cursor: pointer;
    }
    .btn-mastered:hover {
      background: #d8f0e3;
    }
    .loginbar { text-align: left; }
  </style>
</head>
<body>



<!-- Login info -->
<?php echo "<div class='content'>";
echo "👤 Přihlášený uživatel " . $_SESSION['username'] . " | <a href='logout.php'>Odhlásit</a>"; ?>


<h2>🌟 Co ještě neumím:</h2>
<?php echo $feedback; ?>

<?php if ($result->num_rows > 0): ?>
  <br><br>
  <?php
    $currentMonthKey = null; // e.g., "2025-08"
    $tableOpen = false;

    while ($row = $result->fetch_assoc()):
        $monthKey = date('Y-m', strtotime($row['last_attempt']));
        $monthTitle = formatMonthCs($row['last_attempt']);

        // New month? Close previous table and start a new one
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
                    <th>Poslední pokus</th>
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
              <button class="btn-mastered" type="submit" name="unmark" onclick="return confirm('Přesunout do „zvládám“?')">✅ To umím</button>
            </form>
          </td>
          <td><?php echo htmlspecialchars($row['language']); ?></td>
          <td><?php echo htmlspecialchars($row['last_attempt']); ?></td>
        </tr>
  <?php endwhile;

        if ($tableOpen) {
            echo "</tbody></table>";
        }
  ?>
<?php else: ?>
  <p style="text-align:center;">Ještě jste neoznačili žádná slova jako „neumím“. ✅</p>
<?php endif; ?>

</body>
</html>
