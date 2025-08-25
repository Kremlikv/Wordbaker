<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'session.php';
require_once 'db.php';
include 'styling.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$feedback = "";

// Handle mark as mastered action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['unmark'])) {
    $source_word = trim($_POST['source_word'] ?? '');
    $target_word = trim($_POST['target_word'] ?? '');

    error_log("[DEBUG] Attempting to mark as mastered: user_id=$user_id, source_word=$source_word, target_word=$target_word");

    $stmt = $conn->prepare("SELECT language FROM difficult_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
    $stmt->bind_param("iss", $user_id, $source_word, $target_word);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
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
            $feedback = "<p style='color: green;'>✅ Word marked as mastered.</p>";
        } else {
            error_log("[DEBUG] ❌ Delete failed: " . $del->error);
        }
    } else {
        error_log("[DEBUG] ❌ Word not found in difficult_words");
    }
}

// Fetch user's difficult words
$stmt = $conn->prepare("SELECT source_word, target_word, language, last_attempt, id FROM difficult_words WHERE user_id = ? ORDER BY last_attempt DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Neumím</title>
  <style>
    body { font-family: Arial; padding: 0px; }
    table { border-collapse: collapse; width: 90%; margin: auto; }
    th, td { padding: 12px; border: 1px solid #ccc; text-align: center; }
    h2 { text-align: center; }
  </style>
</head> 
<body>

<!-- Login info -->
<?php echo "<div class='content'>";
echo "👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>"; ?>

<h2>🌟 Co ještě neumím: <?php echo htmlspecialchars($username); ?></h2>
<?php echo $feedback; ?>

<?php if ($result->num_rows > 0): ?>
<br><br>

<table>
  <tr>
    <th>Český jazyk</th>
    <th>Cizí jazyk</th>
    <th>Akce</th>
    <th>Jazyk</th>
    <th>Poslední pokus</th>
  </tr>
  <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?php echo htmlspecialchars(trim($row['source_word'])); ?></td>
      <td><?php echo htmlspecialchars(trim($row['target_word'])); ?></td>
      <td>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="source_word" value="<?php echo htmlspecialchars($row['source_word']); ?>">
          <input type="hidden" name="target_word" value="<?php echo htmlspecialchars($row['target_word']); ?>">
          <button type="submit" name="unmark" onclick="return confirm('Mark this word as mastered?')">✅ I know this</button>
        </form>
      </td>
      <td><?php echo htmlspecialchars($row['language']); ?></td>
      <td><?php echo htmlspecialchars($row['last_attempt']); ?></td>
    </tr>
  <?php endwhile; ?>
</table>




<?php else: ?>
  <p style="text-align:center;">Ještě jste neoznačili žádná slova jako "neumím". ✅</p>
<?php endif; ?>

</body>
</html>
