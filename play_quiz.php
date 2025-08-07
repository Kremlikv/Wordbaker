<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    require_once 'db.php';
    return;
}

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

// 🎵 Fetch FreePD tracks
$freepdUrl = 'https://freepd.com/music/';
$html = @file_get_contents($freepdUrl);
$freepdTracks = [];
if ($html !== false && preg_match_all('/href="([^"]+\.mp3)"/i', $html, $matches)) {
    $freepdTracks = array_unique($matches[1]);
    sort($freepdTracks);
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

// 🚀 Start quiz
if (isset($_POST['start_new']) && !empty($_POST['quiz_table'])) {
    $_SESSION['quiz_table'] = $_POST['quiz_table'];
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['mistakes'] = [];

    $chosenTrack = $_POST['freepd_choice'] ?? '';
    if (filter_var($chosenTrack, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $chosenTrack;
    } else {
        $_SESSION['bg_music'] = '';
    }

    $musicSrc = $_SESSION['bg_music'];

    // Load questions
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

include 'styling.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Play Quiz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Keep all your existing styles here (unchanged) */
    </style>
</head>
<body>

<div id="quizBox"></div>

<hr style="margin: 30px 0;">

<div class="content">
    👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>
    <h1>🎯 Quiz</h1>

    <audio id="bgMusic" loop preload="auto">
        <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
        Your browser does not support audio.
    </audio>

    <form method="POST">
        <label for="freepd_choice">Select background music from FreePD:</label><br><br>
        <select name="freepd_choice" id="freepd_choice" style="width:100%; max-width:600px;">
            <option value="">🔇 No Music</option>
            <?php foreach ($freepdTracks as $track): 
                $trackUrl = $freepdUrl . $track; ?>
                <option value="<?= htmlspecialchars($trackUrl) ?>" <?= $musicSrc === $trackUrl ? 'selected' : '' ?>>
                    <?= htmlspecialchars(urldecode($track)) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="quiz_table">Select quiz set:</label><br><br>
        <select name="quiz_table" id="quiz_table" required style="width: 100%; max-width: 600px;">
            <option value="">-- Choose a quiz_choices_* table --</option>
            <?php foreach ($quizTables as $table): ?>
                <option value="<?= htmlspecialchars($table) ?>" <?= ($selectedTable === $table) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($table) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <div class="quiz-buttons">
            <button type="submit" name="start_new">▶️ Start Quiz</button>
        </div>
    </form>

    <form method="POST">
        <div class="quiz-buttons">
            <button type="submit" name="clean_slate">🧹 Clean Slate</button>
        </div>
    </form>
</div>

<hr>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const quizBox = document.getElementById("quizBox");

    <?php if (!empty($_SESSION['questions'])): ?>
        quizBox.style.display = "block";
        loadNextQuestion();

        setTimeout(() => {
            const music = document.getElementById("bgMusic");
            const source = document.getElementById("bgMusicSource");

            if (music && source && source.src) {
                music.volume = 0.3;
                music.play().catch(err => {
                    console.warn("Autoplay blocked by browser:", err);
                });
            }
        }, 500);
    <?php else: ?>
        quizBox.style.display = "none";
    <?php endif; ?>
});

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
            setTimeout(revealAnswers, 2000);
        });
}

function revealAnswers() {
    const grid = document.querySelector(".answer-grid");
    if (grid) {
        grid.style.display = "flex";
        startTimer();
    }
}

function startTimer() {
    let countdown = null;
    let timeLeft = 15;
    const timerDisplay = document.getElementById("timer");
    clearInterval(countdown);
    countdown = setInterval(() => {
        timeLeft--;
        if (timerDisplay) timerDisplay.textContent = `⏳ ${timeLeft}`;
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
            if (timerDisplay) timerDisplay.textContent = "⏰ Time's up!";
        }
    }, 1000);
}

function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    const buttons = document.querySelectorAll(".answer-btn");
    buttons.forEach(b => b.disabled = true);
    clearInterval(countdown);

    fetch("submit_answer.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            answer: value,
            time_taken: 15 - timeLeft
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        const correctAnswer = data.correctAnswer;
        const feedbackText = data.feedback;

        buttons.forEach(b => {
            const btnText = b.textContent.trim();
            if (btnText === correctAnswer) {
                b.style.backgroundColor = "#4CAF50";
                b.style.color = "white";
            } else if (b.getAttribute("data-value") === value) {
                b.style.backgroundColor = "#f44336";
                b.style.color = "white";
            }
        });

        const feedbackBox = document.getElementById("feedbackBox");
        if (feedbackBox) {
            feedbackBox.innerHTML = feedbackText;
            feedbackBox.style.display = "block";
        }

        setTimeout(loadNextQuestion, 2000);
    });
}
</script>
</body>
</html>
