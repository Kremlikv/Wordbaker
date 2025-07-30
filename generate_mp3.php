<?php
session_start();
require_once 'db.php';

$silence = file_get_contents('silence.mp3');
if (!$silence) {
    die("Silence file missing.");
}

$googleApiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';

$voiceMap = [
    'czech'   => ['name' => 'cs-CZ-Standard-B', 'languageCode' => 'cs-CZ'],
    'english' => ['name' => 'en-GB-Standard-O', 'languageCode' => 'en-GB'],
    'german'  => ['name' => 'de-DE-Wavenet-H',  'languageCode' => 'de-DE'],
];

$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';

$source_key = strtolower($col1);
$target_key = strtolower($col2);

if (!isset($voiceMap[$source_key]) || !isset($voiceMap[$target_key])) {
    die("Voice not configured for columns: $col1 / $col2");
}

$source_voice = $voiceMap[$source_key];
$target_voice = $voiceMap[$target_key];

function generateTTS($text, $voice, $apiKey) {
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=" . urlencode($apiKey);
    $payload = json_encode([
        'input' => ['text' => $text],
        'voice' => [
            'languageCode' => $voice['languageCode'],
            'name' => $voice['name']
        ],
        'audioConfig' => [
            'audioEncoding' => 'MP3'
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        file_put_contents("log.txt", "TTS failed for: $text
HTTP Code: $http_code
Error: $err
", FILE_APPEND);
        return null;
    }

    $data = json_decode($response, true);
    return base64_decode($data['audioContent'] ?? '');
}

$final_audio = "";

$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $source_text = trim($row[$col1]);
        $target_text = trim($row[$col2]);

        if ($source_text === "" || $target_text === "") continue;

        $src_audio = generateTTS($source_text, $source_voice, $googleApiKey);
        $tgt_audio = generateTTS($target_text, $target_voice, $googleApiKey);

        if ($src_audio && $tgt_audio) {
            $final_audio .= $src_audio . $silence . $tgt_audio . $silence;
        }
    }
}

$conn->close();

if ($final_audio === '') {
    die("No audio was generated. Check if the table contains valid data.");
}

file_put_contents("cache/$table.mp3", $final_audio);
header("Location: main.php");
exit;
?>