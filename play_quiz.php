<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
require_once 'session.php';

if (isset($_POST['restart'])) {
    unset($_SESSION['score'], $_SESSION['question_index'], $_SESSION['questions'], $_SESSION['quiz_table'], $_SESSION['bg_music']);
    $_SESSION['mistakes'] = [];
    echo "<script>localStorage.removeItem('quiz_music_time'); localStorage.removeItem('quiz_music_src');</script>";
    header("Location: play_quiz.php");
    exit;
}

if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['questions'] = [];
}

$quizTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'quiz_choices_') === 0) {
        $quizTables[] = $row[0];
    }
}

$selectedTable = $_SESSION['quiz_table'] ?? '';
$musicSrc = $_SESSION['bg_music'] ?? '';

if (isset($_POST['start_new']) && !empty($_POST['quiz_table'])) {
    $_SESSION['mistakes'] = [];
    $_SESSION['quiz_table'] = $_POST['quiz_table'];
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;

    // Music choice
    $musicChoice = $_POST['bg_music_choice'] ?? '';
    $customURL = $_POST['custom_music_url'] ?? '';
    if ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice !== '') {
        $_SESSION['bg_music'] = $musicChoice;
    } else {
        $_SESSION['bg_music'] = '';
    }
    $musicSrc = $_SESSION['bg_music'];

    // Load quiz questions directly
    $selectedTable = $_SESSION['quiz_table'];
    $res = $conn->query("SELECT question, correct_answer, wrong1, wrong2, wrong3, image_url FROM `$selectedTable`");
    if (!$res) {
        die("❌ Query failed: " . $conn->error);
    }

    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $answers = array_filter([$row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3']]);
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

    // ✅ Redirect to start clean GET request for JS to load first question
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
<style>
    body { font-family: sans-serif; text-align: center; padding: 0px; }
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
</style>
</head>
<body>

<div class="content">
    👤 Logged in as <?= htmlspecialchars($_SESSION['username']) ?> | <a href='logout.php'>Logout</a>
</div>

<audio id="bgMusic" loop>
    <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
    Your browser does not support audio.
</audio>

<h1>🎯 Kahoot-style Quiz</h1>

<form method="POST">
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
    </select>
    <button type="submit" name="start_new" id="startQuizBtn">Start Quiz</button>
</form>

<hr>

<div id="quizBox"></div>

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

// Load first question via AJAX if quiz session exists
<?php if (!empty($_SESSION['questions'])): ?>
document.addEventListener("DOMContentLoaded", function () {
    loadNextQuestion();
});
<?php endif; ?>

function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
        });
}
</script>

</body>
</html>
