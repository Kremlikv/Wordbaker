<?php
require_once 'session.php';
require_once 'db.php';
require_once 'vendor/autoload.php';

use OpenAI\Client;

$apiKey = $_ENV['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'] ?? 'YOUR_API_KEY_HERE';
$client = OpenAI::client($apiKey);

$table = $_POST['source_table'] ?? '';
$sourceLang = $_POST['source_lang'] ?? '';
$targetLang = $_POST['target_lang'] ?? '';

if (!$table || !$sourceLang || !$targetLang) {
    die("Missing input data.");
}

$quizTable = 'quiz_' . $table;
$conn->query("DROP TABLE IF EXISTS `$quizTable`");

$conn->query("CREATE TABLE `$quizTable` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_word VARCHAR(255),
    target_word VARCHAR(255),
    language VARCHAR(50),
    question TEXT,
    correct_answer TEXT,
    wrong1 TEXT,
    wrong2 TEXT,
    wrong3 TEXT,
    image_url TEXT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$res = $conn->query("SELECT * FROM `$table`);
while ($row = $res->fetch_assoc()) {
    $source = $conn->real_escape_string($row[$sourceLang] ?? '');
    $target = $conn->real_escape_string($row[$targetLang] ?? '');
    if (!$source || !$target) continue;

    $prompt = "Generate 3 plausible wrong alternatives for this word pair in $sourceLang → $targetLang:
Source: $source
Target: $target

Avoid nonsense or exact reversals. Format:
1. Wrong1
2. Wrong2
3. Wrong3";

    $response = $client->chat()->create([
        'model' => 'anthropic/claude-3-haiku',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]);

    $text = $response['choices'][0]['message']['content'] ?? '';
    preg_match_all('/\d+\.\s*(.+)/', $text, $matches);
    $wrongs = $matches[1] ?? [];
    if (count($wrongs) < 3) continue;

    $question = $conn->real_escape_string($source);
    $correct = $conn->real_escape_string($target);
    $wrong1 = $conn->real_escape_string($wrongs[0]);
    $wrong2 = $conn->real_escape_string($wrongs[1]);
    $wrong3 = $conn->real_escape_string($wrongs[2]);
    $language = $conn->real_escape_string($targetLang);

    $conn->query("INSERT INTO `$quizTable`
        (source_word, target_word, language, question, correct_answer, wrong1, wrong2, wrong3, image_url)
        VALUES
        ('$source', '$target', '$language', '$question', '$correct', '$wrong1', '$wrong2', '$wrong3', '')");
}

echo "✅ Quiz table '$quizTable' created.";
