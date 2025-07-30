<?php
session_start();
require_once 'db.php';

// Your Google Cloud TTS API key
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';

// Get table + column info
$table = $_SESSION['table'] ?? '';
$col1 = $_SESSION['col1'] ?? '';
$col2 = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing session info.");
}

// Voice mappings
$voiceMap = [
    'czech'   => 'cs-CZ-Standard-B',
    'english' => 'en-GB-Standard-O',
    'german'  => 'de-DE-Wavenet-H'
];

$srcKey = strtolower($col1);
$tgtKey = strtolower($col2);

$srcVoice = $voiceMap[$srcKey] ?? null;
$tgtVoice = $voiceMap[$tgtKey] ?? null;

if (!$srcVoice || !$tgtVoice) {
    die("❌ No voice for $col1 / $col2");
}

// Create folder if needed
$folder = "cache/" . $table;
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// Google TTS function
function googleTTS($text, $voice, $apiKey) {
    $langCode = substr($voice, 0, 5);
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey";

    $data = [
        'input' => ['text' => $text],
        'voice' => ['languageCode' => $langCode, 'name' => $voice],
        'audioConfig' => ['audioEncoding' => 'MP3']
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    return ($code === 200 && isset($decoded['audioContent'])) ? base64_decode($decoded['audioContent']) : null;
}

// Process table rows
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if (!$result) die("❌ Query failed.");

$index = 1;
while ($row = $result->fetch_assoc()) {
    $srcText = trim($row[$col1]);
    $tgtText = trim($row[$col2]);

    if (!$srcText || !$tgtText) continue;

    $srcMp3 = googleTTS($srcText, $srcVoice, $apiKey);
    $tgtMp3 = googleTTS($tgtText, $tgtVoice, $apiKey);

    if ($srcMp3) file_put_contents("$folder/word_" . str_pad($index, 3, '0', STR_PAD_LEFT) . "A.mp3", $srcMp3);
    if ($tgtMp3) file_put_contents("$folder/word_" . str_pad($index, 3, '0', STR_PAD_LEFT) . "B.mp3", $tgtMp3);

    $index++;
}

$conn->close();
header("Location: main.php?table=" . urlencode($table));
exit;
