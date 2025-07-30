<?php
session_start();
require_once 'db.php';

// Google Cloud TTS API Key
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';

// Get session data
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing session info.");
}

// Define voices
$voices = [
    'czech'   => 'cs-CZ-Standard-B',
    'english' => 'en-GB-Standard-O',
    'german'  => 'de-DE-Wavenet-H'
];

$sourceLang = strtolower($col1);
$targetLang = strtolower($col2);
$sourceVoice = $voices[$sourceLang] ?? null;
$targetVoice = $voices[$targetLang] ?? null;

if (!$sourceVoice || !$targetVoice) {
    die("❌ No voice for: $sourceLang / $targetLang");
}

// Load rows
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if (!$result || $result->num_rows === 0) {
    die("❌ No data.");
}

// Build SSML
$ssml = "<speak>\n";
while ($row = $result->fetch_assoc()) {
    $cz = trim($row[$col1]);
    $foreign = trim($row[$col2]);

    if ($cz === '' || $foreign === '') continue;

    $ssml .= "<voice name=\"$sourceVoice\">" . htmlspecialchars($cz) . ".</voice>\n";
    $ssml .= "<break time=\"2s\"/>\n";
    $ssml .= "<voice name=\"$targetVoice\">" . htmlspecialchars($foreign) . ".</voice>\n";
    $ssml .= "<break time=\"2s\"/>\n";
}
$ssml .= "</speak>";

$conn->close();

// Google TTS SSML request
$request = [
    'input' => ['ssml' => $ssml],
    'voice' => [
        'languageCode' => 'en-US',  // must be set, but will be overridden by <voice>
        'name' => 'en-US-Standard-C'
    ],
    'audioConfig' => ['audioEncoding' => 'MP3']
];

$ch = curl_init('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['audioContent'])) {
    file_put_contents("log_ssml.txt", "❌ SSML failure\nHTTP $httpCode\n$response\n", FILE_APPEND);
    die("❌ TTS failed.");
}

file_put_contents("cache/$table.mp3", base64_decode($data['audioContent']));
header("Location: main.php?table=" . urlencode($table));
exit;
