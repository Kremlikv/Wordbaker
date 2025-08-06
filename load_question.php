<?php
session_start();
if (!isset($_SESSION['questions'], $_SESSION['q_index'])) {
    exit("No quiz in progress.");
}

$questions = $_SESSION['questions'];
$index = $_SESSION['q_index'];

if ($index >= count($questions)) {
    unset($_SESSION['questions'], $_SESSION['q_index']);
    echo "<h2>Quiz Finished</h2>";
    exit;
}

$q = $questions[$index];
$_SESSION['q_index']++;

echo '<div id="timer">⏳ 15</div>';
echo '<div class="question-box">' . htmlspecialchars($q['question']) . '</div>';
if (!empty($q['image'])) {
    echo '<div class="image-container"><img src="' . htmlspecialchars($q['image']) . '" style="max-width:100%;"></div>';
}
echo '<div class="answer-grid fade-in">';
foreach ($q['answers'] as $ans) {
    $correct = ($ans === $q['correct']) ? "1" : "0";
    echo '<div class="answer-col">
            <button class="answer-btn" data-correct="'.$correct.'" onclick="submitAnswer(this)">'.
            htmlspecialchars($ans).'</button>
          </div>';
}
echo '</div>';
echo '<div id="feedback" class="feedback"></div>';
