<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ⛔ Temporarily disable session checking for AJAX fetch
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    // Skip session check logic inside session.php during AJAX
    require_once 'db.php';
    return;
}


require_once 'db.php';
require_once 'session.php';

// 🎵 Build a dropdown of FreePD tracks (server-side fetch)
$freepdTracks = [];
$freepdFetchError = '';
$freepdBase = 'https://freepd.com/music/';

$ctx = stream_context_create([
    'http' => [
        'timeout' => 7,
        'user_agent' => 'Mozilla/5.0 (QuizApp FreePD Fetch)'
    ]
]);

$freepdHtml = @file_get_contents($freepdBase, false, $ctx);
if ($freepdHtml !== false) {
    // Find any <a href="...mp3">Title</a>
    if (preg_match_all('#<a[^>]+href="([^"]+\.mp3)"[^>]*>(.*?)</a>#is', $freepdHtml, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $href = html_entity_decode($hit[1], ENT_QUOTES | ENT_HTML5);
            // make absolute
            if (stripos($href, 'http') !== 0) {
                $href = rtrim($freepdBase, '/') . '/' . ltrim($href, '/');
            }
            $label = trim(strip_tags($hit[2]));
            if ($label === '') {
                // fall back to the filename if no label
                $label = urldecode(basename(parse_url($href, PHP_URL_PATH)));
            }
            // Avoid duplicates
            $freepdTracks[$label] = $href;
        }
        // Sort by title
        ksort($freepdTracks, SORT_NATURAL | SORT_FLAG_CASE);
    } else {
        $freepdFetchError = 'Could not parse FreePD track list.';
    }
} else {
    $freepdFetchError = 'FreePD is unreachable right now.';
}



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

    // 🎵 Music choice (now supports FreePD dropdown)
    $musicChoice    = $_POST['bg_music_choice'] ?? '';
    $customURL      = trim($_POST['custom_music_url'] ?? '');
    $freepdURLSel   = trim($_POST['freepd_music_url'] ?? '');

    if ($musicChoice === 'freepd' && filter_var($freepdURLSel, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $freepdURLSel;
    } elseif ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice !== '') { // builtin tracks like track1.mp3
        $_SESSION['bg_music'] = $musicChoice;
    } else {
        $_SESSION['bg_music'] = '';
    }

  
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
    
    #quizBox {
        display: none;
        height: 100vh;
        overflow-y: auto;
        box-sizing: border-box;
        padding: 20px;
    }

    body {
        font-family: sans-serif;
        text-align: center;
        padding: 0;
        padding-bottom: 80px;
        margin: 0;
    }
    .question-box {
        font-size: clamp(1.2em, 4vw, 1.5em);
        margin-bottom: 20px;
    }
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
        box-sizing: border-box;
    }
    .answer-btn {
        width: 100%;
        padding: clamp(12px, 3vw, 20px);
        font-size: clamp(1em, 3vw, 1.1em);
        cursor: pointer;
        border: none;
        border-radius: 10px;
        background-color: #eee;
        transition: 0.3s;
        word-wrap: break-word;
    }
    .answer-btn:hover {
        background-color: #ddd;
    }
    .feedback {
        font-size: clamp(1em, 3vw, 1.2em);
        margin-top: 20px;
    }
    .score {
        margin-bottom: 10px;
        font-weight: bold;
    }
    .image-container {
        margin: 20px auto;
    }
    img.question-image {
        max-width: 100%;
        height: auto;
        max-height: 66vw;  /* ✅ percentage of viewport width */
        object-fit: contain;
    }


    img.question-image {
    width: 100vw;
    height: auto;
    max-height: 66vw;
    object-fit: contain;
    display: block;
    margin: 0 auto;
    }

    @media (min-width: 768px) {
    img.question-image {
        width: 50vw;
        max-height: 50vh;
    }
    }



    select, button, input[type="url"] {
        padding: 10px;
        font-size: clamp(0.9em, 3vw, 1em);
        max-width: 90%;
    }
    #timer {
        font-size: clamp(1.1em, 3.5vw, 1.3em);
        color: darkred;
        margin: 10px;
    }
    .quiz-buttons {
        text-align: center;
        margin-top: 20px;
    }
    .quiz-buttons button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background-color: #d3d3d3;
        color: black;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: clamp(0.9em, 3vw, 1em);
        cursor: pointer;
        margin: 5px;
        white-space: nowrap;
    }
    .quiz-buttons button:hover {
        background-color: #bfbfbf;
    }
    @media (max-width: 500px) {
        .answer-col {
            flex: 0 0 100%;
        }
    }
