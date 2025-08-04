<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

// ----------------------
// REUSABLE FOLDER/TABLE FETCH LOGIC
// ----------------------
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

// Build folder/table arrays
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

// ----------------------
// DETECT LANGUAGES FROM TABLE COLUMNS
// ----------------------
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

// ----------------------
// AI GENERATION FUNCTIONS
// ----------------------
function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
    $prompt = <<<EOT
You are a professional language teacher who creates multiple-choice vocabulary quizzes for foreign language learners. Given a correct translation, generate 3 **plausible but incorrect** answers that simulate mistakes language learners often make.

Mistakes should reflect:
- False friends
- Gender/article confusion
- Typical typos or spelling errors
- Words with similar pronunciation or meaning

Do **not** invent nonsense words, reversed words, or unrealistic distractors. Each wrong answer must be a real word or plausible learner error.

---

Czech: "stůl"  
Target Language: German  
Correct Answer: "der Tisch"  
Wrong Answers:
1. die Tisch *(article confusion)*
2. der Tasche *(false friend)*
3. der Tich *(spelling error)*

For each Czech word, I will give you the correct translation into $targetLang. 
Your task is to generate 3 **plausible but incorrect alternatives** — the kind of mistake a student might make. 

Czech: "$czechWord"
Correct translation: "$correctAnswer"
Wrong alternatives:
EOT;

    $data = [
        "model" => $model,
        "messages" => [[
            "role" => "user",
            "content" => [["type" => "text", "text" => $prompt]]
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
    preg_match_all('/\d+\.?\s*(.*?)\s*(?:\\n|$)/', $output, $matches);

    $wrongAnswers = array_map(function ($a) {
        $a = trim($a);
        $a = preg_replace('/\s*\([^)]*\)/', '', $a);
        $a = trim($a, "*\"“”‘’' ");
        return $a;
    }, $matches[1]);

    return count($wrongAnswers) >= 3 ? array_slice($wrongAnswers, 0, 3) : [];
}

function naiveWrongAnswers($correct) {
    return [$correct . 'x', strrev($correct), substr($correct, 1) . substr($correct, 0, 1)];
}

// ----------------------
// PROCESS FORM
// ----------------------
$generatedTable = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['source_lang'], $_POST['target_lang'])) {
    $table = $conn->real_escape_string($_POST['table']);
    $sourceLang = htmlspecialchars($_POST['source_lang']);
    $targetLang = htmlspecialchars($_POST['target_lang']);

    $result = $conn->query("SELECT * FROM `$table`");
    if ($result && $result->num_rows > 0) {
        $col1 = $result->fetch_fields()[0]->name;
        $col2 = $result->fetch_fields()[1]->name;

        $quizTable = "quiz_choices_" . $table;
        $conn->query("DROP TABLE IF EXISTS `$quizTable`");
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

        echo "<h2>Generating quiz entries for table <code>$table</code>...</h2><ul>";
        while ($row = $result->fetch_assoc()) {
            $question = trim($row[$col1]);
            $correct = trim($row[$col2]);
            if ($question === '' || $correct === '') continue;

            $wrongAnswers = callOpenRouter(
                $OPENROUTER_API_KEY, $OPENROUTER_MODEL, $question, $correct,
                $targetLang, $OPENROUTER_REFERER, $APP_TITLE
            );
            if (count($wrongAnswers) < 3) {
                $wrongAnswers = naiveWrongAnswers($correct);
            }

            [$wrong1, $wrong2, $wrong3] = array_pad($wrongAnswers, 3, '');
            $stmt = $conn->prepare("INSERT INTO `$quizTable`
                (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, '')");
            $stmt->bind_param("sssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $sourceLang, $targetLang);
            $stmt->execute();
            $stmt->close();

            echo "<li><strong>" . htmlspecialchars($question) . "</strong>: ✅ " . htmlspecialchars($correct) .
                 " | ❌ " . htmlspecialchars($wrong1) . ", " . htmlspecialchars($wrong2) . ", " . htmlspecialchars($wrong3) . "</li>";

            @ob_flush(); @flush();
            if ($THROTTLE_SECONDS > 0) sleep($THROTTLE_SECONDS);
        }
        echo "</ul><hr>";
        $generatedTable = $quizTable;
    }
}

// ----------------------
// OUTPUT PAGE
// ----------------------
echo "<div class='content'>";
echo "👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>";
echo "</div>";

echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";

// Include file explorer in the center
echo "<div style='display:flex;justify-content:center;'>";
include 'file_explorer.php';
echo "</div>";

echo "<div id='fileSelectedMsg' style='display:none;text-align:center;margin-top:10px;font-weight:bold;color:green;'></div>";

if (!empty($selectedTable)) {
    echo "<form method='POST' style='text-align:center; margin-top:20px;'>";
    echo "<input type='hidden' name='table' value='" . htmlspecialchars($selectedTable) . "'>";
    echo "<label>Source language:</label><br>";
    echo "<input type='text' name='source_lang' value='" . htmlspecialchars($autoSourceLang) . "' required><br><br>";
    echo "<label>Target language:</label><br>";
    echo "<input type='text' name='target_lang' value='" . htmlspecialchars($autoTargetLang) . "' required><br><br>";
    echo "<button type='submit'>🚀 Generate Quiz Set</button>";
    echo "</form>";
}

if (!empty($generatedTable)) {
    $res = $conn->query("SELECT * FROM `$generatedTable`");
    echo "<h3 style='text-align:center;'>📜 Edit Generated Quiz: <code>$generatedTable</code></h3>";
    echo "<form method='POST' enctype='multipart/form-data' style='text-align:center;'>";
    echo "<input type='hidden' name='save_table' value='" . htmlspecialchars($generatedTable) . "'>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='margin:auto;'>";
    echo "<tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Image URL</th><th>Delete</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['question']) . "</td>";
        echo "<td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>";
        echo "<td><input type='text' name='edited_rows[$id][image_url]' value='" . htmlspecialchars($row['image_url']) . "'></td>";
        echo "<td><input type='checkbox' name='delete_rows[]' value='" . intval($id) . "'></td>";
        echo "</tr>";
    }
    echo "</table><br><button type='submit'>📂 Save Changes</button></form><br>";
}
?>
<script>
function autoResize(el) {
    el.style.height = "auto";
    el.style.height = (el.scrollHeight) + "px";
}
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach(function (el) {
        autoResize(el);
        el.addEventListener("input", function () {
            autoResize(el);
        });
    });
});
</script>
