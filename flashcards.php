<?php
require_once 'db.php'; 
require_once 'session.php';
include 'styling.php';

// ----------------------
// FOLDER/TABLE FETCH LOGIC (like in main.php)
// ----------------------
function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts = explode('_', $suffix, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $folder = $parts[0];
                $file = $parts[1];
            } else {
                $folder = 'Uncategorized';
                $file = $suffix;
            }
            $allTables[$folder][] = [
                'table_name' => $table,
                'display_name' => $file
            ];
        }
    }
    return $allTables;
}

$username = strtolower($_SESSION['username'] ?? '');
// $conn->set_charset("utf8mb4");


// Put this near the top, right after you create $conn:
mysqli_report(MYSQLI_REPORT_OFF); // keep global quiet
$conn->set_charset("utf8mb4");
function fatal($msg){ echo "<pre style='color:#b00'>$msg</pre>"; exit; }




$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

// Build folderData for JS
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table' => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

// ----------------------
// LOAD SELECTED TABLE
// ----------------------
$selectedTable = $_POST['table'] ?? $_GET['table'] ?? ($_SESSION['table'] ?? '');
$_SESSION['table'] = $selectedTable;

$rows = [];
$column1 = '';
$column2 = '';
$targetLanguage = '';
$difficultOnly = isset($_GET['difficult_only']) && $_GET['difficult_only'] == '1';
$user_id = $_SESSION['user_id'] ?? null;

if (!empty($selectedTable)) {
    $res1 = $conn->query("SELECT * FROM `$selectedTable` LIMIT 1");
    if (!$res1) fatal("Probe query failed for `$selectedTable`: " . htmlspecialchars($conn->error));
    if ($res1->num_rows > 0) {
        $fields = $res1->fetch_fields();

        // Prefer data columns; skip meta
        $skip = ['id','language','created_at','updated_at','user_id'];
        $dataCols = [];
        foreach ($fields as $f) {
            $n = strtolower($f->name);
            if (!in_array($n, $skip, true)) $dataCols[] = $f->name;
        }

        // Pick 'Czech' explicitly if present, otherwise first data column
        $czechCol = null; $foreignCol = null;
        foreach ($dataCols as $c) if (strtolower($c) === 'czech') { $czechCol = $c; break; }
        if ($czechCol === null) $czechCol = $dataCols[0] ?? null;
        foreach ($dataCols as $c) if ($c !== $czechCol) { $foreignCol = $c; break; }

        if (!$czechCol || !$foreignCol) {
            fatal("Cannot determine word columns in `$selectedTable`. Expected a 'Czech' column plus one foreign-language column.");
        }

        $_SESSION['col1'] = $czechCol;
        $_SESSION['col2'] = $foreignCol;

        if ($difficultOnly && $user_id) {
           
            $sql = "
                SELECT t.*
                FROM `$selectedTable` AS t
                INNER JOIN `difficult_words` AS d
                  ON t.`$czechCol`   COLLATE utf8mb4_czech_ci = d.`source_word` COLLATE utf8mb4_czech_ci
                AND t.`$foreignCol` COLLATE utf8mb4_czech_ci = d.`target_word` COLLATE utf8mb4_czech_ci
                WHERE d.`user_id` = ? AND d.`table_name` = ?
            ";



            $stmt = $conn->prepare($sql);
            if (!$stmt) fatal("Prepare failed: " . htmlspecialchars($conn->error) . "\n\nSQL:\n$sql");
            $stmt->bind_param("is", $user_id, $selectedTable);
            if (!$stmt->execute()) fatal("Execute failed: " . htmlspecialchars($stmt->error) . "\n\nSQL:\n$sql");
            $result = $stmt->get_result();
            if (!$result) fatal("get_result failed: " . htmlspecialchars($conn->error));
        } else {
            $result = $conn->query("SELECT * FROM `$selectedTable`");
            if (!$result) fatal("Query failed: " . htmlspecialchars($conn->error));
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'cz'       => $row[$czechCol] ?? '',
                'foreign'  => $row[$foreignCol] ?? '',
                'language' => $row['language'] ?? strtolower($foreignCol),
            ];
        }

        $column1 = $czechCol;
        $column2 = $foreignCol;
        $targetLanguage = strtolower($foreignCol);
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

<h2>Flashcards for Table: <?= htmlspecialchars($selectedTable) ?></h2>

<!-- Include the reusable file explorer -->
<?php include 'file_explorer.php'; ?>

<?php if (!empty($selectedTable)): ?>
<form method="GET" style="margin: 20px 0;">
  <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
  <label><input type="checkbox" name="difficult_only" value="1" <?= $difficultOnly ? 'checked' : '' ?>> Show only my difficult words</label>
  <button type="submit">Apply</button>
</form>

<div id="card" class="card" onclick="flipCard()"></div>
<div class="controls">
  <button type="button" onclick="prevCard()">⬅️ Previous</button>
  <button type="button" onclick="markMastered()">✅ Mastered</button>
  <button type="button" onclick="markDifficult()">❌ Study more</button>
  <button type="button" onclick="nextCard()">Next ➡️</button><br><br>
  🔊 Czech Audio: <input type="checkbox" id="toggleCz" checked onchange="toggleTTS('cz')">
  🔊 Foreign Audio: <input type="checkbox" id="toggleForeign" checked onchange="toggleTTS('foreign')"><br><br>
  <button type="button" onclick="toggleAutoPlay()" id="autoPlayBtn">🔁 Auto Play All</button>
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
  fetch(src, { method: 'HEAD' }).then(res => {
    audioElement.src = res.ok ? src : `generate_tts_snippet.php?text=${encodeURIComponent(fallbackText)}&lang=${encodeURIComponent(fallbackLang)}`;
    audioElement.onended = callback;
    audioElement.play();
  }).catch(() => {
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
<?php endif; ?>
</div>
</body>
</html>
