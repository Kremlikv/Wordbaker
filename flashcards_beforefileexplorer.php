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
$selectedTable = $_GET['table'] ?? ($_SESSION['table'] ?? ($tables[0] ?? ''));
$_SESSION['table'] = $selectedTable;

$rows = [];
$column1 = '';
$column2 = '';
$targetLanguage = '';
$difficultOnly = isset($_GET['difficult_only']) && $_GET['difficult_only'] == '1';
$user_id = $_SESSION['user_id'] ?? null;

if (!empty($selectedTable)) {
    $result = $conn->query("SELECT * FROM `$selectedTable` LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $columns = $result->fetch_fields();
        $col1 = $columns[0]->name;
        $col2 = $columns[1]->name;

        $_SESSION['col1'] = $col1;
        $_SESSION['col2'] = $col2;

        if ($difficultOnly && $user_id) {
            $stmt = $conn->prepare("
                SELECT t.* FROM `$selectedTable` t
                JOIN difficult_words d ON t.`$col1` = d.source_word AND t.`$col2` = d.target_word
                WHERE d.user_id = ? AND d.table_name = ?
            ");
            $stmt->bind_param("is", $user_id, $selectedTable);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query("SELECT * FROM `$selectedTable`");
        }

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'cz' => $row[$col1],
                    'foreign' => $row[$col2],
                    'language' => $row['language'] ?? strtolower($col2)
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
  <title>Flashcards: <?= htmlspecialchars($selectedTable) ?></title>
  <style>
    body {
      font-family: Arial;
      text-align: center;
      padding: 0;
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
<p>👤 Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | <a href='logout.php'>Logout</a></p>

<form method="GET" style="margin-bottom: 10px;">
  <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
  <label><input type="checkbox" name="difficult_only" value="1" <?= $difficultOnly ? 'checked' : '' ?>> Show only my difficult words</label>
  <button type="submit">Apply</button>
</form>

<h2>Flashcards for Table: <?= htmlspecialchars($selectedTable) ?></h2>

<div id="card" class="card" onclick="flipCard()"></div>
<div class="controls">
  <button onclick="prevCard()">⬅️ Previous</button>
  <button onclick="markMastered()">✅ Mastered</button>
  <button onclick="markDifficult()">❌ Study more</button>
  <button onclick="nextCard()">Next ➡️</button><br><br>
  🔊 Czech Audio: <input type="checkbox" id="toggleCz" checked onchange="toggleTTS('cz')">
  🔊 Foreign Audio: <input type="checkbox" id="toggleForeign" checked onchange="toggleTTS('foreign')"><br><br>
  <button onclick="toggleAutoPlay()" id="autoPlayBtn">🔁 Auto Play All</button>
</div>

<audio id="ttsAudio" src="" hidden></audio>

<script>
const data = <?= json_encode($rows) ?>;
const tableName = <?= json_encode($selectedTable) ?>;
const targetLanguage = <?= json_encode($targetLanguage) ?>;

let index = 0;
let showingFront = true;
let frontText = data[0]?.cz ?? '';
let backText = data[0]?.foreign ?? '';
let ttsEnabled = { cz: true, foreign: true };
let autoPlay = false;
let playingNow = false;

const cardElement = document.getElementById('card'); 
const audioElement = document.getElementById('ttsAudio');

function getSnippetPath(index, side) {
  const num = String(index + 1).padStart(3, '0');
  const filename = `word_${num}${side}.mp3`;
  return `cache/${tableName}/${filename}`;
}



function playCachedAudio(index, side, fallbackText, fallbackLang, callback) {
  const src = getSnippetPath(index, side);

  // First, check if the file exists using fetch
  fetch(src, { method: 'HEAD' }).then(res => {
    if (res.ok) {
      audioElement.src = src;
    } else {
      audioElement.src = `generate_tts_snippet.php?text=${encodeURIComponent(fallbackText)}&lang=${encodeURIComponent(fallbackLang)}`;
    }
    audioElement.onended = callback;
    audioElement.play();
  }).catch(() => {
    // fallback in case of fetch failure
    audioElement.src = `generate_tts_snippet.php?text=${encodeURIComponent(fallbackText)}&lang=${encodeURIComponent(fallbackLang)}`;
    audioElement.onended = callback;
    audioElement.play();
  });
}




function playTTS(text, language) {
  if (!text || !language || !ttsEnabled[language]) return;
  const langCode = language === 'cz' ? 'czech' : (data[index].language || targetLanguage);
  playCachedAudio(index, language === 'cz' ? 'A' : 'B', text, langCode, () => {});
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
    body: `source_word=${encodeURIComponent(frontText)}&target_word=${encodeURIComponent(backText)}&language=${encodeURIComponent(data[index].language || targetLanguage)}&table_name=${encodeURIComponent(tableName)}`
  });
  nextCard();
}

function markMastered() {
  fetch('unmark_difficult.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `source_word=${encodeURIComponent(frontText)}&target_word=${encodeURIComponent(backText)}&table_name=${encodeURIComponent(tableName)}`
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
  const czText = data[index]?.cz ?? '';
  const foreignText = data[index]?.foreign ?? '';
  const lang = data[index]?.language || targetLanguage;

  cardElement.textContent = czText;
  showingFront = true;

  if (ttsEnabled.cz) {
    playCachedAudio(index, 'A', czText, 'czech', () => {
      if (ttsEnabled.foreign) {
        cardElement.textContent = foreignText;
        showingFront = false;
        playCachedAudio(index, 'B', foreignText, lang, () => {
          index++;
          setTimeout(playCardWithAudio, 1000);
        });
      } else {
        index++;
        setTimeout(playCardWithAudio, 1000);
      }
    });
  } else if (ttsEnabled.foreign) {
    cardElement.textContent = foreignText;
    showingFront = false;
    playCachedAudio(index, 'B', foreignText, lang, () => {
      index++;
      setTimeout(playCardWithAudio, 1000);
    });
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
