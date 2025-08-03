<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

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
1. die Tisch
2. der Tasche
3. der Tich

For each Czech word, I will give you the correct translation into $targetLang. 
Your task is to generate 3 **plausible but incorrect alternatives** — the kind of mistake a student might make. 

⚠️ DO NOT:
- Add random letters or corrupt the correct answer.
- Use gibberish.
- Return the correct answer in any form.
- Explain the answers.
- Use parentheses or notes.

✅ DO:
- Use real words that are incorrect but believable.
- Make mistakes like false friends, wrong gender/article, or overgeneralization.
- Output only 3 wrong answers in numbered list format:
1. WrongAnswer1
2. WrongAnswer2
3. WrongAnswer3

Czech: "$czechWord"
Correct translation: "$correctAnswer"
Wrong alternatives:
EOT;

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
    curl_close($ch);

    preg_match_all('/^\d+[\.\)\s-]+(.+)$/m', $response, $matches);
    $rawAnswers = isset($matches[1]) ? $matches[1] : array();

    $cleaned = array();
    foreach ($rawAnswers as $a) {
        $a = trim($a, " \"“”‘’'");
        $a = preg_replace('/\\s*\\([^)]*\\)/', '', $a); // remove parentheses content
        $cleaned[] = trim($a);
    }

    return $cleaned;
}

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

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
$tables = getUserTables($conn, $username);
$generatedTable = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $editTable = $conn->real_escape_string($_POST['save_table']);

    if (!empty($_POST['delete_rows'])) {
        foreach ($_POST['delete_rows'] as $deleteId) {
            $deleteId = intval($deleteId);
            $conn->query("DELETE FROM `$editTable` WHERE id = $deleteId");
        }
    }

    foreach ($_POST['edited_rows'] as $id => $row) {
        $imagePath = $_FILES['image']['name'][$id] ?? '';
        if ($imagePath && is_uploaded_file($_FILES['image']['tmp_name'][$id])) {
            $targetPath = "uploads/images/" . basename($_FILES['image']['name'][$id]);
            move_uploaded_file($_FILES['image']['tmp_name'][$id], $targetPath);
        } else {
            $targetPath = $_POST['existing_image'][$id] ?? '';
        }

        $stmt = $conn->prepare("UPDATE `$editTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=?, image_url=? WHERE id=?");
        $stmt->bind_param("sssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $targetPath, $id);
        $stmt->execute();
        $stmt->close();
    }

    echo "<p style='color: green;'><strong>Changes saved to table:</strong> $editTable</p>";
    $generatedTable = $editTable;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['source_lang'], $_POST['target_lang']) && !isset($_POST['save_table'])) {
    $table = $conn->real_escape_string($_POST['table']);
    $sourceLang = htmlspecialchars($_POST['source_lang']);
    $targetLang = htmlspecialchars($_POST['target_lang']);

    $result = $conn->query("SELECT * FROM `$table`");
    $col1 = $result->fetch_field_direct(0)->name;
    $col2 = $result->fetch_field_direct(1)->name;

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
        target_lang VARCHAR(20),
        image_url TEXT
    )");

    echo "<h2>Generating quiz entries for table <code>$table</code>...</h2><ul>";
    while ($row = $result->fetch_assoc()) {
        $question = trim($row[$col1]);
        $correct = trim($row[$col2]);
        if ($question === '' || $correct === '') continue;

        $wrongs = callOpenRouter(
            $OPENROUTER_API_KEY, $OPENROUTER_MODEL,
            $question, $correct, $targetLang,
            $OPENROUTER_REFERER, $APP_TITLE
        );

        list($wrong1, $wrong2, $wrong3) = array_pad($wrongs, 3, '');
        $image = '';

        $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang, image_url)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $sourceLang, $targetLang, $image);
        $stmt->execute();
        $stmt->close();

        echo "<li><strong>" . htmlspecialchars($question) . "</strong>: ✅ " . htmlspecialchars($correct)
             . " | ❌ " . htmlspecialchars($wrong1) . ", " . htmlspecialchars($wrong2) . ", " . htmlspecialchars($wrong3) . "</li>";

        @ob_flush(); @flush();
        if ($THROTTLE_SECONDS > 0) sleep($THROTTLE_SECONDS);
    }
    echo "</ul><hr>";
    $generatedTable = $quizTable;
}

// === HTML HEADER ===
echo <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Generate Quiz Choices</title>
<style>
    body { font-family: sans-serif; text-align: center; }
    form { display: inline-block; text-align: left; margin-top: 20px; }
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
</script></head><body>
HTML;

echo "<div class='content'>👋 Logged in as {$_SESSION['username']} | <a href='logout.php'>Logout</a></div>";

echo "<h2>Generate AI Quiz Choices</h2><form method='POST'>
<label>Select dictionary table:</label><br>
<select name='table' required>";
foreach ($tables as $t) echo "<option value='" . htmlspecialchars($t) . "'>$t</option>";
echo "</select><br><br>
<label>Source language (e.g. Czech):</label><br>
<input type='text' name='source_lang' required><br><br>
<label>Target language (e.g. German):</label><br>
<input type='text' name='target_lang' required><br><br>
<button type='submit'>🚀 Generate Quiz Set</button>
</form>";

if (!empty($generatedTable)) {
    $res = $conn->query("SELECT * FROM `$generatedTable`");
    echo "<h3>📜 Edit Generated Quiz: <code>$generatedTable</code></h3>
    <form method='POST' enctype='multipart/form-data'>
    <input type='hidden' name='save_table' value='" . htmlspecialchars($generatedTable) . "'>
    <table border='1' cellpadding='5' cellspacing='0'>
    <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Image</th><th>Delete</th></tr>";

    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr><td>" . htmlspecialchars($row['question']) . "</td>";
        foreach (['correct_answer', 'wrong1', 'wrong2', 'wrong3'] as $field) {
            echo "<td><textarea name='edited_rows[$id][$field]' oninput='autoResize(this)'>" . htmlspecialchars($row[$field]) . "</textarea></td>";
        }
        echo "<td>";
        if (!empty($row['image_url'])) echo "<img src='" . htmlspecialchars($row['image_url']) . "' style='max-height:50px;'><br>";
        echo "<input type='file' name='image[$id]'>
              <input type='hidden' name='existing_image[$id]' value='" . htmlspecialchars($row['image_url']) . "'>
              </td><td><input type='checkbox' name='delete_rows[]' value='$id'></td></tr>";
    }

    echo "</table><br><button type='submit'>📂 Save Changes</button></form>";
}

echo "</body></html>";
?>
