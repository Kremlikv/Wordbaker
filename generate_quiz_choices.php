<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

$PIXABAY_API_KEY = '51629627-a41f1d96812d8b351d3f25867'; // Insert your free Pixabay key

/* --- Pixabay direct image search --- */
function getImageFromPixabay($searchTerm, $pixabayKey) {
    if (empty($pixabayKey)) return '';
    $url = "https://pixabay.com/api/?key={$pixabayKey}&q=" . urlencode($searchTerm) . "&image_type=photo&per_page=3&safe_search=true";
    $json = @file_get_contents($url);
    if (!$json) return '';
    $data = json_decode($json, true);
    if (!empty($data['hits'][0]['largeImageURL'])) {
        return $data['hits'][0]['largeImageURL'];
    }
    return '';
}

/* --- Wikimedia fallback --- */
function getWikimediaImage($searchTerm) {
    $apiUrl = "https://commons.wikimedia.org/w/api.php?action=query&generator=search&gsrsearch=" . urlencode($searchTerm) . "&gsrlimit=1&prop=imageinfo&iiprop=url&format=json";
    $json = @file_get_contents($apiUrl);
    if (!$json) return '';
    $data = json_decode($json, true);
    if (!isset($data['query']['pages'])) return '';
    foreach ($data['query']['pages'] as $page) {
        if (isset($page['imageinfo'][0]['url'])) {
            return $page['imageinfo'][0]['url'];
        }
    }
    return '';
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

/* --- AI + Pixabay + Wikimedia image --- */
function callOpenRouter($apiKey, $model, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle, $pixabayKey) {
    $prompt = <<<EOT
You are a professional language teacher who creates multiple-choice vocabulary quizzes.

For the given Czech word and its correct translation in $targetLang:

1. Generate 3 plausible but incorrect translations (realistic learner mistakes).
2. Suggest a URL to a royalty-free or public-domain image that illustrates the correct translation.

Image rules:
- Must be from public domain / CC0 / royalty-free sources.
- Prefer Wikimedia Commons, Pixabay, Unsplash.
- Direct link to image file (.jpg, .png, .webp).

Example:

Czech: "stůl"
Correct translation: "der Tisch"
Wrong Answers:
1. die Tisch (article confusion)
2. der Tasche (false friend)
3. der Tich (spelling error)
Image URL: https://upload.wikimedia.org/wikipedia/commons/3/3a/Wooden_table.jpg

---

Czech: "$czechWord"
Correct translation: "$correctAnswer"
Wrong alternatives and image URL:
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
        return trim(preg_replace('/\s*\([^)]*\)/', '', trim($a)), "*\"“”‘’' ");
    }, $matches[1]);

    preg_match('/Image URL:\s*(https?:\/\/\S+\.(?:jpg|jpeg|png|webp))/i', $output, $imgMatch);
    $imageUrl = $imgMatch[1] ?? '';

    // Validate AI-provided image
    $valid = false;
    if (!empty($imageUrl)) {
        $lower = strtolower($imageUrl);
        $extOk = preg_match('/\.(jpg|jpeg|png|webp)$/', $lower);
        $notBlocked = (strpos($lower, 'wikimedia.org') === false);
        if ($extOk && $notBlocked) {
            $valid = true;
        }
    }

    // If not valid, try Pixabay
    if (!$valid) {
        $imageUrl = getImageFromPixabay($correctAnswer, $pixabayKey);
    }

    // If still empty, fallback to Wikimedia
    if (empty($imageUrl)) {
        $imageUrl = getWikimediaImage($correctAnswer);
    }

    return [
        'wrongAnswers' => count($wrongAnswers) >= 3 ? array_slice($wrongAnswers, 0, 3) : [],
        'imageUrl' => $imageUrl
    ];
}

function naiveWrongAnswers($correct) {
    return [$correct . 'x', strrev($correct), substr($correct, 1) . substr($correct, 0, 1)];
}