</style>
</head>
<body>

<!-- QUIZ AREA FIRST -->
<div id="quizBox"></div>

<hr style="margin: 30px 0;">

<!-- HEADER + CONTROLS BELOW QUIZ -->
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

        <?php
        $currentMusic = $_SESSION['bg_music'] ?? '';
        $isFreePDSelected = in_array($currentMusic, array_values($freepdTracks), true);
        ?>

        <select name="bg_music_choice" onchange="toggleMusicSources(this.value)">
            <option value="" <?= $currentMusic === '' ? 'selected' : '' ?>>🔇 OFF</option>
            <option value="track1.mp3" <?= $currentMusic === 'track1.mp3' ? 'selected' : '' ?>>🎸 Track 1</option>
            <option value="track2.mp3" <?= $currentMusic === 'track2.mp3' ? 'selected' : '' ?>>🎹 Track 2</option>
            <option value="track3.mp3" <?= $currentMusic === 'track3.mp3' ? 'selected' : '' ?>>🥛 Track 3</option>
            <option value="freepd" <?= $isFreePDSelected ? 'selected' : '' ?>>🎼 FreePD library</option>
            <option value="custom" <?= (!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'selected' : '' ?>>
                🌐 Custom URL (e.g. <a href="https://freepd.com/">freepd.com</a>)
            </option>
        </select>

        <div id="freepdSelectWrap" style="<?= $isFreePDSelected ? 'display:block;' : 'display:none;' ?>">
            <?php if (!empty($freepdFetchError)): ?>
                <div style="margin:8px 0;color:#a00;font-size:0.95em;">
                    ⚠️ <?= htmlspecialchars($freepdFetchError) ?> You can also open
                    <a href="https://freepd.com/music/" target="_blank" rel="noopener">freepd.com/music</a>
                    and paste an MP3 link below.
                </div>
            <?php endif; ?>

            <label for="freepdSelect" style="display:block;margin:8px 0 6px;">Choose a FreePD track:</label>
            <select id="freepdSelect" name="freepd_music_url" style="width:100%;max-width:600px;">
                <option value="">-- Select from FreePD --</option>
                <?php foreach ($freepdTracks as $label => $url): ?>
                    <option value="<?= htmlspecialchars($url) ?>" <?= ($currentMusic === $url) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="margin-top:6px;font-size:0.9em;">
                Tip: Preview below to make sure the track loads (some hosts need a moment).
            </div>
        </div>


        <div id="customMusicInput" style="<?= (!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'display:block;' : 'display:none;' ?>">
            <input type="url" name="custom_music_url" placeholder="Paste full MP3 URL"
                style="width: 100%; max-width: 600px;"
                value="<?= htmlspecialchars($currentMusic) ?>">
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
        </div> <!-- .quiz-buttons -->
    </form>
</div> <!-- ✅ only one .content div closes here -->


<hr>

<script>
let countdown = null;
let timeLeft = 15;

// MUSIC FUNCTIONS 

function toggleCustomMusic(value) {
    document.getElementById("customMusicInput").style.display = (value === "custom") ? "block" : "none";
}


function toggleMusicSources(value) {
    document.getElementById("customMusicInput").style.display = (value === "custom") ? "block" : "none";
    document.getElementById("freepdSelectWrap").style.display = (value === "freepd") ? "block" : "none";
}

function previewMusic() {
    const sourceSel = document.querySelector('select[name="bg_music_choice"]');
    const customInput = document.querySelector('input[name="custom_music_url"]');
    const freepdSel  = document.getElementById('freepdSelect');
    const player     = document.getElementById('previewPlayer');

    let src = '';
    if (sourceSel.value === 'custom') {
        src = (customInput?.value || '').trim();
    } else if (sourceSel.value === 'freepd') {
        src = (freepdSel?.value || '').trim();
    } else {
        // built-in choices (track1.mp3 etc.)
        src = sourceSel.value;
    }

    if (!src) {
        alert("Please pick a track first.");
        return;
    }

    player.src = src;
    player.style.display = "block";
    player.play().catch(err => console.warn("Preview blocked:", err));
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

// QUIZ FUNCTIONS 

function revealAnswers() {
    const grid = document.querySelector(".answer-grid");
    if (grid) {
        grid.style.display = "flex";
        startTimer();
    }
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

        // Highlight buttons
        buttons.forEach(b => {
            const btnText = b.textContent.trim();
            if (btnText === correctAnswer) {
                b.style.backgroundColor = "#4CAF50"; // green
                b.style.color = "white";
            } else if (b.getAttribute("data-value") === value) {
                b.style.backgroundColor = "#f44336"; // red
                b.style.color = "white";
            }
        });

        // Show feedback
        const feedbackBox = document.getElementById("feedbackBox");
        if (feedbackBox) {
            feedbackBox.innerHTML = feedbackText;
            feedbackBox.style.display = "block";
        }

        // Load next question after delay
        setTimeout(() => {
            fetch("load_question.php")
                .then(res => res.text())
                .then(html => {
                    const quizBox = document.getElementById("quizBox");
                    quizBox.style.display = "block";  // ✅ make it visible
                    quizBox.innerHTML = html;
                    setTimeout(revealAnswers, 2000);
                });
        }, 2000);
    });
}


