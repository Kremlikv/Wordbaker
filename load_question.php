<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// ✅ No quiz in progress
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

// 🔄 Load current question
$question = $questions[$index];
$answers  = $question['answers'];
shuffle($answers);

// ⏱️ Time limit based on correct answer length
$wordCount = str_word_count($question['correct']);
$timeLimit = 15 + max(0, $wordCount - 1) * 5;
?>

<div class="score">Question <?= ($index + 1) ?> of <?= $total ?> | Score: <?= $score ?></div>

<div id="progressBarContainer" style="width:100%; background:#ddd; height:10px; border-radius:5px; overflow:hidden; margin:5px auto;">
    <div id="progressBar" style="width:100%; height:100%; background:green;"></div>
</div>

<div id="timer" style="color: green; font-weight: bold; margin-top:5px;">⏳ <?= $timeLimit ?></div>

<div class="question-box">🧠 <?= htmlspecialchars($question['question']) ?></div>

<?php if (!empty($question['image'])): ?>
    <div class="image-container">
        <img src="<?= htmlspecialchars($question['image']) ?>" class="question-image">
    </div>
<?php endif; ?>

<div class="answer-grid" style="display:none;">
<?php foreach ($answers as $a): ?>
    <div class="answer-col">
        <button type="button" class="answer-btn" onclick="submitAnswer(this)" data-value="<?= htmlspecialchars($a) ?>">
            <?= htmlspecialchars($a) ?>
        </button>
    </div>
<?php endforeach; ?>
</div>

<div id="feedbackBox" class="feedback" style="display:none;"></div>
<div id="correctAnswerBox" style="display:none;"><?= htmlspecialchars($question['correct']) ?></div>