/* --- Save edits with uploads --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_table'])) {
    $saveTable = $conn->real_escape_string($_POST['save_table']);
    $editedRows = $_POST['edited_rows'] ?? [];
    $deleteRows = $_POST['delete_rows'] ?? [];

    $uploadDir = __DIR__ . "/uploads/quiz_images/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($editedRows as $id => $row) {
        if (in_array($id, $deleteRows)) {
            $conn->query("DELETE FROM `$saveTable` WHERE id=" . intval($id));
            continue;
        }

        $imageUrl = trim($row['image_url']);

        if (isset($_FILES['image_file']['name'][$id]) && $_FILES['image_file']['error'][$id] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image_file']['tmp_name'][$id];
            $fileSize = $_FILES['image_file']['size'][$id];
            $ext = strtolower(pathinfo($_FILES['image_file']['name'][$id], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && $fileSize <= 2 * 1024 * 1024) {
                $newName = "quiz_" . intval($id) . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                    $imageUrl = "uploads/quiz_images/" . $newName;
                }
            }
        }

        $stmt = $conn->prepare("UPDATE `$saveTable` SET correct_answer=?, wrong1=?, wrong2=?, wrong3=?, image_url=? WHERE id=?");
        $stmt->bind_param("sssssi", $row['correct'], $row['wrong1'], $row['wrong2'], $row['wrong3'], $imageUrl, $id);
        $stmt->execute();
        $stmt->close();
    }
}

/* --- Generate quiz --- */
$generatedTable = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_quiz']) && !empty($selectedTable)) {
    $table = $conn->real_escape_string($selectedTable);
    $sourceLang = $autoSourceLang;
    $targetLang = $autoTargetLang;

    $result = $conn->query("SELECT * FROM `$table`");
    $totalRows = $result->num_rows;
    if ($result && $totalRows > 0) {
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

        echo "<div style='text-align:center;'><div style='width:50%;margin:auto;border:1px solid #333;height:30px;'>
                <div id='progressBar' style='height:100%;width:0%;background:green;color:white;text-align:center;line-height:30px;'>0%</div>
              </div></div>";
        ob_flush(); flush();

        $processed = 0;
        while ($row = $result->fetch_assoc()) {
            $question = trim($row[$col1]);
            $correct = trim($row[$col2]);
            if ($question === '' || $correct === '') continue;

            $aiResult = callOpenRouter($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $question, $correct, $targetLang, $OPENROUTER_REFERER, $APP_TITLE, $PIXABAY_API_KEY);
            $wrongAnswers = $aiResult['wrongAnswers'] ?: naiveWrongAnswers($correct);
            $imageUrl = $aiResult['imageUrl'];

            [$wrong1, $wrong2, $wrong3] = array_pad($wrongAnswers, 3, '');
            $stmt = $conn->prepare("INSERT INTO `$quizTable`
                (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $question, $correct, $wrong1, $wrong2, $wrong3, $sourceLang, $targetLang, $imageUrl);
            $stmt->execute();
            $stmt->close();

            $processed++;
            $percent = intval(($processed / $totalRows) * 100);
            echo "<script>document.getElementById('progressBar').style.width='{$percent}%';
                         document.getElementById('progressBar').textContent='{$percent}%';</script>";
            ob_flush(); flush();

            if ($THROTTLE_SECONDS > 0) sleep($THROTTLE_SECONDS);
        }

        echo "<script>document.getElementById('progressBar').style.background='blue';
                      document.getElementById('progressBar').textContent='✅ Complete';</script>";
        $generatedTable = $quizTable;
    }
}

/* --- Output --- */
echo "<div class='content'>👤 Logged in as " . $_SESSION['username'] . " | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";

include 'file_explorer.php';

if (!empty($selectedTable) && !isset($_POST['generate_quiz'])) {
    echo "<div style='text-align:center;margin-top:10px;font-weight:bold;color:green;'>File \"$selectedTable\" selected</div>";
    echo "<form method='POST' style='text-align:center; margin-top:20px;'>
            <input type='hidden' name='table' value='" . htmlspecialchars($selectedTable) . "'>
            <input type='hidden' name='generate_quiz' value='1'>
            <button type='submit'>🚀 Generate Quiz Set from " . htmlspecialchars($selectedTable) . "</button>
          </form>";
}

if (!empty($generatedTable)) {
    $res = $conn->query("SELECT * FROM `$generatedTable`");
    echo "<h3 style='text-align:center;'>📜 Edit Generated Quiz: <code>$generatedTable</code></h3>";
    echo "<form method='POST' enctype='multipart/form-data' style='text-align:center;'>
            <input type='hidden' name='save_table' value='" . htmlspecialchars($generatedTable) . "'>
            <table border='1' cellpadding='5' cellspacing='0' style='margin:auto;'>
                <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Image URL</th><th>Upload File</th><th>Preview</th><th>Delete</th></tr>";
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>
                <td>" . htmlspecialchars($row['question']) . "</td>
                <td><textarea name='edited_rows[$id][correct]' oninput='autoResize(this)'>" . htmlspecialchars($row['correct_answer']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong1]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong1']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong2]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong2']) . "</textarea></td>
                <td><textarea name='edited_rows[$id][wrong3]' oninput='autoResize(this)'>" . htmlspecialchars($row['wrong3']) . "</textarea></td>
                <td><input type='text' name='edited_rows[$id][image_url]' value='" . htmlspecialchars($row['image_url']) . "'></td>
                <td><input type='file' name='image_file[$id]'></td>
                <td>" . (!empty($row['image_url']) ? "<img src='" . htmlspecialchars($row['image_url']) . "' style='max-height:50px;'>" : "") . "</td>
                <td><input type='checkbox' name='delete_rows[]' value='" . intval($id) . "'></td>
              </tr>";
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
    document.querySelectorAll("textarea").forEach(el => {
        autoResize(el);
        el.addEventListener("input", () => autoResize(el));
    });
});
</script>
