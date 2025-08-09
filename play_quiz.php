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
            if (stripos($href, 'http') !== 0) {
                $href = rtrim($freepdBase, '/') . '/' . ltrim($href, '/');
            }
            $label = trim(strip_tags($hit[2]));
            if ($label === '') {
                $label = urldecode(basename(parse_url($href, PHP_URL_PATH)));
            }
            $freepdTracks[$label] = $href; // avoid dupes by key
        }
        ksort($freepdTracks, SORT_NATURAL | SORT_FLAG_CASE);
    } else {
        $freepdFetchError = 'Could not parse FreePD track list.';
    }
} else {
    $freepdFetchError = 'FreePD is unreachable right now.';
}

// === File Explorer prep (mirrors main.php) ===
function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts  = explode('_', $suffix, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $folder = $parts[0];
                $file   = $parts[1];
            } else {
                $folder = 'Uncategorized';
                $file   = $suffix;
            }
            $allTables[$folder][] = [
                'table_name'   => $table,
                'display_name' => $file
            ];
        }
    }
    return $allTables;
}

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");

// Build folder structure
$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words',  'display_name' => 'Mastered Words'];

// Prepare folder data for file_explorer.php
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table'   => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

// Handle selection coming from the explorer
$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? ($_SESSION['table'] ?? '');
$column1 = '';
$column2 = '';

