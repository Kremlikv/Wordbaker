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

// 🧹 Clean slate if button pressed
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

    // 📅 Load questions
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

    // Refresh to avoid form resubmission
    header("Location: play_quiz.php");
    exit;
}

include 'styling.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Play Quiz</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
<?php include 'style.css'; ?>
</style>
</head>
<body>
<div class='content'>
    <div class="content">
    👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>

<audio id="bgMusic" loop>
    <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
    Your browser does not support audio.
</audio>

<h1>🎯 Quiz</h1>

<form method="POST" style="display:inline-block;">
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
        <button type="submit" name="start_new" id="startQuizBtn">▶️ Start Quiz</button>
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
    const source = document.getElementById("bgMusicSource");
    if (!source.src || source.src.endsWith('/')) {
        alert("Please select a valid music track first.");
        return;
    }
    if (music.paused) {
        music.volume = 0.3;
        music.play().catch(err => console.warn("Music play blocked:", err));
    } else {
        music.pause();
    }
}

function showQuestionAndImage(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const question = temp.querySelector('.question-box');
    const image = temp.querySelector('.image-container');
    const score = temp.querySelector('.score');
    const timer = temp.querySelector('#timer');
    const progress = temp.querySelector('#progressBarContainer');

    const quizBox = document.getElementById("quizBox");
    quizBox.innerHTML = "";
    if (score) quizBox.appendChild(score);
    if (progress) quizBox.appendChild(progress);
    if (timer) quizBox.appendChild(timer);
    if (question) quizBox.appendChild(question);
    if (image) quizBox.appendChild(image);
}

function showAnswersAndStartTimer(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const answers = temp.querySelector('.answer-grid');
    const quizBox = document.getElementById("quizBox");
    if (answers) quizBox.appendChild(answers);
    startTimer();
}

function startTimer() {
    clearInterval(countdown);
    timeLeft = 15;
    const timerDisplay = document.getElementById("timer");
    const progressBar = document.getElementById("progressBar");
    countdown = setInterval(() => {
        timeLeft--;
        if (timerDisplay) timerDisplay.textContent = `⏳ ${timeLeft}`;
        if (progressBar) progressBar.style.width = (timeLeft / 15 * 100) + "%";
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
            if (timerDisplay) timerDisplay.textContent = "⏰ Time's up!";
        }
    }, 1000);
}

function highlightAnswerAndShowFeedback(userAnswer, html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const feedback = temp.querySelector('.feedback');
    const correct = feedback && feedback.textContent.includes("Wrong.")
        ? feedback.textContent.split("Correct answer: ")[1]
        : userAnswer;

    document.querySelectorAll(".answer-btn").forEach(btn => {
        if (btn.textContent === correct) {
            btn.style.backgroundColor = "#4CAF50";
            btn.style.color = "white";
        } else if (btn.getAttribute("data-value") === userAnswer) {
            btn.style.backgroundColor = "#f44336";
            btn.style.color = "white";
        }
    });

    if (feedback) {
        document.getElementById("quizBox").appendChild(feedback);
    }
}

function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    document.querySelectorAll(".answer-btn").forEach(b => b.disabled = true);
    clearInterval(countdown);

    fetch("load_question.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            answer: value,
            time_taken: 15 - timeLeft
        })
    })
    .then(res => res.text())
    .then(html => {
        highlightAnswerAndShowFeedback(value, html);
        setTimeout(() => {
            loadNextQuestion();
        }, 2000);
    });
}

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            showQuestionAndImage(html);
            setTimeout(() => showAnswersAndStartTimer(html), 2000);
        });
}

document.addEventListener("DOMContentLoaded", function () {
    if (<?= json_encode(!empty($_SESSION['questions'])) ?>) {
        loadNextQuestion();
    }
});
</script>
</div>
</div>
</body>
</html>
