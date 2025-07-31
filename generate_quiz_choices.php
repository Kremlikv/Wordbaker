<?php
require_once 'db.php';
require_once 'session.php';

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

function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer) {
    $prompt = "The correct translation of the Czech word \"$czechWord\" into $targetLang is \"$correctAnswer\". "
            . "Generate 3 plausible wrong alternatives based on typical mistakes students make. "
            . "Use errors like wrong articles, false friends, misspellings, or common confusion. "
            . "Return only the 3 alternatives as a numbered list.";

    $data = [
        "model" => $model,
        "messages" => [
            ["role" => "user", "content" => $prompt]
        ]
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer YOUR_API_KEY_HERE", // Replace with your actual key
        "HTTP-Referer: https://kremlik.byethost15.com",
        "X-Title: KahootGenerator"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (!isset($decoded['choices'][0]['message']['content'])) return [];

    $output = trim($decoded['choices'][0]['message']['content']);
    preg_match_all('/^\d+[\).\s-]+(.+)$/m', $output, $matches);
    return $matches[1] ?? [];
}

// --- Begin Page Logic ---
$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
$tables = getUserTables($conn, $username);

$generatedTable = '';

// Handle save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table']) && isset($_POST['edited_rows'])) {
    $editTable = $conn->real_escape_string($_POST['save_table']);
    foreach ($_POST['edited_rows'] as $id => $row) {
        $stmt = $conn->prepare("UPDATE `$editTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=? WHERE id=?");
        $stmt->bind_param("ssssi",
            $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $id
        );
        $stmt->execute();
        $stmt->close();
    }
    echo "<p style='color: green;'><strong>Changes saved to table:</strong> $editTable</p>";
    $generatedTable = $editTable;
}

// Handle quiz generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table'], $_POST['source_lang'], $_POST['target_lang']) && !isset($_POST['save_table'])) {
    $table = $conn->real_escape_string($_POST['table']);
    $sourceLang = htmlspecialchars($_POST['source_lang']);
    $targetLang = htmlspecialchars($_POST['target_lang']);
    $model = "tngtech/deepseek-r1t2-chimera:free";

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

            $wrongAnswers = callOpenRouter("YOUR_API_KEY_HERE", $model, $question, $correct, $targetLang, "https://kremlik.byethost15.com");
            $wrong1 = $wrongAnswers[0] ?? '';
            $wrong2 = $wrongAnswers[1] ?? '';
            $wrong3 = $wrongAnswers[2] ?? '';

            $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $sourceLang, $targetLang);
            $stmt->execute();
            $stmt->close();

            echo "<li><strong>$question</strong>: ✅ $correct | ❌ $wrong1, $wrong2, $wrong3</li>";
            flush();
            ob_flush();
            sleep(1); // avoid flooding
        }
        echo "</ul><hr>";
        $generatedTable = $quizTable;
    } else {
        echo "<p style='color:red;'>No data found in table.</p>";
    }
}

// Show editable table after generation or save
if (!empty($generatedTable)) {
    $editTable = $conn->real_escape_string($generatedTable);
    $res = $conn->query("SELECT * FROM `$editTable`");
    echo "<h3>📝 Edit Generated Quiz: <code>$editTable</code></h3>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='save_table' value='" . htmlspecialchars($editTable) . "'>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['question']) . "</td>";
        echo "<td><input type='text' name='edited_rows[$id][correct]' value='" . htmlspecialchars($row['correct_answer']) . "'></td>";
        echo "<td><input type='text' name='edited_rows[$id][wrong1]' value='" . htmlspecialchars($row['wrong1']) . "'></td>";
        echo "<td><input type='text' name='edited_rows[$id][wrong2]' value='" . htmlspecialchars($row['wrong2']) . "'></td>";
        echo "<td><input type='text' name='edited_rows[$id][wrong3]' value='" . htmlspecialchars($row['wrong3']) . "'></td>";
        echo "</tr>";
    }
    echo "</table><br><button type='submit'>💾 Save Changes</button></form><br>";
}

// Initial form
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
echo "</form>";
?>