function loadNextQuestion() {
    fetch("load_question.php")
        .then(res => res.text())
        .then(html => {
            document.getElementById("quizBox").innerHTML = html;
            setTimeout(revealAnswers, 2000);
        });
}

document.addEventListener("DOMContentLoaded", function () {
    const sel = document.querySelector('select[name="bg_music_choice"]');
    if (sel) toggleMusicSources(sel.value);
    const quizBox = document.getElementById("quizBox");

    <?php if (!empty($_SESSION['questions'])): ?>
        // Quiz already in progress, show and load question
        quizBox.style.display = "block";
        loadNextQuestion();

        // ✅ Always attempt to autoplay music
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


/*

document.addEventListener("DOMContentLoaded", function () {
    const quizBox = document.getElementById("quizBox");

    <?php if (!empty($_SESSION['questions'])): ?>
        // Quiz already in progress, show and load question
        quizBox.style.display = "block";
        loadNextQuestion();
    <?php else: ?>
        // No active quiz, keep quiz box hidden
        quizBox.style.display = "none";
    <?php endif; ?>
});

*/

window.addEventListener('beforeunload', function (e) {
    // Detect whether this is a reload (safe fallback for all browsers)
    let isReload = false;

    if (performance.getEntriesByType) {
        const nav = performance.getEntriesByType("navigation")[0];
        isReload = nav && nav.type === "reload";
    } else if (performance.navigation) {
        isReload = performance.navigation.type === 1; // TYPE_RELOAD = 1
    }

    // If it's not a reload, then clear session and hide quiz box
    if (!isReload) {
        navigator.sendBeacon('reset_quiz_session.php');

        const quizBox = document.getElementById('quizBox');
        if (quizBox) {
            quizBox.style.display = 'none';
            quizBox.innerHTML = '';
        }
    }
}); 
</script>


</div>
</div>
</div>

</body>
</html>