<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

echo "<div class='content'>";
echo "👋 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a>"; 
echo "</div>";

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <title>Generate Quiz Choices</title>
  
    <style>
        textarea {
            width: 120px;
            min-height: 1.5em;
            resize: none;
            overflow: hidden;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 1em;
        }
    </style>
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
</head>
<body>
HTML;

function getUserTables($conn, $username) {
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (stripos($table, $username . '_') === 0) {
            $tables[] = $table;
        }
    }
    return $tables;
}

function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
//    $prompt = "The correct translation of the Czech word \"$czechWord\" into $targetLang is \"$correctAnswer\". "
//            . "Generate 3 incorrect alternatives where a different word will be used instead of the correct word."                
//            . "Avoid unrealistic mistakes which humans would not make. The words must be somewhat similar in meaning or form."
//           . "Return only valid UTF-8 text as a numbered list.";

    $prompt = <<<EOT
        You are helping build a language-learning quiz.

        For each Czech word, I will give you the correct translation into $targetLang. 
        Your task is to generate 3 **plausible but incorrect alternatives** — the kind of mistake a student might make. 

        ⚠️ DO NOT:
        - Add random letters or corrupt the correct answer.
        - Modify the correct answer by adding/removing characters (e.g., "der Hof" → "der Hofx" ❌)
        - Use gibberish (e.g., "Grgsbslk" ❌ or "dr rmnsch Stl" ❌)
        - Return the correct answer in any form.
        - Do not explain why you selected these alternatives.

        ✅ DO:
        - Use real words from the target language that are incorrect but believable.
        - Make mistakes a human might make: false friends, wrong gender, wrong article, wrong word choice.
        - Choose actual words/phrases students could confuse.
        - Format as a numbered list (1–3), with each option on its own line.
        - Output must be valid UTF-8.

        ### Example:
        Czech word: "město"
        Correct translation into German: "die Stadt"
        Wrong alternatives:
        1. das Stadt (wrong gender)
        2. die Stelle (false friend)
        3. die Hauptstadt (too specific)

        Now apply this to the following word:

        Czech: "$czechWord"
        Correct translation: "$correctAnswer"

        Wrong alternatives:
        EOT;


//


    $data = array(
        "model" => $model,
        "messages" => array(
            array(
                "role" => "user",
                "content" => array(
                    array("type" => "text", "text" => $prompt)
                )
            )
        )
    );

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
        "HTTP-Referer: " . $referer,
        "X-Title: " . $appTitle
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response) return array(array(), $httpCode);

    $decoded = json_decode($response, true);
    $output = isset($decoded['choices'][0]['message']['content']) ? $decoded['choices'][0]['message']['content'] : '';
    preg_match_all('/^\d+[\.\)\s-]+(.+)$/m', $output, $matches);
    $rawAnswers = isset($matches[1]) ? $matches[1] : array();

    // Trim quotes and whitespace
    $cleaned = array();
    foreach ($rawAnswers as $a) {
        $cleaned[] = trim($a, " \"“”‘’'");
    }

    return array($cleaned, $httpCode);
}

function naiveWrongAnswers($correct, $lang) {
    $wrong1 = $correct . 'x';
    $wrong2 = preg_replace('/[aeiouáéíóúýäëïöü]/u', '', $correct);
    $wrong3 = mb_substr($correct, 1) . mb_substr($correct, 0, 1);
    return array($wrong1, $wrong2, $wrong3);
}

$username = strtolower(isset($_SESSION['username']) ? $_SESSION['username'] : '');
$conn->set_charset("utf8mb4");
$tables = getUserTables($conn, $username);
$generatedTable = '';

