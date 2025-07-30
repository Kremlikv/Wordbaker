<?php
require_once 'db.php'; 
require_once 'session.php';
include 'styling.php';

// Fetch tables 
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
$selectedTable = $_POST['table'] ?? ($tables[0] ?? '');

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
  🔊 Foreign Audio: <input type="checkbox" id="toggleForeign" checked onchange="toggleTTS('foreign')"><br><br>
  <button onclick="toggleAutoPlay()" id="autoPlayBtn">🔁 Auto Play All</button>
</div>

<audio id="ttsAudio" src="" hidden></audio>

<script>
const data = <?php echo json_encode($rows); ?>;
const tableName = <?php echo json_encode($selectedTable); ?>;

let index = 0;
let showingFront = true;
let frontText = data[0]?.cz ?? '';
let backText = data[0]?.foreign ?? '';
let ttsEnabled = { cz: true, foreign: true };
let autoPlay = false;
let playingNow = false;

const cardElement = document.getElementById('card'); 
const audioElement = document.getElementById('ttsAudio');

function getFileName(rowIndex, side) {
  const padded = String(rowIndex + 1).padStart(3, '0');
  const letter = side === 'cz' ? 'A' : 'B';
  return `cache/${tableName}/word_${padded}${letter}.mp3`;
}

function playFromFile(side) {
  if (!ttsEnabled[side]) return;
  const file = getFileName(index, side);
  audioElement.src = file;
  audioElement.play();
}

function updateCard() {
  frontText = data[index]?.cz ?? '';
  backText = data[index]?.foreign ?? '';
  showingFront = true;
  cardElement.textContent = frontText;
  setTimeout(() => playFromFile('cz'), 300);
}

function flipCard() {
  showingFront = !showingFront;
  cardElement.textContent = showingFront ? frontText : backText;
  setTimeout(() => playFromFile(showingFront ? 'cz' : 'foreign'), 300);
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
    body: `source_word=${encodeURIComponent(frontText)}&target_word=${encodeURIComponent(backText)}&language=${encodeURIComponent(data[index].language || '')}`
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

function toggleAutoPlay() {
  autoPlay = !autoPlay;
  document.getElementById('autoPlayBtn').textContent = autoPlay ? '⏸️ Stop Auto Play' : '🔁 Auto Play All';
  if (autoPlay && !playingNow) {
    index = 0;
    playCardWithAudio();
  }
}

function playCardWithAudio() {
  if (index >= data.length || !autoPlay) {
    playingNow = false;
    return;
  }

  playingNow = true;
  frontText = data[index]?.cz ?? '';
  backText = data[index]?.foreign ?? '';
  cardElement.textContent = frontText;
  showingFront = true;

  if (ttsEnabled.cz) {
    audioElement.src = getFileName(index, 'cz');
    audioElement.onended = () => {
      if (ttsEnabled.foreign) {
        cardElement.textContent = backText;
        showingFront = false;
        audioElement.src = getFileName(index, 'foreign');
        audioElement.onended = () => {
          index++;
          setTimeout(playCardWithAudio, 1000);
        };
        audioElement.play();
      } else {
        index++;
        setTimeout(playCardWithAudio, 1000);
      }
    };
    audioElement.play();
  } else if (ttsEnabled.foreign) {
    cardElement.textContent = backText;
    showingFront = false;
    audioElement.src = getFileName(index, 'foreign');
    audioElement.onended = () => {
      index++;
      setTimeout(playCardWithAudio, 1000);
    };
    audioElement.play();
  } else {
    index++;
    setTimeout(playCardWithAudio, 1000);
  }
}

updateCard();
</script>
</div>
</body>
</html>
