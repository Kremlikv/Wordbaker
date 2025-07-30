<?php
session_start();
require_once 'db.php';

// Load 1-second silence mp3 (used twice = 2 seconds)
$silence = file_get_contents('silence.mp3');
if (!$silence) {
    die("❌ Silence file (silence.mp3) missing.");
}

// === Google Cloud API setup ===
$api_key = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';  // Replace with your actual API key
$voices = [
    'czech'   => 'cs-CZ-Wavenet-A',
    'english' => 'en-US-Wavenet-D',
    'german'  => 'de-DE-Wavenet-B'
];

// === Get session table/column info ===
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing session info: table or column names.");
}

$source_key = strtolower($col1);
$target_key = strtolower($col2);

if (!isset($voices[$source_key]) || !isset($voices[$target_key])) {
    die("❌ Voice not configured for columns: $col1 / $col2");
}

$source_voice = $voices[$source_key];
$target_voice = $voices[$target_key];

// === Google Cloud TTS request ===
function googleTTS($text, $voice_name, $lang_code, $api_key) {
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=$api_key";
    $payload = json_encode([
        'input' => ['text' => $text],
        'voice' => [
            'languageCode' => $lang_code,
            'name' => $voice_name
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
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($http_code !== 200 || !isset($data['audioContent'])) {
        file_put_contents("log.txt", "TTS failed: $text\nHTTP: $http_code\nError: $err\nResponse: $response\n", FILE_APPEND);
        return null;
    }

    return base64_decode($data['audioContent']);
}

// === Build MP3 file ===
$final_audio = '';
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $source_text = trim($row[$col1]);
        $target_text = trim($row[$col2]);

        if ($source_text === '' || $target_text === '') continue;

        $src_audio = googleTTS($source_text, $source_voice, substr($source_voice, 0, 5), $api_key);
        $tgt_audio = googleTTS($target_text, $target_voice, substr($target_voice, 0, 5), $api_key);

        if ($src_audio && $tgt_audio) {
            // Add 2 seconds silence after each voice (1s × 2)
            $final_audio .= $src_audio . $silence . $silence . $tgt_audio . $silence . $silence;
        }
    }
}

$conn->close();

if ($final_audio === '') {
    die("No audio was generated. Check if the table contains valid text.");
}

// === Save to cache ===
file_put_contents("cache/$table.mp3", $final_audio);

// === Redirect to main UI ===
header("Location: main.php?table=" . urlencode($table));
exit;
