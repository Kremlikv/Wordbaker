<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
require_once 'session.php';

// Get quiz tables
$quizTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'quiz_choices_') === 0) {
        $quizTables[] = $row[0];
    }
}

// Start quiz
if (isset($_POST['start_quiz']) && !empty($_POST['quiz_table'])) {
    $_SESSION['quiz_table'] = $_POST['quiz_table'];

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
<title>Quiz</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: sans-serif; text-align: center; margin:0; padding:20px; }
.answer-grid { display: flex; flex-wrap: wrap; max-width: 600px; margin:auto; justify-content:center; }
.answer-col { flex: 0 0 50%; padding: 10px; }
.answer-btn { width: 100%; padding: 15px; border-radius: 10px; font-size: 1.1em; border:none; cursor:pointer; background:#eee; }
.answer-btn:hover { background:#ddd; }
.feedback { font-size: 1.2em; margin-top: 20px; }
.hidden { display:none; }
.fade-in { opacity:0; transition:opacity 0.6s; }
.fade-in.show { opacity:1; }
</style>
</head>
<body>

<h1>🎯 Quiz</h1>

<?php if (empty($_SESSION['questions'])): ?>
<form method="POST" onsubmit="startMusic()">
    <select name="quiz_table" required>
        <option value="">-- choose quiz --</option>
        <?php foreach($quizTables as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
    </select><br><br>
    <button type="submit" name="start_quiz">▶️ Start Quiz</button>
</form>
<audio id="bgMusic" loop>
    <source src="track1.mp3" type="audio/mpeg">
</audio>
<?php else: ?>
<div id="quizBox"></div>
<audio id="bgMusic" loop>
    <source src="track1.mp3" type="audio/mpeg">
</audio>
<script>
let countdown = null;
let timeLeft = 15;

function startMusic() {
    const music = document.getElementById("bgMusic");
    music.volume = 0.3;
    music.play().catch(()=>{});
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
