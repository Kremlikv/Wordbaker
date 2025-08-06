<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';

// 📂 Get available quiz tables
$quizTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'quiz_choices_') === 0) {
        $quizTables[] = $row[0];
    }
}

$selectedTable = $_SESSION['quiz_table'] ?? '';
$musicSrc = $_SESSION['bg_music'] ?? '';

// 🧹 Clean slate
if (isset($_POST['clean_slate'])) {
    unset(
        $_SESSION['score'],
        $_SESSION['question_index'],
        $_SESSION['questions'],
        $_SESSION['quiz_table'],
        $_SESSION['bg_music'],
        $_SESSION['mistakes'],
        $_SESSION['feedback']
    );
    header("Location: play_quiz.php");
    exit;
}

// 🚀 Start Quiz
if (isset($_POST['start_new']) && !empty($_POST['quiz_table'])) {
    $_SESSION['quiz_table'] = $_POST['quiz_table'];
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['mistakes'] = [];

    // 🎵 Music choice
    $musicChoice = $_POST['bg_music_choice'] ?? '';
    $customURL   = $_POST['custom_music_url'] ?? '';
    if ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice !== '') {
        $_SESSION['bg_music'] = $musicChoice;
    } else {
        $_SESSION['bg_music'] = '';
    }
    $musicSrc = $_SESSION['bg_music'];

    // 📥 Load questions
    $selectedTable = $_POST['quiz_table'];
    $res = $conn->query("SELECT question, correct_answer, wrong1, wrong2, wrong3, image_url FROM `$selectedTable`");
    if (!$res) die("❌ Query failed: " . $conn->error);

    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $answers = [$row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3']];
        shuffle($answers);
        $questions[] = [
            'question' => $row['question'],
            'correct'  => $row['correct_answer'],
            'answers'  => $answers,
            'image'    => $row['image_url'] ?? ''
        ];
    }

    if (empty($questions)) {
        die("⚠️ No questions found in '$selectedTable'.");
    }

    shuffle($questions);
    $_SESSION['questions'] = $questions;

    header("Location: play_quiz.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Play Quiz</title>
<style>
body { font-family: sans-serif; text-align: center; margin:0; padding-bottom:80px; }
.question-box { font-size: clamp(1.2em, 4vw, 1.5em); margin-bottom: 20px; }
.answer-grid { display: flex; flex-wrap: wrap; justify-content: center; max-width: 600px; margin: auto; }
.answer-col { flex: 0 0 50%; padding: 10px; box-sizing: border-box; }
.answer-btn { width: 100%; padding: clamp(12px, 3vw, 20px); font-size: clamp(1em, 3vw, 1.1em); cursor: pointer; border: none; border-radius: 10px; background-color: #eee; transition: 0.3s; }
.answer-btn:hover { background-color: #ddd; }
.feedback { font-size: clamp(1em, 3vw, 1.2em); margin-top: 20px; }
.score { margin-bottom: 10px; font-weight: bold; }
.image-container { margin: 20px auto; }
img.question-image { max-width: 100%; height: auto; max-height: 50vh; }
select, button, input[type="url"] { padding: 10px; font-size: clamp(0.9em, 3vw, 1em); max-width: 90%; }
.quiz-buttons button { display:inline-flex; align-items:center; justify-content:center; gap:6px; background-color:#d3d3d3; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; white-space:nowrap; }
.quiz-buttons button:hover { background-color: #bfbfbf; }
@media (max-width: 500px) { .answer-col { flex: 0 0 100%; } }
</style>
</head>
<body>

👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>

<audio id="bgMusic" loop>
    <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
</audio>

<h1>🎯 Quiz</h1>

<form method="POST" style="display:inline-block;" onsubmit="startMusicOnClick()">
    <label>Select background music:</label><br><br>
    <?php $currentMusic = $_SESSION['bg_music'] ?? ''; ?>
    <select name="bg_music_choice" onchange="toggleCustomMusic(this.value)">
        <option value="" <?= $currentMusic === '' ? 'selected' : '' ?>>🔇 OFF</option>
        <option value="track1.mp3" <?= $currentMusic === 'track1.mp3' ? 'selected' : '' ?>>🎸 Track 1</option>
        <option value="track2.mp3" <?= $currentMusic === 'track2.mp3' ? 'selected' : '' ?>>🎹 Track 2</option>
        <option value="track3.mp3" <?= $currentMusic === 'track3.mp3' ? 'selected' : '' ?>>🥁 Track 3</option>
        <option value="custom" <?= filter_var($currentMusic, FILTER_VALIDATE_URL) ? 'selected' : '' ?>>🌐 Use custom music URL</option>
    </select><br><br>

    <div id="customMusicInput" style="<?= filter_var($currentMusic, FILTER_VALIDATE_URL) ? 'display:block;' : 'display:none;' ?>">
        <input type="url" name="custom_music_url" placeholder="Paste full MP3 URL" style="width: 60%;" value="<?= htmlspecialchars($currentMusic) ?>">
    </div>

    <div style='margin-bottom: 20px;'>
        <button type="button" onclick="previewMusic()">🎧 Preview</button>
        <button type="button" onclick="toggleMusic()">▶️/⏸️ Toggle Music</button>
        <audio id="previewPlayer" controls style="display:none; margin-top: 10px;"></audio>
    </div>

    <label>Select quiz set:</label><br><br>
    <select name="quiz_table" required>
        <option value="">-- Choose a quiz_choices_* table --</option>
        <?php foreach ($quizTables as $table): ?>
            <option value="<?= htmlspecialchars($table) ?>" <?= ($selectedTable === $table) ? 'selected' : '' ?>>
                <?= htmlspecialchars($table) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <div class="quiz-buttons">
        <button type="submit" name="start_new">▶️ Start Quiz</button>
</form>
<form method="POST" style="display:inline-block;">
        <button type="submit" name="clean_slate">🧹 Clean Slate</button>
    </div>
</form>

<hr>

<?php if (!empty($_SESSION['questions'])): ?>
    <div id="quizBox"></div>
<?php endif; ?>

<script>
function toggleCustomMusic(value) {
    document.getElementById("customMusicInput").style.display = (value === "custom") ? "block" : "none";
}

function previewMusic() {
    const dropdown = document.querySelector('select[name="bg_music_choice"]');
    const urlInput = document.querySelector('input[name="custom_music_url"]');
    const player = document.getElementById('previewPlayer');
    let src = (dropdown.value === "custom") ? urlInput.value.trim() : dropdown.value;
    if (src) {
        player.src = src;
        player.style.display = "block";
        player.play();
    }
}

function toggleMusic() {
    const music = document.getElementById("bgMusic");
    if (music.paused) {
        music.volume = 0.3;
        music.play().catch(()=>{});
    } else {
        music.pause();
    }
}

// 🎵 Start music when Start Quiz is clicked
function startMusicOnClick() {
    const music = document.getElementById("bgMusic");
    if (music.src) {
        music.volume = 0.3;
        music.play().catch(()=>{});
    }
}

// Called by load_question.php buttons
function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    fetch("load_question.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ answer: value })
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("quizBox").innerHTML = html;
    });
}

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
        });
}

document.addEventListener("DOMContentLoaded", function () {
    if (<?= json_encode(!empty($_SESSION['questions'])) ?>) { 
        loadNextQuestion();
    }
});
</script>
</body>
</html>
