<?php
session_start();
require_once 'db.php';

// Your Google Cloud API Key
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';

// Session info
$table = $_SESSION['table'] ?? '';
$col1 = $_SESSION['col1'] ?? '';
$col2 = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing session info.");
}

// Supported voices
$voices = [
    'czech'   => ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'],
    'english' => ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'],
    'german'  => ['name' => 'de-DE-Standard-H',  'code' => 'de-DE'] 
];


$srcLang = strtolower($col1);
$tgtLang = strtolower($col2);

if (!isset($voices[$srcLang], $voices[$tgtLang])) {
    die("❌ Unsupported language columns: $col1 / $col2");
}

$srcVoice = $voices[$srcLang];
$tgtVoice = $voices[$tgtLang];

// Fetch data
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if (!$result || $result->num_rows === 0) {
    die("❌ No data found in table.");
}

// Build SSML
$ssml = "<speak>\n";
while ($row = $result->fetch_assoc()) {
    $srcText = trim($row[$col1]);
    $tgtText = trim($row[$col2]);

    if ($srcText === '' || $tgtText === '') continue;

    $ssml .= '<voice name="' . $srcVoice['name'] . '">' . htmlspecialchars($srcText) . ".</voice>\n";
    $ssml .= "<break time=\"2s\"/>\n";
    $ssml .= '<voice name="' . $tgtVoice['name'] . '">' . htmlspecialchars($tgtText) . ".</voice>\n";
    $ssml .= "<break time=\"2s\"/>\n";
}
$ssml .= "</speak>";

$conn->close();

// Send to Google TTS
$payload = [
    'input' => ['ssml' => $ssml],
    'voice' => [
        'languageCode' => $srcVoice['code'],  // Required base voice
        'name' => $srcVoice['name']
    ],
    'audioConfig' => ['audioEncoding' => 'MP3']
];

$ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['audioContent'])) {
    file_put_contents("log_ssml.txt", "❌ Failed\nHTTP: $httpCode\n$response\n", FILE_APPEND);
    die("❌ Google TTS SSML failed. See log_ssml.txt");
}

file_put_contents("cache/$table.mp3", base64_decode($data['audioContent']));
header("Location: main.php?table=" . urlencode($table));
exit;
