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

// 🎥 Check if musicSrc is YouTube
$isYouTube = false;
$ytVideoId = '';
if (!empty($musicSrc)) {
    $musicSrcNoParams = preg_replace('/\?.*/', '', $musicSrc); // remove query params
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $musicSrcNoParams, $m) ||
        preg_match('/youtube\.com.*[?&]v=([a-zA-Z0-9_-]+)/', $musicSrcNoParams, $m)) {
        $ytVideoId = $m[1];
        $isYouTube = true;
    }
}

include 'styling.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Play Quiz</title>
<style>
    body { font-family: sans-serif; text-align: center; padding: 0px; padding-bottom: 80px;}
    .question-box { font-size: 1.5em; margin-bottom: 20px; }
    .answer-grid { display: flex; flex-wrap: wrap; justify-content: center; max-width: 600px; margin: auto; }
    .answer-col { flex: 0 0 50%; padding: 10px; }
    .answer-btn { width: 100%; padding: 20px; font-size: 1.1em; cursor: pointer; border: none; border-radius: 10px; background-color: #eee; transition: 0.3s; }
    .answer-btn:hover { background-color: #ddd; }
    .feedback { font-size: 1.2em; margin-top: 20px; }
    .score { margin-bottom: 10px; font-weight: bold; }
    .image-container { margin: 20px auto; }
    img.question-image { max-width: 80%; max-height: 300px; }
    select, button, input[type="url"] { padding: 10px; font-size: 1em; }
    #timer { font-size: 1.3em; color: darkred; margin: 10px; }
    .quiz-buttons { text-align: center; margin-top: 20px; }
    .quiz-buttons button {
        background-color: #d3d3d3;
        color: black;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 1em;
        cursor: pointer;
        margin: 0 5px;
    }
    .quiz-buttons button:hover {
        background-color: #bfbfbf;
    }
</style>
</head>
<body>

<div class="content">
    👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>

<?php if ($isYouTube): ?>
    <!-- YouTube Player (Hidden) -->
    <div id="ytPlayer"></div>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
        var player;
        function onYouTubeIframeAPIReady() {
            player = new YT.Player('ytPlayer', {
                height: '0',
                width: '0',
                videoId: '<?= $ytVideoId ?>',
                playerVars: { autoplay: 0, loop: 1, playlist: '<?= $ytVideoId ?>' }
            });
        }
        function toggleMusic() {
            if (!player) return;
            if (player.getPlayerState() === YT.PlayerState.PLAYING) {
                player.pauseVideo();
            } else {
                player.setVolume(30);
                player.playVideo();
            }
        }
        function previewMusic() {
            toggleMusic();
        }
<?php else: ?>
    </script>
    <!-- MP3 Player -->
    <audio id="bgMusic" loop>
        <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
    </audio>
    <script>
        function toggleMusic() {
            const music = document.getElementById("bgMusic");
            if (music.paused) {
                music.volume = 0.3;
                music.play().catch(err => console.warn("Music play blocked:", err));
            } else {
                music.pause();
            }
        }
        function previewMusic() {
            toggleMusic();
        }
<?php endif; ?>

// Start Quiz and Play Music in same click
function startQuizAndMusic(formId) {
    <?php if ($isYouTube): ?>
        if (player) {
            player.setVolume(30);
            player.playVideo();
        }
    <?php else: ?>
        const music = document.getElementById("bgMusic");
        if (music) {
            music.volume = 0.3;
            music.play().catch(err => console.warn("Music play blocked:", err));
        }
    <?php endif; ?>
    document.getElementById(formId).submit();
}
    </script>

<h1>🎯 Kahoot-style Quiz</h1>

<!-- Start Quiz Form -->
<form method="POST" id="startQuizForm" style="display:inline-block;">
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
        <input type="url" name="custom_music_url" placeholder="Paste full MP3 or YouTube URL" style="width: 60%;" value="<?= htmlspecialchars($currentMusic) ?>">
    </div>

    <!-- 🎧 Preview & ▶️/⏸️ Toggle Music Buttons -->
    <div style='margin-bottom: 20px;'>
        <button type="button" onclick="previewMusic()">🎧 Preview</button>
        <button type="button" onclick="toggleMusic()">▶️/⏸️ Toggle Music</button>
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

    <input type="hidden" name="start_new" value="1">
    <div class="quiz-buttons">
        <button type="button" onclick="startQuizAndMusic('startQuizForm')">▶️ Start Quiz</button>
    </div>
</form>

<!-- Clean Slate Form -->
<form method="POST" style="display:inline-block;">
    <div class="quiz-buttons">
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

function startTimer() {
    clearInterval(countdown);
    timeLeft = 15;
    const timerDisplay = document.getElementById("timer");
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
    document.querySelectorAll(".answer-btn").forEach(b => b.disabled = true);
    clearInterval(countdown);
    fetch("load_question.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ answer: value, time_taken: 15 - timeLeft })
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("quizBox").innerHTML = html;
        startTimer();
    });
}

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
            startTimer();
        });
}

document.addEventListener("DOMContentLoaded", function () {
    if (<?= json_encode(!empty($_SESSION['questions'])) ?>) { 
        loadNextQuestion();
    }
});
</script>

</div>
</body>
</html>
