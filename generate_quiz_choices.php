<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

function getUserTables($conn, $username) {
    $tables = [];
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
    $prompt = <<<EOT
You are a professional language teacher who creates multiple-choice vocabulary quizzes for foreign language learners. Given a correct translation, generate 3 **plausible but incorrect** answers that simulate mistakes language learners often make.

Mistakes should reflect:
- False friends
- Gender/article confusion
- Typical typos or spelling errors
- Words with similar pronunciation or meaning

Do **not** invent nonsense words, reversed words, or unrealistic distractors. Each wrong answer must be a real word or plausible learner error.

Czech: "$czechWord"
Correct translation: "$correctAnswer"
Wrong alternatives:
EOT;

    $data = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => [["type" => "text", "text" => $prompt]]]]
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $output = json_decode($response, true)['choices'][0]['message']['content'] ?? '';
    preg_match_all('/^\d+[\.\)\s-]+(.+)$/m', $output, $matches);
    return [array_map('trim', $matches[1] ?? []), $httpCode];
}

function naiveWrongAnswers($correct, $lang) {
    return [$correct . 'x', preg_replace('/[aeiou]/i', '', $correct), substr($correct, 1) . substr($correct, 0, 1)];
}

$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
$tables = getUserTables($conn, $username);
$generatedTable = '';

// Save edits + image uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $editTable = $conn->real_escape_string($_POST['save_table']);

    if (!empty($_POST['delete_rows'])) {
        foreach ($_POST['delete_rows'] as $deleteId) {
            $conn->query("DELETE FROM `$editTable` WHERE id = " . intval($deleteId));
        }
    }

    if (!empty($_POST['edited_rows'])) {
        foreach ($_POST['edited_rows'] as $id => $row) {
            $imagePath = '';
            if (isset($_FILES['image_files']['name'][$id]) && $_FILES['image_files']['error'][$id] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['image_files']['tmp_name'][$id];
                $fileName = basename($_FILES['image_files']['name'][$id]);
                $uploadDir = 'uploads/quiz_images/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $targetPath = $uploadDir . uniqid("img_") . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $imagePath = $targetPath;
                }
            }
            if (!$imagePath) {
                $res = $conn->query("SELECT image_url FROM `$editTable` WHERE id = $id");
                $imagePath = ($res->fetch_assoc())['image_url'] ?? '';
            }

            $stmt = $conn->prepare("UPDATE `$editTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=?, image_url=? WHERE id=?");
            $stmt->bind_param("sssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $imagePath, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<p style='color: green;'><strong>Changes saved to table:</strong> $editTable</p>";
    $generatedTable = $editTable;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['source_lang'], $_POST['target_lang']) && !isset($_POST['save_table'])) {
    $table = $conn->real_escape_string($_POST['table']);
    $sourceLang = htmlspecialchars($_POST['source_lang']);
    $targetLang = htmlspecialchars($_POST['target_lang']);

    $result = $conn->query("SELECT * FROM `$table`");
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
        $question = trim($row[array_keys($row)[0]]);
        $correct = trim($row[array_keys($row)[1]]);
        if ($question === '' || $correct === '') continue;

        list($wrongs, $httpCode) = callOpenRouter(
            $OPENROUTER_API_KEY, $OPENROUTER_MODEL,
            $question, $correct, $targetLang,
            $OPENROUTER_REFERER, $APP_TITLE
        );

        if (count($wrongs) < 3) $wrongs = naiveWrongAnswers($correct, $targetLang);
        list($w1, $w2, $w3) = array_pad($wrongs, 3, '');

        $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang, image_url)
                                VALUES (?, ?, ?, ?, ?, ?, ?, '')");
        $stmt->bind_param("sssssss", $question, $correct, $w1, $w2, $w3, $sourceLang, $targetLang);
        $stmt->execute();
        $stmt->close();

        echo "<li><strong>" . htmlspecialchars($question) . ":</strong> ✅ $correct</li>";
        flush(); sleep($THROTTLE_SECONDS);
    }
    echo "</ul><hr>";
    $generatedTable = $quizTable;
}

// === UI ===
echo "<h2>Generate AI Quiz Choices</h2><form method='POST'><label>Dictionary table:</label><br><select name='table' required>";
foreach ($tables as $t) echo "<option value='$t'>$t</option>";
echo "</select><br><br><label>Source language:</label><br><input name='source_lang' required><br><br>
      <label>Target language:</label><br><input name='target_lang' required><br><br>
      <button type='submit'>🚀 Generate Quiz Set</button></form><hr>";

if ($generatedTable) {
    $res = $conn->query("SELECT * FROM `$generatedTable`");
    echo "<h3>Edit Quiz: <code>$generatedTable</code></h3><form method='POST' enctype='multipart/form-data'>
          <input type='hidden' name='save_table' value='$generatedTable'>
          <table border='1'><tr><th>Q</th><th>Correct</th><th>W1</th><th>W2</th><th>W3</th><th>Image</th><th>Del</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr><td>" . htmlspecialchars($row['question']) . "</td>
              <td><textarea name='edited_rows[$id][correct]'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>
              <td><textarea name='edited_rows[$id][wrong1]'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>
              <td><textarea name='edited_rows[$id][wrong2]'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>
              <td><textarea name='edited_rows[$id][wrong3]'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>
              <td>" .
              ($row['image_url'] ? "<img src='" . htmlspecialchars($row['image_url']) . "' width='50'><br>" : "") .
              "<input type='file' name='image_files[$id]' accept='image/*'></td>
              <td><input type='checkbox' name='delete_rows[]' value='$id'></td></tr>";
    }
    echo "</table><br><button type='submit'>💾 Save Changes</button></form>";
}
