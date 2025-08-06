<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'session.php';

if (
    empty($_SESSION['questions']) ||
    !isset($_SESSION['question_index'], $_SESSION['score'], $_POST['answer'])
) {
    echo json_encode(['error' => 'No active quiz or invalid submission.']);
    exit;
}

$index     = $_SESSION['question_index'];
$questions = $_SESSION['questions'];
$question  = $questions[$index];
$userAnswer = $_POST['answer'];
$timeTaken  = intval($_POST['time_taken'] ?? 15);
$bonus      = 0;
$correct    = $question['correct'];
$isCorrect  = ($userAnswer === $correct);

if ($isCorrect) {
    if     ($timeTaken <= 5)  $bonus = 3;
    elseif ($timeTaken <= 10) $bonus = 2;
    elseif ($timeTaken <= 15) $bonus = 1;
    $_SESSION['score'] += $bonus;
    $feedback = "✅ Correct! (+$bonus)";
} else {
    $feedback = "❌ Wrong. Correct answer: $correct";
    $_SESSION['mistakes'][] = [
        'question' => $question['question'],
        'correct'  => $correct,
        'user'     => $userAnswer
    ];
}

// Advance the index (but not the HTML)
$_SESSION['question_index']++;

echo json_encode([
    'isCorrect'     => $isCorrect,
    'correctAnswer' => $correct,
    'feedback'      => $feedback,
    'score'         => $_SESSION['score']
]);