// === Save Edits Handler ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $editTable = $conn->real_escape_string($_POST['save_table']);

    // Deletion
    if (!empty($_POST['delete_rows'])) {
        foreach ($_POST['delete_rows'] as $deleteId) {
            $deleteId = intval($deleteId);
            $conn->query("DELETE FROM `$editTable` WHERE id = $deleteId");
        }
    }

    // Updates
    if (!empty($_POST['edited_rows'])) {
        foreach ($_POST['edited_rows'] as $id => $row) {
            $stmt = $conn->prepare("UPDATE `$editTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=? WHERE id=?");
            $stmt->bind_param("ssssi",
                $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $id
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<p style='color: green;'><strong>Changes saved to table:</strong> $editTable</p>";
    $generatedTable = $editTable;
}

// === Generate Quiz Set Handler ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['source_lang'], $_POST['target_lang']) && !isset($_POST['save_table'])) {
    $table = $conn->real_escape_string($_POST['table']);
    $sourceLang = htmlspecialchars($_POST['source_lang']);
    $targetLang = htmlspecialchars($_POST['target_lang']);

    $result = $conn->query("SELECT * FROM `$table`");
    if ($result && $result->num_rows > 0) {
        $columns = $result->fetch_fields();
        $col1 = $columns[0]->name;
        $col2 = $columns[1]->name;

        $quizTable = "quiz_choices_" . $table;
        $conn->query("DROP TABLE IF EXISTS `$quizTable`");
        $conn->query("CREATE TABLE `$quizTable` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT,
            correct_answer TEXT,
            wrong1 TEXT,
            wrong2 TEXT,
            wrong3 TEXT,
            source_lang VARCHAR(20),
            target_lang VARCHAR(20)
        )");

        echo "<h2>Generating quiz entries for table <code>$table</code>...</h2><ul>";
        while ($row = $result->fetch_assoc()) {
            $question = trim($row[$col1]);
            $correct = trim($row[$col2]);
            if ($question === '' || $correct === '') continue;

            list($wrongs, $httpCode) = callOpenRouter(
                $OPENROUTER_API_KEY,
                $OPENROUTER_MODEL,
                $question,
                $correct,
                $targetLang,
                $OPENROUTER_REFERER,
                $APP_TITLE
            );

            if (count($wrongs) < 3) {
                $wrongs = naiveWrongAnswers($correct, $targetLang);
            }

            list($wrong1, $wrong2, $wrong3) = array_pad($wrongs, 3, '');

            $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $sourceLang, $targetLang);
            $stmt->execute();
            $stmt->close();

            echo "<li><strong>" . htmlspecialchars($question) . "</strong>: ✅ " . htmlspecialchars($correct)
                 . " | ❌ " . htmlspecialchars($wrong1) . ", " . htmlspecialchars($wrong2) . ", " . htmlspecialchars($wrong3) . "</li>";

            @ob_flush(); @flush();
            if ($THROTTLE_SECONDS > 0) sleep($THROTTLE_SECONDS);
        }

        echo "</ul><hr>";
        $generatedTable = $quizTable;
    } else {
        echo "<p style='color:red;'>No data found in table.</p>";
    }
}

// === Editable Table View ===
if (!empty($generatedTable)) {
    $editTable = $conn->real_escape_string($generatedTable);
    $res = $conn->query("SELECT * FROM `$editTable`");
    echo "<h3>📝 Edit Generated Quiz: <code>$editTable</code></h3>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='save_table' value='" . htmlspecialchars($editTable) . "'>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Delete</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['question']) . "</td>";
        echo "<td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>";
        echo "<td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>";
        echo "<td><input type='checkbox' name='delete_rows[]' value='" . intval($id) . "'></td>";
        echo "</tr>";
    }
    echo "</table><br><button type='submit'>💾 Save Changes</button></form><br>";
}

echo "<div class='content'>";
echo "<h2>Generate AI Quiz Choices</h2>";
echo "<form method='POST'>";
echo "<label>Select dictionary table:</label><br>";
echo "<select name='table' required>";
foreach ($tables as $t) {
    echo "<option value='" . htmlspecialchars($t) . "'>$t</option>";
}
echo "</select><br><br>";
echo "<label>Source language (e.g. Czech):</label><br>";
echo "<input type='text' name='source_lang' required><br><br>";
echo "<label>Target language (e.g. German):</label><br>";
echo "<input type='text' name='target_lang' required><br><br>";
echo "<button type='submit'>🚀 Generate Quiz Set</button>";
echo "</form></body></html>";
echo "</div>";
?>
