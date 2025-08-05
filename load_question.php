<?php

session_start();
// require_once 'session.php';
require_once 'db.php';

header("Content-Type: text/html; charset=UTF-8");

if (!isset($_SESSION['questions'], $_SESSION['question_index'], $_SESSION['score'], $_SESSION['quiz_table'])) {
    echo "<p>❌ Session expired. Please restart the quiz.</p>";
    exit;
}


$index = $_SESSION['question_index'];
$questions = $_SESSION['questions'];
$total = count($questions);
$score = $_SESSION['score'];
$quizTable = $_SESSION['quiz_table'];

if (!isset($_SESSION['mistakes'])) {
    $_SESSION['mistakes'] = [];
}

// Process answer if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $question = $questions[$index];
    $userAnswer = $_POST['answer'];
    $timeTaken = intval($_POST['time_taken'] ?? 15);
    $bonus = 0;

    if ($userAnswer === $question['correct']) {
        if ($timeTaken <= 5) $bonus = 3;
        elseif ($timeTaken <= 10) $bonus = 2;
        elseif ($timeTaken <= 15) $bonus = 1;
        $_SESSION['score'] += $bonus;
        $feedback = "✅ Correct! (+$bonus)";
    } else {
        $feedback = "❌ Wrong. Correct answer: " . htmlspecialchars($question['correct']);

        // Store mistake for later display
        $_SESSION['mistakes'][] = [
            'question' => $question['question'],
            'correct' => $question['correct'],
            'user' => $userAnswer
        ];
    }

    $_SESSION['feedback'] = $feedback;
    $_SESSION['question_index']++;
    $index++;
}

// End of quiz
if ($index >= $total) {
    echo "<h2>🌟 Quiz Completed!</h2>";
    echo "<p>Your final score: {$score} out of " . ($total * 3) . " points</p>";

    if (!empty($_SESSION['mistakes'])) {
        echo "<h3>🔍 Review Your Mistakes</h3><table border='1' cellpadding='5'><tr><th>Question</th><th>Your Answer</th><th>Correct</th></tr>";
        foreach ($_SESSION['mistakes'] as $m) {
            echo "<tr><td>" . htmlspecialchars($m['question']) . "</td><td>" . htmlspecialchars($m['user']) . "</td><td>" . htmlspecialchars($m['correct']) . "</td></tr>";
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

// Load current question
$question = $questions[$index];
$answers = $question['answers'];
shuffle($answers);

echo '<div class="score">Question ' . ($index + 1) . ' of ' . $total . ' | Score: ' . $_SESSION['score'] . '</div>';
echo '<div id="timer">⏳ 15</div>';
echo '<div class="question-box">🧠 ' . htmlspecialchars($question['question']) . '</div>';

if (!empty($question['image'])) {
    echo '<div class="image-container">
            <img src="' . htmlspecialchars($question['image']) . '" class="question-image">
          </div>';
}

echo '<div class="answer-grid">';
foreach ($answers as $a) {
    echo '<div class="answer-col">
            <button class="answer-btn" onclick="submitAnswer(this)" data-value="' . htmlspecialchars($a) . '">' . htmlspecialchars($a) . '</button>
          </div>';
}
echo '</div>';

if (!empty($_SESSION['feedback'])) {
    echo '<div class="feedback">' . $_SESSION['feedback'] . '</div>';
    unset($_SESSION['feedback']);
}
?>

<script>
let timeLeft = 15;
const timerDisplay = document.getElementById("timer");
const countdown = setInterval(() => {
    timeLeft--;
    timerDisplay.textContent = `⏳ ${timeLeft}`;
    if (timeLeft <= 0) {
        clearInterval(countdown);
        document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
        timerDisplay.textContent = "⏰ Time's up!";
    }
}, 1000);

function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    document.querySelectorAll(".answer-btn").forEach(b => b.disabled = true);
    fetch("load_question.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            answer: value,
            time_taken: 15 - timeLeft
        })
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("quizBox").innerHTML = html;
    });
}
</script>