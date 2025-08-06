<?php
session_start();
require_once 'db.php';
require_once 'session.php';

if (empty($_SESSION['questions']) || !isset($_SESSION['question_index'], $_SESSION['score'], $_SESSION['quiz_table'])) {
    echo "<p>⚠️ No active quiz found. Please go to <a href='play_quiz.php'>Play Quiz</a> and start a new game.</p>";
    exit;
}

$index     = $_SESSION['question_index'];
$questions = $_SESSION['questions'];
$total     = count($questions);
$score     = $_SESSION['score'];

if (!isset($_SESSION['mistakes'])) {
    $_SESSION['mistakes'] = [];
}

// 📝 Process answer if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $question   = $questions[$index];
    $userAnswer = $_POST['answer'];
    $timeTaken  = intval($_POST['time_taken'] ?? 15);
    $bonus      = 0;

    if ($userAnswer === $question['correct']) {
        if     ($timeTaken <= 5)  $bonus = 3;
        elseif ($timeTaken <= 10) $bonus = 2;
        elseif ($timeTaken <= 15) $bonus = 1;
        $_SESSION['score'] += $bonus;
        $feedback = "✅ Correct! (+$bonus)";
    } else {
        $feedback = "❌ Wrong. Correct answer: " . htmlspecialchars($question['correct']);
        $_SESSION['mistakes'][] = [
            'question' => $question['question'],
            'correct'  => $question['correct'],
            'user'     => $userAnswer
        ];
    }

    $_SESSION['feedback'] = $feedback;
    $_SESSION['question_index']++;
    $index++;
}

// 🏁 End of quiz
if ($index >= $total) {
    echo "<h2>🌟 Quiz Completed!</h2>";
    echo "<p>Your final score: {$score} out of " . ($total * 3) . " points</p>";

    if (!empty($_SESSION['mistakes'])) {
        echo "<h3>🔍 Review Your Mistakes</h3>
              <table border='1' cellpadding='5'>
              <tr><th>Question</th><th>Your Answer</th><th>Correct</th></tr>";
        foreach ($_SESSION['mistakes'] as $m) {
            echo "<tr>
                    <td>" . htmlspecialchars($m['question']) . "</td>
                    <td>" . htmlspecialchars($m['user']) . "</td>
                    <td>" . htmlspecialchars($m['correct']) . "</td>
                  </tr>";
        }
        echo "</table><br>";
    }

    echo '<form method="POST" action="play_quiz.php">
            <input type="hidden" name="restart" value="1">
            <button type="submit">Play Again</button>
          </form>';

    unset($_SESSION['mistakes']);
    exit;
}

// 📜 Load current question
$question = $questions[$index];
$answers  = $question['answers'];
shuffle($answers);

$wordCount = str_word_count($question['correct']);
$timeLimit = 15 + max(0, $wordCount - 1) * 5;

echo '<style>
.fade-in { opacity: 0; transition: opacity 0.6s ease-in; }
.fade-in.show { opacity: 1; }
</style>';

echo '<div class="score">Question ' . ($index + 1) . ' of ' . $total . ' | Score: ' . $_SESSION['score'] . '</div>';

echo '<div id="progressBarContainer" style="width:100%; background:#ddd; height:10px; border-radius:5px; overflow:hidden; margin:5px auto;">
        <div id="progressBar" style="width:100%; height:100%; background:green;"></div>
      </div>';

echo '<div id="timer" style="color: green; font-weight: bold; margin-top:5px;">⏳ ' . $timeLimit . '</div>';
echo '<div class="question-box">🧠 ' . htmlspecialchars($question['question']) . '</div>';

if (!empty($question['image'])) {
    echo '<div class="image-container">
            <img src="' . htmlspecialchars($question['image']) . '" class="question-image">
          </div>';
}

echo '<div class="answer-grid fade-in">';
foreach ($answers as $a) {
    echo '<div class="answer-col">
            <button type="button" class="answer-btn"
                data-value="' . htmlspecialchars($a) . '"
                data-correct="' . ($a === $question['correct'] ? '1' : '0') . '"
                onclick="submitAnswer(this)">' . htmlspecialchars($a) . '</button>
          </div>';
}
echo '</div>';

echo '<div class="feedback fade-in" id="feedbackBox"></div>';
?>

<script>
let timeLeft = <?= $timeLimit ?>;
let countdown = null;
const timerDisplay = document.getElementById("timer");
const progressBar = document.getElementById("progressBar");
const answerGrid = document.querySelector(".answer-grid");
const feedbackBox = document.getElementById("feedbackBox");

function updateTimerColor() {
    if (timeLeft <= 5) {
        timerDisplay.style.color = "red";
        progressBar.style.backgroundColor = "red";
    } else if (timeLeft <= <?= floor($timeLimit / 2) ?>) {
        timerDisplay.style.color = "orange";
        progressBar.style.backgroundColor = "orange";
    } else {
        timerDisplay.style.color = "green";
        progressBar.style.backgroundColor = "green";
    }
}

function startTimer() {
    clearInterval(countdown);
    countdown = setInterval(() => {
        timeLeft--;
        updateTimerColor();
        timerDisplay.textContent = `⏳ ${timeLeft}`;
        progressBar.style.width = (timeLeft / <?= $timeLimit ?>) * 100 + "%";
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
            timerDisplay.textContent = "⏰ Time's up!";
            setTimeout(() => { loadNextQuestion(); }, 2000);
        }
    }, 1000);
}

// Hide answers initially, fade in after 2s, then start timer
answerGrid.classList.remove("show");
setTimeout(() => {
    answerGrid.classList.add("show");
    startTimer();
}, 2000);

function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    document.querySelectorAll(".answer-btn").forEach(b => b.disabled = true);
    clearInterval(countdown);

    // Highlight
    document.querySelectorAll(".answer-btn").forEach(b => {
        if (b.dataset.correct === "1") {
            b.style.backgroundColor = "#4CAF50"; b.style.color = "white";
        }
        if (b.getAttribute("data-value") === value && b.dataset.correct !== "1") {
            b.style.backgroundColor = "#f44336"; b.style.color = "white";
        }
    });

    // Feedback
    feedbackBox.textContent = btn.dataset.correct === "1"
        ? "✅ Correct!"
        : "❌ Wrong. Correct answer: " + document.querySelector('.answer-btn[data-correct="1"]').textContent;
    feedbackBox.classList.add("show");

    // Store result, then load next after 2s
    fetch("load_question.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ answer: value, time_taken: <?= $timeLimit ?> - timeLeft })
    }).then(() => {
        setTimeout(() => { loadNextQuestion(); }, 2000);
    });
}
</script>
