<?php
require_once 'db.php';
require_once 'session.php';
include 'styling.php';

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';
$THROTTLE_SECONDS = 1;

$PIXABAY_API_KEY = 'YOUR_PIXABAY_API_KEY_HERE'; // Insert your free Pixabay key

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

    // If AI didn't provide a usable image, try Pixabay
    if (empty($imageUrl)) {
        $imageUrl = getImageFromPixabay($correctAnswer, $pixabayKey);
    }

    // If Pixabay also failed, fallback to Wikimedia
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
// (this part stays unchanged from your working version)

/* --- Generate quiz --- */
// (replace callOpenRouter call with new one including $PIXABAY_API_KEY)

