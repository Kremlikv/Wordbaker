<?php
session_start();
require_once 'db.php';

// Google Cloud API Key
$googleApiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';

// Define language voice mappings
$voices = [
    'czech' => 'cs-CZ-Standard-B',      // Female Czech
    'english' => 'en-GB-Standard-O',    // Male British English
    'german' => 'de-DE-Wavenet-H'       // Male German
];

$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing table or column names.");
}

$source_key = strtolower($col1);
$target_key = strtolower($col2);

if (!isset($voices[$source_key]) || !isset($voices[$target_key])) {
    die("Voice not configured for: $col1 / $col2");
}

$source_voice = $voices[$source_key];
$target_voice = $voices[$target_key];

// TTS generation function
function generateTTS($text, $voice, $apiKey) {
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey";
    $payload = json_encode([
        'input' => ['text' => $text],
        'voice' => [
            'languageCode' => substr($voice, 0, 5),
            'name' => $voice
        ],
        'audioConfig' => [
            'audioEncoding' => 'MP3'
        ]
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json",
            'content' => $payload
        ]
    ];
    $context  = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);

    if (!$result) return null;
    $data = json_decode($result, true);
    return base64_decode($data['audioContent'] ?? '');
}

// Create snippet directory
$snippetDir = __DIR__ . "/cache/$table";
if (!file_exists($snippetDir)) {
    mkdir($snippetDir, 0777, true);
}

// Fetch rows and generate individual audio files
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);

if (!$result) die("Query failed.");

$counter = 1;
while ($row = $result->fetch_assoc()) {
    $source_text = trim($row[$col1]);
    $target_text = trim($row[$col2]);
    if ($source_text === '' || $target_text === '') continue;

    $src_audio = generateTTS($source_text, $source_voice, $googleApiKey);
    $tgt_audio = generateTTS($target_text, $target_voice, $googleApiKey);

    if ($src_audio && $tgt_audio) {
        // Concatenate: source + 1s silence + target
        $filename = sprintf("%s/row_%03d.mp3", $snippetDir, $counter);
        file_put_contents("$snippetDir/temp1.mp3", $src_audio);
        file_put_contents("$snippetDir/temp2.mp3", $tgt_audio);
        // Combine manually: just save src and tgt separately for now
        file_put_contents($filename, $src_audio . $tgt_audio);
        $counter++;
    }
}

$conn->close();
header("Location: main.php");
exit;