if (!empty($selectedFullTable)) {
    $res = $conn->query("SELECT * FROM `$selectedFullTable`");
    if ($res !== false) {
        $columns = $conn->query("SHOW COLUMNS FROM `$selectedFullTable`");
        if ($columns && $columns->num_rows >= 2) {
            $colData = $columns->fetch_all(MYSQLI_ASSOC);
            $column1 = $colData[0]['Field'];
            $column2 = $colData[1]['Field'];
        }
        // Make the app use this dataset
        $_SESSION['table'] = $selectedFullTable;
        $_SESSION['col1']  = $column1;
        $_SESSION['col2']  = $column2;
    }
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

// ====== PAGE OUTPUT (single valid HTML document) ======
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Play Quiz</title><meta name='viewport' content='width=device-width, initial-scale=1.0'>";

// Include site styling/header (your file outputs markup; we keep consistency with main.php)
include 'styling.php';

// Extra page-specific styles (kept minimal)
echo "<style>
    #quizBox { display:none; height:100vh; overflow-y:auto; box-sizing:border-box; padding:20px; }
    body { font-family:sans-serif; text-align:center; padding:0 0 80px 0; margin:0; }
    .question-box { font-size:clamp(1.2em,4vw,1.5em); margin-bottom:20px; }
    .answer-grid { display:flex; flex-wrap:wrap; justify-content:center; max-width:600px; margin:auto; }
    .answer-col { flex:0 0 50%; padding:10px; box-sizing:border-box; }
    .answer-btn { width:100%; padding:clamp(12px,3vw,20px); font-size:clamp(1em,3vw,1.1em); cursor:pointer; border:none; border-radius:10px; background:#eee; transition:0.3s; word-wrap:break-word; }
    .answer-btn:hover { background:#ddd; }
    .feedback { font-size:clamp(1em,3vw,1.2em); margin-top:20px; }
    .score { margin-bottom:10px; font-weight:bold; }
    .image-container { margin:20px auto; }
    img.question-image { width:100vw; height:auto; max-height:66vw; object-fit:contain; display:block; margin:0 auto; }
    @media (min-width:768px){ img.question-image { width:50vw; max-height:50vh; } }
    select, button, input[type=url] { padding:10px; font-size:clamp(0.9em,3vw,1em); max-width:90%; }
    #timer { font-size:clamp(1.1em,3.5vw,1.3em); color:darkred; margin:10px; }
    .quiz-buttons { text-align:center; margin-top:20px; }
    .quiz-buttons button { display:inline-flex; align-items:center; justify-content:center; gap:6px; background:#d3d3d3; color:#000; padding:10px 20px; border:none; border-radius:5px; font-size:clamp(0.9em,3vw,1em); cursor:pointer; margin:5px; white-space:nowrap; }
    .quiz-buttons button:hover { background:#bfbfbf; }
    @media (max-width:500px){ .answer-col { flex:0 0 100%; } }
</style>";

echo "</head><body>";

// ====== Explorer (same UX as main.php) ======
echo "<div class='content'>";
echo "<h2 style='margin-top:0;'>Choose a table for the quiz</h2>";
include 'file_explorer.php'; // expects $folders, $folderData, $selectedFullTable, $column1, $column2
echo "</div><br>";

// ====== Quiz UI ======
echo "
<div id='quizBox'></div>
<hr style='margin:30px 0;'>
<div class='content'>
    👤 Logged in as " . htmlspecialchars($_SESSION['username']) . " | <a href='logout.php'>Logout</a>
    <h1>🎯 Quiz</h1>

    <audio id='bgMusic' loop preload='auto'>
        <source id='bgMusicSource' src='" . htmlspecialchars($musicSrc) . "' type='audio/mpeg'>
        Your browser does not support audio.
    </audio>

    <form method='POST' style='display:block; margin-bottom:10px;'>
        <label>Select background music:</label><br><br>";
        $currentMusic = $_SESSION['bg_music'] ?? '';
        $isFreePDSelected = in_array($currentMusic, array_values($freepdTracks), true);
echo "  <select name='bg_music_choice' onchange='toggleMusicSources(this.value)'>
            <option value='' ".($currentMusic === '' ? 'selected' : '').">🔇 OFF</option>
            <option value='track1.mp3' ".($currentMusic === 'track1.mp3' ? 'selected' : '').">🎸 Track 1</option>
            <option value='track2.mp3' ".($currentMusic === 'track2.mp3' ? 'selected' : '').">🎹 Track 2</option>
            <option value='track3.mp3' ".($currentMusic === 'track3.mp3' ? 'selected' : '').">🥛 Track 3</option>
            <option value='freepd' ".($isFreePDSelected ? 'selected' : '').">🎼 FreePD library</option>
            <option value='custom' ".((!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'selected' : '').">🌐 Custom URL</option>
        </select>

        <div id='freepdSelectWrap' style='".($isFreePDSelected ? 'display:block;' : 'display:none;')."'>";
            if (!empty($freepdFetchError)) {
                echo "<div style='margin:8px 0;color:#a00;font-size:0.95em;'>
                        ⚠️ ".htmlspecialchars($freepdFetchError)." You can also open
                        <a href='https://freepd.com/music/' target='_blank' rel='noopener'>freepd.com/music</a>
                        and paste an MP3 link below.
                      </div>";
            }
echo "      <label for='freepdSelect' style='display:block;margin:8px 0 6px;'>Choose a FreePD track:</label>
            <select id='freepdSelect' name='freepd_music_url' style='width:100%;max-width:600px;'>
                <option value=''>-- Select from FreePD --</option>";
                foreach ($freepdTracks as $label => $url) {
                    $sel = ($currentMusic === $url) ? "selected" : "";
                    echo "<option value='".htmlspecialchars($url)."' $sel>".htmlspecialchars($label)."</option>";
                }
echo "      </select>
            <div style='margin-top:6px;font-size:0.9em;'>Tip: Preview below to make sure the track loads.</div>
        </div>

        <div id='customMusicInput' style='".((!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'display:block;' : 'display:none;')."'>
            <input type='url' name='custom_music_url' placeholder='Paste full MP3 URL' style='width:100%;max-width:600px;' value='".htmlspecialchars($currentMusic)."'>
        </div>

        <div style='margin:12px 0 20px;'>
            <button type='button' onclick='previewMusic()'>🎧 Preview</button>
            <button type='button' onclick='toggleMusic()'>▶️/⏸️ Toggle Music</button>
            <audio id='previewPlayer' controls style='display:none; margin-top: 10px;'></audio>
        </div>

        <label>Select quiz set:</label><br><br>
        <select name='quiz_table' required style='width:100%;max-width:600px;'>
            <option value=''>-- Choose a quiz_choices_* table --</option>";
            foreach ($quizTables as $table) {
                $sel = ($selectedTable === $table) ? "selected" : "";
                echo "<option value='".htmlspecialchars($table)."' $sel>".htmlspecialchars($table)."</option>";
            }
echo "  </select>

        <div class='quiz-buttons'>
            <button type='submit' name='start_new' id='startQuizBtn'>▶️ Start Quiz</button>
        </div>
    </form>

    <form method='POST' style='display:block;'>
        <div class='quiz-buttons'>
            <button type='submit' name='clean_slate'>🧹 Clean Slate</button>
        </div>
    </form>
</div>

<hr>

<script>
let countdown = null;
let timeLeft = 15;

// MUSIC
function toggleMusicSources(value) {
    document.getElementById('customMusicInput').style.display = (value === 'custom') ? 'block' : 'none';
    document.getElementById('freepdSelectWrap').style.display = (value === 'freepd') ? 'block' : 'none';
}
function previewMusic() {
    const sourceSel  = document.querySelector('select[name=\"bg_music_choice\"]');
    const customInput= document.querySelector('input[name=\"custom_music_url\"]');
    const freepdSel  = document.getElementById('freepdSelect');
    const player     = document.getElementById('previewPlayer');
    let src = '';
    if (sourceSel.value === 'custom') src = (customInput?.value || '').trim();
    else if (sourceSel.value === 'freepd') src = (freepdSel?.value || '').trim();
    else src = sourceSel.value;
    if (!src) { alert('Please pick a track first.'); return; }
    player.src = src;
    player.style.display = 'block';
    player.play().catch(err => console.warn('Preview blocked:', err));
}
function toggleMusic() {
    const music = document.getElementById('bgMusic');
    const source = document.getElementById('bgMusicSource');
    if (!source.src || source.src.endsWith('/')) { alert('Please select a valid music track first.'); return; }
    if (music.paused) { music.volume = 0.3; music.play().catch(err => console.warn('Music play blocked:', err)); }
    else { music.pause(); }
}

// QUIZ
function revealAnswers() {
    const grid = document.querySelector('.answer-grid');
    if (grid) { grid.style.display = 'flex'; startTimer(); }
}
function startTimer() {
    clearInterval(countdown);
    timeLeft = 15;
    const timerDisplay = document.getElementById('timer');
    countdown = setInterval(() => {
        timeLeft--;
        if (timerDisplay) timerDisplay.textContent = `⏳ ${timeLeft}`;
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll('.answer-btn').forEach(btn => btn.disabled = true);
            if (timerDisplay) timerDisplay.textContent = '⏰ Time\\'s up!';
        }
    }, 1000);
}
function submitAnswer(btn) {
    const value = btn.getAttribute('data-value');
    const buttons = document.querySelectorAll('.answer-btn');
    buttons.forEach(b => b.disabled = true);
    clearInterval(countdown);
    fetch('submit_answer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ answer: value, time_taken: 15 - timeLeft })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        const correctAnswer = data.correctAnswer;
        const feedbackText  = data.feedback;
        buttons.forEach(b => {
            const btnText = b.textContent.trim();
            if (btnText === correctAnswer) { b.style.backgroundColor = '#4CAF50'; b.style.color = 'white'; }
            else if (b.getAttribute('data-value') === value) { b.style.backgroundColor = '#f44336'; b.style.color = 'white'; }
        });
        const feedbackBox = document.getElementById('feedbackBox');
        if (feedbackBox) { feedbackBox.innerHTML = feedbackText; feedbackBox.style.display = 'block'; }
        setTimeout(() => {
            fetch('load_question.php')
                .then(res => res.text())
                .then(html => {
                    const quizBox = document.getElementById('quizBox');
                    quizBox.style.display = 'block';
                    quizBox.innerHTML = html;
                    setTimeout(revealAnswers, 2000);
                });
        }, 2000);
    });
}
function loadNextQuestion() {
    fetch('load_question.php')
        .then(res => res.text())
        .then(html => {
            const qb = document.getElementById('quizBox');
            qb.style.display = 'block';
            qb.innerHTML = html;
            setTimeout(revealAnswers, 2000);
        });
}
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.querySelector('select[name=\"bg_music_choice\"]');
    if (sel) toggleMusicSources(sel.value);
    const quizBox = document.getElementById('quizBox');
    " . (!empty($_SESSION['questions']) ? "
        quizBox.style.display = 'block';
        loadNextQuestion();
        setTimeout(() => {
            const music = document.getElementById('bgMusic');
            const source = document.getElementById('bgMusicSource');
            if (music && source && source.src) {
                music.volume = 0.3;
                music.play().catch(err => console.warn('Autoplay blocked by browser:', err));
            }
        }, 500);
    " : "
        quizBox.style.display = 'none';
    ") . "
});
window.addEventListener('beforeunload', function () {
    let isReload = false;
    if (performance.getEntriesByType) {
        const nav = performance.getEntriesByType('navigation')[0];
        isReload = nav && nav.type === 'reload';
    } else if (performance.navigation) {
        isReload = performance.navigation.type === 1;
    }
    if (!isReload) {
        navigator.sendBeacon('reset_quiz_session.php');
        const quizBox = document.getElementById('quizBox');
        if (quizBox) { quizBox.style.display = 'none'; quizBox.innerHTML = ''; }
    }
});
</script>";

echo "</body></html>";
