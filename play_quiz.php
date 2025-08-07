<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ⛔ Skip session check for AJAX
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
if ($html !== false) {
    if (preg_match_all('/href="([^"]+\.mp3)"/i', $html, $matches)) {
        $freepdTracks = array_unique($matches[1]);
        sort($freepdTracks);
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

    // 🎵 Music logic
    $musicChoice = $_POST['bg_music_choice'] ?? '';
    $customURL = $_POST['custom_music_url'] ?? '';
    $freepdChoice = $_POST['freepd_choice'] ?? '';

    if ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice === 'freepd' && filter_var($freepdChoice, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $freepdChoice;
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

include 'styling.php';
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Play Quiz</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* ... keep all your previous styles unchanged ... */
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

    <form method="POST" style="display:block;">
        <label>Select background music:</label><br><br>
        <?php $currentMusic = $_SESSION['bg_music'] ?? ''; ?>
        <select name="bg_music_choice" onchange="toggleCustomMusic(this.value)">
            <option value="" <?= $currentMusic === '' ? 'selected' : '' ?>>🔇 OFF</option>
            <option value="track1.mp3" <?= $currentMusic === 'track1.mp3' ? 'selected' : '' ?>>🎸 Track 1</option>
            <option value="track2.mp3" <?= $currentMusic === 'track2.mp3' ? 'selected' : '' ?>>🎹 Track 2</option>
            <option value="track3.mp3" <?= $currentMusic === 'track3.mp3' ? 'selected' : '' ?>>🥛 Track 3</option>
            <option value="custom" <?= (filter_var($currentMusic, FILTER_VALIDATE_URL) && !str_contains($currentMusic, 'freepd.com')) ? 'selected' : '' ?>>🌐 Custom URL</option>
            <option value="freepd" <?= str_contains($currentMusic, 'freepd.com') ? 'selected' : '' ?>>🎶 Choose from FreePD.com</option>
        </select>

        <!-- Custom URL Input -->
        <div id="customMusicInput" style="<?= (filter_var($currentMusic, FILTER_VALIDATE_URL) && !str_contains($currentMusic, 'freepd.com')) ? 'display:block;' : 'display:none;' ?>">
            <input type="url" name="custom_music_url" placeholder="Paste full MP3 URL" style="width: 100%; max-width: 600px;" value="<?= htmlspecialchars($currentMusic) ?>">          
        </div>

        <!-- FreePD Dropdown + Preview -->
        <div id="freepdSelector" style="<?= str_contains($currentMusic, 'freepd.com') ? 'display:block;' : 'display:none;' ?>">
            <select id="freepdTrackSelect" name="freepd_choice" style="width:100%; max-width:600px; margin-top:10px;">
                <option value="">-- Select a FreePD track --</option>
                <?php foreach ($freepdTracks as $track): 
                    $trackUrl = $freepdUrl . $track; ?>
                    <option value="<?= htmlspecialchars($trackUrl) ?>" <?= $musicSrc === $trackUrl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($track) ?>
                    </option>
                <?php endforeach; ?>
            </select><br>
            <button type="button" onclick="previewFreepdTrack()">🎧 Preview Selected</button>
            <audio id="freepdPreviewPlayer" controls style="display:none; margin-top:10px;"></audio>
        </div>

        <div style='margin-bottom: 20px;'>
            <button type="button" onclick="previewMusic()">🎧 Preview</button>
            <button type="button" onclick="toggleMusic()">▶️/⏸️ Toggle Music</button>
            <audio id="previewPlayer" controls style="display:none; margin-top: 10px;"></audio>
        </div>

        <label>Select quiz set:</label><br><br>
        <select name="quiz_table" required style="width: 100%; max-width: 600px;">
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

    <form method="POST" style="display:block;">
        <button type="submit" name="clean_slate">🧹 Clean Slate</button>
        </div>
    </form>
</div>

<hr>

<script>
function toggleCustomMusic(value) {
    document.getElementById("customMusicInput").style.display = (value === "custom") ? "block" : "none";
    document.getElementById("freepdSelector").style.display = (value === "freepd") ? "block" : "none";
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

function previewFreepdTrack() {
    const select = document.getElementById('freepdTrackSelect');
    const audio = document.getElementById('freepdPreviewPlayer');
    const url = select.value;

    if (url && url.endsWith('.mp3')) {
        audio.src = url;
        audio.style.display = 'block';
        audio.play().catch(err => {
            console.warn("Preview failed:", err);
        });
    } else {
        alert("Please select a valid FreePD MP3 track first.");
    }
}
</script>

<!-- Rest of your existing JS remains unchanged (quiz logic, timer, answer handling, etc.) -->

</body>
</html>
