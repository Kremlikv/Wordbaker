<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';


// Handle quiz restart
if (isset($_POST['restart'])) {
    unset($_SESSION['score'], $_SESSION['question_index'], $_SESSION['questions'], $_SESSION['quiz_table'], $_SESSION['bg_music']);
    $_SESSION['mistakes'] = []; // Reset mistakes for a new game
    echo "<script>localStorage.removeItem('quiz_music_time'); localStorage.removeItem('quiz_music_src');</script>";
    header("Location: play_quiz.php");
    exit;
}

// First-time setup
if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['questions'] = []; 
}

// Load quiz tables
$quizTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'quiz_choices_') === 0) {
        $quizTables[] = $row[0];
    }
}

if (isset($_POST['start_new']) && !empty($_POST['quiz_table'])) {
    $_SESSION['mistakes'] = []; // <— THIS is the missing reset!
    $_SESSION['quiz_table'] = $_POST['quiz_table'];
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;

    // Music selection
    $musicChoice = $_POST['bg_music_choice'] ?? '';
    $customURL = $_POST['custom_music_url'] ?? '';
    if ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice !== '') {
        $_SESSION['bg_music'] = $musicChoice;
    } else {
        $_SESSION['bg_music'] = ''; // 🔇 OFF
    }

    $selectedTable = $_POST['quiz_table'];
    $res = $conn->query("SELECT * FROM `$selectedTable`");
    if (!$res) {
        die("❌ Query failed: " . $conn->error);
    }

    $questions = [];
    while ($row = $res->fetch_assoc()) {
        if (!isset($row['question'], $row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3'])) {
            echo "❌ Missing expected keys in row:";
            var_dump($row);
            exit;
        }

        $answers = [$row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3']];
        shuffle($answers);
        $questions[] = [
            'question' => $row['question'],
            'correct' => $row['correct_answer'],
            'answers' => $answers,
            'image' => $row['image_url'] ?? ''
        ];
    }

    if (empty($questions)) {
        die("⚠️ No questions found in table '$selectedTable'.");
    }

    shuffle($questions);
    $_SESSION['questions'] = $questions;
    header("Location: play_quiz.php");
    exit;
}

$selectedTable = $_SESSION['quiz_table'] ?? '';

// $musicSrc = $_SESSION['bg_music'] ?? 'background.mp3';
$musicSrc = $_SESSION['bg_music'];


include 'styling.php';
echo "👋 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Play Quiz</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 0px; }
        .question-box { font-size: 1.5em; margin-bottom: 20px; }
        .answer-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 600px;
            margin: auto;
        }
        .answer-col {
            flex: 0 0 50%;
            padding: 10px;
        }
        .answer-btn {
            width: 100%;
            padding: 20px;
            font-size: 1.1em;
            cursor: pointer;
            border: none;
            border-radius: 10px;
            background-color: #eee;
            transition: 0.3s;
        }
        .answer-btn:hover {
            background-color: #ddd;
        }
        .feedback { font-size: 1.2em; margin-top: 20px; }
        .score { margin-bottom: 10px; font-weight: bold; }
        .image-container { margin: 20px auto; }
        img.question-image { max-width: 80%; max-height: 300px; }
        select, button, input[type="url"] { padding: 10px; font-size: 1em; }
        #timer { font-size: 1.3em; color: darkred; margin: 10px; }
    </style>
</head>
<body>

<audio id="bgMusic" loop>
    <source id="bgMusicSource" src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
    Your browser does not support background music.
</audio>


<h1>🎯 Kahoot-style Quiz</h1>

<form method="POST">
    <label>Select background music:</label><br><br>
    <select name="bg_music_choice" onchange="toggleCustomMusic(this.value)">
        <option value="">🔇 OFF</option>
        <option value="track1.mp3">🎸 Track 1</option>
        <option value="track2.mp3">🎹 Track 2</option>
        <option value="track3.mp3">🥁 Track 3</option>
        <option value="custom">🌐 Use custom music URL</option>
    </select><br><br>

    <div id="customMusicInput" style="display:none;">
        <input type="url" name="custom_music_url" placeholder="Paste full MP3 URL (e.g., from freetouse.com)" style="width: 60%;">
    </div>

    <div style='text-align: center; margin-bottom: 20px;'>
    <br>
    <button type="button" onclick="previewMusic()">▶️ Preview Music</button>
    <audio id="previewPlayer" controls style="display:none; margin-top: 10px;"></audio>
    <button onclick="document.getElementById('bgMusic').play()">▶️ Play Music</button>
    <button onclick="document.getElementById('bgMusic').pause()">⏸️ Pause Music</button>
    </div>

    <br><br><label>Select quiz set:</label><br><br>
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

<?php if ($selectedTable): ?>
    <div id="quizBox"><!-- Question will load here --></div>
<?php endif; ?>

<script>
function toggleCustomMusic(value) {
    document.getElementById("customMusicInput").style.display = (value === "custom") ? "block" : "none";
}

function previewMusic() {
    const dropdown = document.querySelector('select[name="bg_music_choice"]');
    const urlInput = document.querySelector('input[name="custom_music_url"]');
    const player = document.getElementById('previewPlayer');
    let src = dropdown.value === "custom" ? urlInput.value.trim() : dropdown.value;

    if (src) {
        player.src = src;
        player.style.display = "block";
        player.play();
    }
}

let timeLeft;
let countdown;

function startTimer() {
    timeLeft = 15;
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
    const music = document.getElementById("bgMusic");
    const source = document.getElementById("bgMusicSource");
    const storedSrc = localStorage.getItem("quiz_music_src");
    const storedTime = parseFloat(localStorage.getItem("quiz_music_time")) || 0;

    if (source.src !== storedSrc) {
        localStorage.setItem("quiz_music_src", source.src);
        localStorage.setItem("quiz_music_time", 0);
    } else {
        music.currentTime = storedTime;
    }

    // music.volume = 0.3;
    // music.play().catch(err => console.warn("Autoplay blocked:", err));

    setInterval(() => {
        localStorage.setItem("quiz_music_time", music.currentTime);
    }, 1000);

    if (<?= json_encode((bool)$selectedTable) ?>) {
        loadNextQuestion();
    }
});

document.getElementById("startQuizBtn").addEventListener("click", function () {
    const music = document.getElementById("bgMusic");
    if (music?.src && music.src !== window.location.href) {
        music.volume = 0.3;
        music.play().catch(err => console.warn("Music play blocked:", err));
    }
});

</script>

</body>
</html>
