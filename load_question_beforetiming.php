<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// ✅ Safety net — no quiz loaded
if (empty($_SESSION['questions']) || !isset($_SESSION['question_index'], $_SESSION['score'], $_SESSION['quiz_table'])) {
    echo "<p>⚠️ No active quiz found. Please go to <a href='play_quiz.php'>Play Quiz</a> and start a new game.</p>";
    exit;
}

$index      = $_SESSION['question_index'];
$questions  = $_SESSION['questions'];
$total      = count($questions);
$score      = $_SESSION['score'];

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

// Calculate timer based on correct answer word count
$wordCount = str_word_count($question['correct']);
$timeLimit = 15 + max(0, $wordCount - 1) * 5;

echo '<div class="score">Question ' . ($index + 1) . ' of ' . $total . ' | Score: ' . $_SESSION['score'] . '</div>';

// Progress bar container
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

echo '<div class="answer-grid">';
foreach ($answers as $a) {
    echo '<div class="answer-col">
            <button type="button" class="answer-btn" onclick="submitAnswer(this)" data-value="' . htmlspecialchars($a) . '">' . htmlspecialchars($a) . '</button>
          </div>';
}
echo '</div>';

if (!empty($_SESSION['feedback'])) {
    echo '<div class="feedback" id="feedbackBox">' . $_SESSION['feedback'] . '</div>';
    unset($_SESSION['feedback']);
}
?>

<script>
let timeLeft = <?= $timeLimit ?>;
let countdown = null;
const timerDisplay = document.getElementById("timer");
const progressBar = document.getElementById("progressBar");

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
        let percent = (timeLeft / <?= $timeLimit ?>) * 100;
        progressBar.style.width = percent + "%";
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
            timerDisplay.textContent = "⏰ Time's up!";
        }
    }, 1000);
}

// Delay timer start by 2 seconds for reading time
setTimeout(startTimer, 2000);

function submitAnswer(btn) {
    const value = btn.getAttribute("data-value");
    document.querySelectorAll(".answer-btn").forEach(b => b.disabled = true);

    fetch("load_question.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            answer: value,
            time_taken: <?= $timeLimit ?> - timeLeft
        })
    })
    .then(res => res.text())
    .then(html => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const feedback = tempDiv.querySelector('.feedback');
        const correctAnswer = feedback && feedback.textContent.includes('Wrong.')
            ? feedback.textContent.split('Correct answer: ')[1]
            : value;

        // Highlight answers
        document.querySelectorAll(".answer-btn").forEach(btn2 => {
            if (btn2.textContent === correctAnswer) {
                btn2.style.backgroundColor = "#4CAF50"; // green
                btn2.style.color = "white";
            } else if (btn2.getAttribute("data-value") === value) {
                btn2.style.backgroundColor = "#f44336"; // red
                btn2.style.color = "white";
            }
        });

        // Show feedback first, then load next question
        if (feedback) {
            document.getElementById("quizBox").innerHTML = feedback.outerHTML;
            setTimeout(() => {
                document.getElementById("quizBox").innerHTML = html;
            }, 1500);
        } else {
            document.getElementById("quizBox").innerHTML = html;
        }
    });
}
</script>
