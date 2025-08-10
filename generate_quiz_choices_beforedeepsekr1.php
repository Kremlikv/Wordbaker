<?php
// Debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php'; // KEY, MODEL, REFERER, APP NAME


function quizTableExists($conn, $table) {
    $quizTable = (strpos($table, 'quiz_choices_') === 0) ? $table : "quiz_choices_" . $table;
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $res && $res->num_rows > 0;
}

function callOpenRouter($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
    $systemMessage = "You are an expert language teacher preparing multiple-choice quizzes. For each Czech word and its correct translation, output ONLY 3 plausible wrong answers (no bullets, no numbering, no explanations).";
    $userMessage = <<<USR
Czech word: "$czechWord"
Correct $targetLang translation: "$correctAnswer"

Simulate 3 wrong answers a student might mistakenly choose. Reflect real mistakes:
- article/gender confusion
- false friends
- similar spelling/sound
- same category
- plural vs singular
- diacritic confusion

STRICT:
- do NOT explain
- do NOT include the correct answer
- exactly 3 lines, one per wrong answer
USR;

    $payload = [
        "model" => $OPENROUTER_MODEL, // e.g. 'deepseek/deepseek-r1:free'
        "messages" => [
            ["role" => "system", "content" => $systemMessage],
            ["role" => "user", "content" => $userMessage],
        ],
        "max_tokens" => 120,
        "temperature" => 0.4,
    ];

    $url = "https://openrouter.ai/api/v1/chat/completions";

    // small retry loop for 429s
    $attempts = 0;
    while ($attempts < 3) {
        $attempts++;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "HTTP-Referer: $referer",
                "X-Title: $appTitle",
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $err  = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // Network error?
        if ($errno) {
            error_log("[OpenRouter] cURL error $errno: $err");
            break;
        }

        $code = (int)($info['http_code'] ?? 0);
        if ($code === 429) {
            // Back off a bit and retry
            usleep(600000); // 0.6s
            continue;
        }

        if ($code >= 400) {
            // Try to show server error body to the logs
            error_log("[OpenRouter] HTTP $code response: $response");
            break;
        }

        // Success path
        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        return cleanAIOutput($lines);
    }

    // Fallback (don’t leak nonsense)
    return [];
}



function cleanAIOutput($answers) {
    return array_values(array_filter(array_map(function($a) {
        $clean = trim($a);
        $clean = preg_replace('/^[-\d\.\)\:\"\']+/', '', $clean);
        if (strlen($clean) > 50 || preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u', $clean)) {
            return '';
        }
        return $clean;
    }, $answers)));
}



// ===== SAME PRE-EXPLORER LOGIC AS main.php =====
$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (strpos($table, 'quiz_choices_') === 0) continue; // skip quiz tables
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts = explode('_', $suffix, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $folder = $parts[0];
                $file = $parts[1];
            } else {
                $folder = 'Uncategorized';
                $file = $suffix;
            }
            $allTables[$folder][] = [
                'table_name' => $table,
                'display_name' => $file
            ];
        }
    }
    return $allTables;
}

$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table' => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}
// ===== END PRE-EXPLORER LOGIC =====

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
echo "<p style='text-align:center;'> This AI is designed for vocabulary, not sentences</p>";

include 'file_explorer.php';

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
