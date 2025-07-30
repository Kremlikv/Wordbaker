<?php
require_once 'db.php'; 
require_once 'session.php';
include 'styling.php';

// Fetch tables
// function getTables($conn) {
//    $tables = [];
//    $result = $conn->query("SHOW TABLES");
//    if ($result) {
//        while ($row = $result->fetch_array()) {
//            $tables[] = $row[0];
//        }
//    }
//    return $tables;
// }

// $tables = getTables($conn);
// $selectedTable = $_POST['table'] ?? ($tables[0] ?? '');

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
  </style>
</head>
<body>

<div class='content'>
<p>👤 Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> | <a href='logout.php'>Logout</a></p>

<!-- Table selection form -->
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
  🔊 Foreign Audio: <input type="checkbox" id="toggleForeign" checked onchange="toggleTTS('foreign')">
</div>

<audio id="ttsAudio" src="" hidden></audio>

<script>
const data = <?php echo json_encode($rows); ?>;
const tableName = <?php echo json_encode($selectedTable); ?>;
const targetLanguage = <?php echo json_encode($targetLanguage); ?>;

let index = 0;
let showingFront = true;
let frontText = data[0]?.cz ?? '';
let backText = data[0]?.foreign ?? '';
let ttsEnabled = { cz: true, foreign: true };

const cardElement = document.getElementById('card');
const audioElement = document.getElementById('ttsAudio');

function playTTS(text, language) {
  if (!text || !language || !ttsEnabled[language]) return;
  const url = `generate_tts_snippet.php?text=${encodeURIComponent(text)}&lang=${encodeURIComponent(language === 'cz' ? 'czech' : (data[index].language || targetLanguage))}`;
  audioElement.src = url;
  audioElement.play();
}

function updateCard() {
  frontText = data[index]?.cz ?? '';
  backText = data[index]?.foreign ?? '';
  showingFront = true;
  cardElement.textContent = frontText;
  setTimeout(() => playTTS(frontText, 'cz'), 300);
}

function flipCard() {
  showingFront = !showingFront;
  cardElement.textContent = showingFront ? frontText : backText;
  setTimeout(() => playTTS(showingFront ? frontText : backText, showingFront ? 'cz' : 'foreign'), 300);
}

function nextCard() {
  if (index < data.length - 1) {
    index++;
    updateCard();
  }
}

function prevCard() {
  if (index > 0) {
    index--;
    updateCard();
  }
}

function markDifficult() {
  fetch('mark_difficult.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `source_word=${encodeURIComponent(frontText)}&target_word=${encodeURIComponent(backText)}&language=${encodeURIComponent(data[index].language || targetLanguage)}`
  });
  nextCard();
}

function markKnown() {
  fetch('unmark_difficult.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `source_word=${encodeURIComponent(frontText)}&target_word=${encodeURIComponent(backText)}`
  });
  nextCard();
}

function toggleTTS(side) {
  ttsEnabled[side] = !ttsEnabled[side];
}

updateCard();
</script>
</div>
</body>
</html>
