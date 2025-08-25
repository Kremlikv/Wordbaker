<?php
require_once 'session.php';
// echo '<pre>[Debug] SESSION: ' . print_r($_SESSION, true) . '</pre>';
require_once 'db.php';
include 'styling.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$feedback = "";

// [Debug]
// echo "<!-- DEBUG: user_id = $user_id -->";

// Handle return-to-difficult action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['return'])) {
    $source_word = trim($_POST['source_word'] ?? '');
    $target_word = trim($_POST['target_word'] ?? '');

    $stmt = $conn->prepare("SELECT language FROM mastered_words WHERE user_id = ? AND source_word = ? AND target_word = ? LIMIT 1");
    $stmt->bind_param("iss", $user_id, $source_word, $target_word);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $language = $row['language'];

        // Insert back into difficult_words
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

        $feedback = "<p style='color: green;'>✅ Word returned to difficult words.</p>";
    }
}

// ✅ Fetch mastered words
$stmt = $conn->prepare("SELECT source_word, target_word, language, last_seen, id FROM mastered_words WHERE user_id = ? ORDER BY last_seen DESC");
if (!$stmt) {
    die("Failed to prepare SELECT: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$debugCount = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mastered Words</title>
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

<h2>🌟 Umím: <?php echo htmlspecialchars($username); ?></h2>
<?php echo $feedback; ?>
<!-- <p style="text-align:center; font-style:italic; color:gray;">(Found <?php echo $debugCount; ?> mastered words)</p>  -->

<?php if ($debugCount > 0): ?>

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
          <button type="submit" name="return" onclick="return confirm('Move this word back to difficult list?')">🔁 Study More</button>
        </form>
      </td>
      <td><?php echo htmlspecialchars($row['language']); ?></td>
      <td><?php echo htmlspecialchars($row['last_seen']); ?></td>
    </tr>
  <?php endwhile; ?>
</table>



<?php else: ?>
  <p style="text-align:center;">Zatím jste neoznačili žádná slova jako "umím". ✅</p>
<?php endif; ?>

</body>
</html>
