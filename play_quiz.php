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
        $_SESSION['bg_music']
    );
    header("Location: play_quiz.php");
    exit;
}

// 🚀 Start Quiz
if (isset($_POST['start_new']) && !empty($_POST['quiz_table'])) {
    $_SESSION['quiz_table'] = $_POST['quiz_table'];

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
    shuffle($questions);
    $_SESSION['questions'] = $questions;
    $_SESSION['q_index'] = 0;

    header("Location: play_quiz.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Play Quiz</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: sans-serif; text-align: center; margin:0; padding-bottom:80px; }
.answer-grid { display: flex; flex-wrap: wrap; max-width: 600px; margin:auto; justify-content:center; }
.answer-col { flex: 0 0 50%; padding: 10px; }
.answer-btn { width: 100%; padding: 15px; border-radius: 10px; font-size: 1.1em; border:none; cursor:pointer; background:#eee; }
.answer-btn:hover { background:#ddd; }
.feedback { font-size: 1.2em; margin-top: 20px; }
.hidden { display:none; }
.fade-in { opacity:0; transition:opacity 0.6s; }
.fade-in.show { opacity:1; }
@media (max-width: 500px) { .answer-col { flex: 0 0 100%; } }
</style>
</head>
<body>

👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>

<audio id="bgMusic" loop>
    <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
</audio>

<h1>🎯 Quiz</h1>

<?php if (empty($_SESSION['questions'])): ?>
<form method="POST" onsubmit="startMusicOnClick()">
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

    <button type="submit" name="start_new">▶️ Start Quiz</button>
</form>
<?php else: ?>
<div id="quizBox"></div>
<script>
let countdown = null;
let timeLeft = 15;

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

function startMusicOnClick() {
    const music = document.getElementById("bgMusic");
    if (music.src) {
        music.volume = 0.3;
        music.play().catch(()=>{});
    }
}

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
            const grid = document.querySelector(".answer-grid");
            grid.classList.remove("show");
            setTimeout(() => {
                grid.classList.add("show");
                startTimer();
            }, 2000);
        });
}

function startTimer() {
    clearInterval(countdown);
    timeLeft = 15;
    document.getElementById("timer").textContent = "⏳ " + timeLeft;
    countdown = setInterval(() => {
        timeLeft--;
        document.getElementById("timer").textContent = "⏳ " + timeLeft;
        if (timeLeft <= 0) {
            clearInterval(countdown);
            disableButtons();
            setTimeout(loadNextQuestion, 2000);
        }
    }, 1000);
}

function disableButtons() {
    document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
}

function submitAnswer(btn) {
    disableButtons();
    if (btn.dataset.correct === "1") {
        btn.style.backgroundColor = "lightgreen";
        document.getElementById("feedback").textContent = "✅ Correct!";
    } else {
        btn.style.backgroundColor = "lightcoral";
        const correctBtn = document.querySelector('.answer-btn[data-correct="1"]');
        if (correctBtn) correctBtn.style.backgroundColor = "lightgreen";
        document.getElementById("feedback").textContent = "❌ Wrong!";
    }
    clearInterval(countdown);
    setTimeout(loadNextQuestion, 2000);
}

document.addEventListener("DOMContentLoaded", loadNextQuestion);
</script>
<?php endif; ?>

</body>
</html>
