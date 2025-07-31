<?php
require_once 'db.php'; 
require_once 'session.php';
include 'styling.php';

function getTables($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

$tables = getTables($conn);
$selectedTable = $_POST['table'] ?? ($_GET['table'] ?? ($_SESSION['table'] ?? ($tables[0] ?? '')));

$_SESSION['table'] = $selectedTable;

$rows = [];
$column1 = '';
$column2 = '';
$targetLanguage = '';

if (!empty($selectedTable)) {
    $result = $conn->query("SELECT * FROM `$selectedTable`");
    if ($result && $result->num_rows > 0) {
        $columns = $result->fetch_fields();
        $col1 = $columns[0]->name;
        $col2 = $columns[1]->name;

        $_SESSION['col1'] = $col1;
        $_SESSION['col2'] = $col2;

        while ($row = $result->fetch_assoc()) {
            if ($selectedTable === 'difficult_words') {
                $rows[] = [
                    'cz' => $row['source_word'],
                    'foreign' => $row['target_word'],
                    'language' => $row['language']
                ];
            } else {
                $rows[] = [
                    'cz' => $row[$col1],
                    'foreign' => $row[$col2]
                ];
            }
        }
        $column1 = $col1;
        $column2 = $col2;
        $targetLanguage = strtolower($col2);
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Flashcards: <?php echo htmlspecialchars($selectedTable); ?></title>
  <style>
    body {
      font-family: Arial;
      text-align: center;
      padding: 30px;
      margin: 0;
    }
    .card {
      font-size: 2em;
      margin: 20px auto;
      border: 2px solid #333;
      padding: 40px;
      width: 90%;
      max-width: 400px;
      cursor: pointer;
      background: #fdfdfd;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .controls button {
      margin: 10px;
      padding: 10px 20px;
      font-size: 1em;
    }
    textarea {
      height: auto;
      min-height: 2em;
      width: 100%;
      box-sizing: border-box;
      overflow: hidden;
      resize: none;
      font-size: 1em;
    }
    input[type="text"] {
      width: 100%;
      padding: 4px;
      font-size: 1em;
    }
  </style>
</head>
<body>

<div class='content'>
<p>👤 Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> | <a href='logout.php'>Logout</a></p>

<form method='POST' action=''>
  <label for='table'>Select a table:</label>
  <select name='table' id='table'>
    <?php foreach ($tables as $table): ?>
      <option value='<?php echo $table; ?>' <?php echo ($table === $selectedTable) ? 'selected' : ''; ?>><?php echo $table; ?></option>
    <?php endforeach; ?>
  </select>
  <button type='submit'>⬆️ Load</button>
</form>
<br><br>

<h2>Flashcards for Table: <?php echo htmlspecialchars($selectedTable); ?></h2>

<form method="POST" action="review_difficult.php" style="margin-bottom: 20px;">
  <input type="hidden" name="table" value="<?php echo htmlspecialchars($selectedTable); ?>">
  <button type="submit">🧠 Review My Difficult Words</button>
</form>

<div id="card" class="card" onclick="flipCard()"></div>
<div class="controls">
  <button onclick="prevCard()">⬅️ Previous</button>
  <button onclick="markKnown()">✅ I know this</button>
  <button onclick="markDifficult()">❌ Study more</button>
  <button onclick="nextCard()">Next ➡️</button><br><br>
  🔊 Czech Audio: <input type="checkbox" id="toggleCz" checked onchange="toggleTTS('cz')">
  🔊 Foreign Audio: <input type="checkbox" id="toggleForeign" checked onchange="toggleTTS('foreign')"><br><br>
  <button onclick="toggleAutoPlay()" id="autoPlayBtn">🔁 Auto Play All</button>
</div>

<audio id="ttsAudio" src="" hidden></audio>

<script>
function autoResize(textarea) {
  textarea.style.height = 'auto';
  textarea.style.height = textarea.scrollHeight + 'px';
}

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("textarea").forEach(function (el) {
    autoResize(el);
    el.addEventListener('input', function () {
      autoResize(this);
    });
  });
});

// Flashcard logic continues below...
const data = <?php echo json_encode($rows); ?>;
const tableName = <?php echo json_encode($selectedTable); ?>;
const targetLanguage = <?php echo json_encode($targetLanguage); ?>;

// rest of your existing script ...
</script>
</div>
</body>
</html>
