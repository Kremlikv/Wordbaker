<?php
// Debug mode: show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

/* --- Save handler (non-AJAX) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $saveTable = trim($_POST['save_table']);
    if (!empty($saveTable)) {
        $editedRows = $_POST['edited_rows'] ?? [];
        $deleteRows = $_POST['delete_rows'] ?? [];
        foreach ($editedRows as $id => $row) {
            if (in_array($id, $deleteRows)) {
                $conn->query("DELETE FROM `$saveTable` WHERE id=" . intval($id));
                continue;
            }
            $stmt = $conn->prepare("UPDATE `$saveTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=? WHERE id=?");
            $stmt->bind_param("ssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $id);
            $stmt->execute();
            $stmt->close();
        }
        ob_clean();
        header("Location: generate_quiz_choices.php?table=" . urlencode($saveTable) . "&saved=1");
        exit;
    }
}

include 'styling.php';

/* --- Check if quiz table exists --- */
function quizTableExists($conn, $table) {
    $quizTable = "quiz_choices_" . $table;
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $result && $result->num_rows > 0;
}

/* --- Get folders & tables --- */
function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
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

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
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

$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$autoSourceLang = '';
$autoTargetLang = '';
if (!empty($selectedTable)) {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $autoSourceLang = ucfirst($cols[0]['Field']);
        $autoTargetLang = ucfirst($cols[1]['Field']);
    }
}

/* --- AI call --- */
function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
    $prompt = <<<EOT
    Create three different usual mistakes (wrong1, wrong2, wrong3) that a human student may make when translating $czechWord into $targetLang: $correctAnswer. 
    Vary the types of mistakes: article/gender confusion, false friends, near homophones, spelling errors, wrong diacritic marks, similar but incorrect verb form, wrong plural/singular, etc.
    Sometimes the mistakes are confusion of two things that have something in common: have the same word-root (Aufgang, Ausgang), similar function (Car, Van), similar spelling (lie, lay).
    Don't use nonsense strings, reversed words, randomly inserted letters, palindromes, unrelated words. 
    Don't explain the mistakes.
    Don't add any symbols like ()':"-/_ or numbering or bulletpoints.     
    EOT;

    $data = [
        "model" => $model,
        "messages" => [[
            "role" => "user",
            "content" => $prompt
        ]] 
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey",
        "HTTP-Referer: $referer",
        "X-Title: $appTitle"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    $output = $decoded['choices'][0]['message']['content'] ?? '';
    $lines = preg_split('/\r\n|\r|\n/', $output);
    $lines = array_map(function($line) {
        return trim(preg_replace('/^[\-\:\"]+/', '', $line));
    }, $lines);
    $lines = array_filter($lines, fn($l) => $l !== '');
    return array_slice(array_values($lines), 0, 3);
}

function naiveWrongAnswers($correct) {
    return [$correct . 'x', strrev($correct), substr($correct, 1) . substr($correct, 0, 1)];
}

function cleanAIOutput($answers) {
    return array_map(function($a) {
        return trim(preg_replace('/^[\-\:\"]+/', '', $a));
    }, $answers);
}

/* --- Delete quiz and images --- */
if (isset($_POST['delete_quiz']) && !empty($_POST['delete_table'])) {
    $delTable = $conn->real_escape_string($_POST['delete_table']);
    $res = $conn->query("SELECT image_url FROM `$delTable` WHERE image_url LIKE 'uploads/quiz_images/%'");
    while ($row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/' . $row['image_url'];
        if (file_exists($filePath)) unlink($filePath);
    }
    $conn->query("DROP TABLE IF EXISTS `$delTable`");
    header("Location: generate_quiz_choices.php");
    exit;
}

/* --- Generate quiz if not exists --- */
$generatedTable = '';
if (!empty($selectedTable)) {
    $quizTable = "quiz_choices_" . $selectedTable;
    if (!quizTableExists($conn, $selectedTable)) {
        $result = $conn->query("SELECT * FROM `$selectedTable`");
        if ($result && $result->num_rows > 0) {
            $col1 = $result->fetch_fields()[0]->name;
            $col2 = $result->fetch_fields()[1]->name;
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
            while ($row = $result->fetch_assoc()) {
                $question = trim($row[$col1]);
                $correct = trim($row[$col2]);
                if ($question === '' || $correct === '') continue;
                $wrongAnswers = callOpenRouter($apiKey, $model, $question, $correct, $autoTargetLang, $OPENROUTER_REFERER, $APP_TITLE) ?: naiveWrongAnswers($correct);
                $wrongAnswers = cleanAIOutput($wrongAnswers);
                [$wrong1, $wrong2, $wrong3] = array_pad($wrongAnswers, 3, '');
                $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $autoSourceLang, $autoTargetLang);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    $generatedTable = $quizTable;
}

/* --- Output --- */
echo "<div class='content'>👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";
include 'file_explorer.php';

if (!empty($generatedTable)) {
    if (isset($_GET['saved']) && $_GET['saved'] == '1') {
        echo "<div style='color:green; text-align:center; font-weight:bold;'>✅ Table saved successfully</div>";
    }
    echo "<h3 style='text-align:center;'>📜 Edit Generated Quiz: <code>$generatedTable</code></h3>";
    echo "<form id='quizForm' method='POST' style='text-align:center;'>
            <input type='hidden' name='save_table' value='" . htmlspecialchars($generatedTable) . "'>
            <table border='1' cellpadding='5' cellspacing='0' style='margin:auto;'>
                <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Delete</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable`");
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>
                <td>" . htmlspecialchars($row['question']) . "</td>
                <td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>
                <td><input type='checkbox' name='delete_rows[]' value='" . intval($id) . "'></td>
              </tr>";
    }
    echo "</table><br>
          <button type='submit'>📂 Save Changes</button>
          </form>

          <div style='text-align:center; margin-top:20px;'>
            <button type='button' onclick='window.location.href=\"add_images.php?table=" . urlencode($generatedTable) . "\"'>🖼 Do you want to add pictures?</button>
          </div>

          <form method='POST' style='margin-top:20px; text-align:center;'>
            <input type='hidden' name='delete_table' value='" . htmlspecialchars($generatedTable) . "'>
            <button type='submit' name='delete_quiz' onclick='return confirm(\"Delete this quiz and all uploaded images?\")'>🗑 Delete Quiz</button>
          </form>";
}
?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("textarea").forEach(el => autoResize(el));
});

function autoResize(el) {
    el.style.height = "auto";
    el.style.height = (el.scrollHeight) + "px";
}
</script>
