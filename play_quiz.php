<?php
require_once 'db.php';
require_once 'session.php';

session_start();
if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['questions'] = [];
}

// Load available quiz_choices_* tables
$quizTables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    if (strpos($row[0], 'quiz_choices_') === 0) {
        $quizTables[] = $row[0];
    }
}

$selectedTable = $_POST['quiz_table'] ?? $_SESSION['quiz_table'] ?? '';
if ($selectedTable && empty($_SESSION['questions'])) {
    $_SESSION['quiz_table'] = $selectedTable;
    $res = $conn->query("SELECT * FROM `$selectedTable`");
    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $answers = [$row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3']];
        shuffle($answers);
        $questions[] = [
            'question' => $row['question'],
            'correct' => $row['correct_answer'],
            'answers' => $answers
        ];
    }
    shuffle($questions);
    $_SESSION['questions'] = $questions;
    $_SESSION['question_index'] = 0;
    $_SESSION['score'] = 0;
}

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $index = $_SESSION['question_index'];
    $question = $_SESSION['questions'][$index];
    if ($_POST['answer'] === $question['correct']) {
        $_SESSION['score']++;
        $_SESSION['feedback'] = "✅ Correct!";
    } else {
        $_SESSION['feedback'] = "❌ Wrong. Correct answer: " . htmlspecialchars($question['correct']);
    }
    $_SESSION['question_index']++;
    header("Location: play_quiz.php");
    exit;
}

$index = $_SESSION['question_index'];
$questions = $_SESSION['questions'];
$total = count($questions);
$score = $_SESSION['score'];

// HTML output
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Play Quiz</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 20px; }
        .question-box { font-size: 1.5em; margin-bottom: 20px; }
        .answer-btn {
            display: inline-block;
            width: 45%;
            padding: 20px;
            margin: 10px;
            font-size: 1.2em;
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
        select, button { padding: 10px; font-size: 1em; }
    </style>
</head>
<body>
<h1>🎯 Kahoot-style Quiz</h1>

<?php if (!$selectedTable): ?>
    <form method="POST">
        <label>Select quiz set:</label><br><br>
        <select name="quiz_table" required>
            <option value="">-- Choose a quiz_choices_* table --</option>
            <?php foreach ($quizTables as $table): ?>
                <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
            <?php endforeach; ?>
        </select><br><br>
        <button type="submit">Start Quiz</button>
    </form>
<?php elseif ($index < $total): ?>
    <div class="score">Question <?= $index + 1 ?> of <?= $total ?> | Score: <?= $score ?></div>
    <div class="question-box">🧠 <?= htmlspecialchars($questions[$index]['question']) ?></div>
    <form method="POST">
        <?php foreach ($questions[$index]['answers'] as $a): ?>
            <button type="submit" name="answer" value="<?= htmlspecialchars($a) ?>" class="answer-btn">
                <?= htmlspecialchars($a) ?>
            </button><br>
        <?php endforeach; ?>
    </form>
    <?php if (isset($_SESSION['feedback'])): ?>
        <div class="feedback"><?= $_SESSION['feedback'] ?></div>
        <?php unset($_SESSION['feedback']); ?>
    <?php endif; ?>
<?php else: ?>
    <h2>🏁 Quiz Completed!</h2>
    <p>Your final score: <?= $score ?> out of <?= $total ?></p>
    <form method="POST" action="">
        <button type="submit" name="restart" value="1">Play Again</button>
    </form>
    <?php session_destroy(); ?>
<?php endif; ?>
</body>
</html>
