<?php
// Debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = 'YOUR_API_KEY';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';

function quizTableExists($conn, $table) {
    $quizTable = (strpos($table, 'quiz_choices_') === 0) ? $table : "quiz_choices_" . $table;
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $res && $res->num_rows > 0;
}

function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
    $prompt = <<<EOT
    Create three different usual mistakes...
    EOT;
    $data = [
        "model" => $model,
        "messages" => [[ "role" => "user", "content" => $prompt ]]
    ];
    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey",
            "HTTP-Referer: $referer",
            "X-Title: $appTitle"
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($response, true);
    $output = $decoded['choices'][0]['message']['content'] ?? '';
    $lines = array_filter(array_map('trim', explode("\n", $output)));
    return array_slice($lines, 0, 3);
}

function cleanAIOutput($answers) {
    return array_map(fn($a) => trim(preg_replace('/^[\-\:\"]+/', '', $a)), $answers);
}

// Always load file explorer data
$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");

// Modified file explorer filter
function includeFilteredFileExplorer($conn, $username) {
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array()) {
        $table = $row[0];
        if (stripos($table, $username . '_') === 0 && strpos($table, 'quiz_choices_') !== 0) {
            $tables[] = $table;
        }
    }

    echo "<div style='text-align:center; margin:20px;'><h3>Select a source table:</h3>";
    foreach ($tables as $t) {
        echo "<div style='margin:5px;'>
                <a href='generate_quiz_choices.php?table=" . urlencode($t) . "' style='padding:8px; background:#2196F3; color:#fff; text-decoration:none;'>
                    " . htmlspecialchars($t) . "
                </a>
              </div>";
    }
    echo "</div>";
}

$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$autoSourceLang = '';
$autoTargetLang = '';
if ($selectedTable) {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $autoSourceLang = ucfirst($cols[0]['Field']);
        $autoTargetLang = ucfirst($cols[1]['Field']);
    }
}

$generatedTable = '';
if ($selectedTable) {
    $quizTable = "quiz_choices_" . $selectedTable;
    if (!quizTableExists($conn, $selectedTable)) {
        $res = $conn->query("SELECT * FROM `$selectedTable`");
        if ($res && $res->num_rows > 0) {
            $col1 = $res->fetch_fields()[0]->name;
            $col2 = $res->fetch_fields()[1]->name;
            $conn->query("CREATE TABLE `$quizTable` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question TEXT,
                correct_answer TEXT,
                wrong1 TEXT,
                wrong2 TEXT,
                wrong3 TEXT,
                source_lang VARCHAR(50),
                target_lang VARCHAR(50),
                image_url TEXT
            )");
            while ($row = $res->fetch_assoc()) {
                $question = trim($row[$col1]);
                $correct = trim($row[$col2]);
                if ($question === '' || $correct === '') continue;
                $wrongAnswers = callOpenRouter($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $question, $correct, $autoTargetLang, $OPENROUTER_REFERER, $APP_TITLE) ?: [$correct.'x', strrev($correct), 'wrong'];
                $wrongAnswers = cleanAIOutput($wrongAnswers);
                [$w1, $w2, $w3] = array_pad($wrongAnswers, 3, '');
                $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $question, $correct, $w1, $w2, $w3, $autoSourceLang, $autoTargetLang);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    $generatedTable = $quizTable;
}

echo "<div class='content'>👤 Logged in as ".$_SESSION['username']." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";

// Always show filtered file explorer
includeFilteredFileExplorer($conn, $username);

// Only show preview if table was selected
if ($generatedTable) {
    echo "<h3 style='text-align:center;'>Preview: <code>$generatedTable</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable` LIMIT 20");
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
                <td>".htmlspecialchars($row['question'])."</td>
                <td>".htmlspecialchars($row['correct_answer'])."</td>
                <td>".htmlspecialchars($row['wrong1'])."</td>
                <td>".htmlspecialchars($row['wrong2'])."</td>
                <td>".htmlspecialchars($row['wrong3'])."</td>
              </tr>";
    }
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit</a>
          </div>";
}
?>
