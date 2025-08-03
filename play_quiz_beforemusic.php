<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// session_start();
require_once 'db.php';
require_once 'session.php';

echo "👋 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>"; 

// Handle quiz restart
if (isset($_POST['restart'])) {
    unset($_SESSION['score'], $_SESSION['question_index'], $_SESSION['questions'], $_SESSION['quiz_table']);
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
    $_SESSION['quiz_table'] = $_POST['quiz_table'];
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;

    $selectedTable = $_POST['quiz_table'];
    $res = $conn->query("SELECT * FROM `$selectedTable`");
    if (!$res) {
        die("❌ Query failed: " . $conn->error);
    }
    
    $questions = [];
    while ($row = $res->fetch_assoc()) {
        // Debug: Check for missing keys
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
$index = $_SESSION['question_index'] ?? 0;
$questions = $_SESSION['questions'] ?? [];
$total = count($questions);
$score = $_SESSION['score'] ?? 0;

// Handle answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $index = $_SESSION['question_index'];
    $question = $_SESSION['questions'][$index];
    $timeTaken = intval($_POST['time_taken'] ?? 15);
    $bonus = 0;
    if ($_POST['answer'] === $question['correct']) {
        if ($timeTaken <= 5) $bonus = 3;
        elseif ($timeTaken <= 10) $bonus = 2;
        elseif ($timeTaken <= 15) $bonus = 1;
        $_SESSION['score'] += $bonus;
        $_SESSION['feedback'] = "✅ Correct! (+$bonus)";
    } else {
        $_SESSION['feedback'] = "❌ Wrong. Correct answer: " . htmlspecialchars($question['correct']);
    }
    $_SESSION['question_index']++;
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
    <style>
        body { font-family: sans-serif; text-align: center; padding: 20px; }
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
        select, button { padding: 10px; font-size: 1em; }
        #timer { font-size: 1.3em; color: darkred; margin: 10px; }
    </style>
</head>
<body>

<audio autoplay loop volume="0.2">
    <source src="background.mp3" type="audio/mpeg">
    Your browser does not support background music.
</audio>

<h1>🎯 Kahoot-style Quiz</h1>

<form method="POST">
    <label>Select quiz set:</label><br><br>
    <select name="quiz_table" required>
        <option value="">-- Choose a quiz_choices_* table --</option>
        <?php foreach ($quizTables as $table): ?>
            <option value="<?= htmlspecialchars($table) ?>" <?= ($selectedTable === $table) ? 'selected' : '' ?>>
                <?= htmlspecialchars($table) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="start_new">Start Quiz</button>
</form>

<hr>

<?php if ($selectedTable && $index < $total): ?>
    <div class="score">Question <?= $index + 1 ?> of <?= $total ?> | Score: <?= $score ?></div>
    <div id="timer">⏳ 15</div>
    <div class="question-box">🧠 <?= htmlspecialchars($questions[$index]['question']) ?></div>
    <?php if (!empty($questions[$index]['image'])): ?>
        <div class="image-container">
            <img src="<?= htmlspecialchars($questions[$index]['image']) ?>" class="question-image">
        </div>
    <?php endif; ?>
    <form method="POST" id="quizForm">
        <input type="hidden" name="time_taken" id="time_taken" value="15">
        <div class="answer-grid">
            <?php foreach ($questions[$index]['answers'] as $a): ?>
                <div class="answer-col">
                    <button type="submit" name="answer" value="<?= htmlspecialchars($a) ?>" class="answer-btn">
                        <?= htmlspecialchars($a) ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
    <?php if (isset($_SESSION['feedback'])): ?>
        <div class="feedback"><?= $_SESSION['feedback'] ?></div>
        <?php unset($_SESSION['feedback']); ?>
    <?php endif; ?>
    <script>
        let timeLeft = 15;
        const timerDisplay = document.getElementById("timer");
        const timeTakenInput = document.getElementById("time_taken");
        const countdown = setInterval(() => {
            timeLeft--;
            timerDisplay.textContent = `⏳ ${timeLeft}`;
            timeTakenInput.value = 15 - timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                document.querySelectorAll(".answer-btn").forEach(btn => btn.disabled = true);
                timerDisplay.textContent = "⏰ Time's up!";
            }
        }, 1000);
    </script>
<?php elseif ($selectedTable && $index >= $total): ?>
    <h2>🌟 Quiz Completed!</h2>
    <p>Your final score: <?= $score ?> out of <?= $total * 3 ?> points</p>
    <form method="POST">
        <input type="hidden" name="restart" value="1">
        <button type="submit">Play Again</button>
    </form>
<?php endif; ?>

</body>
</html>