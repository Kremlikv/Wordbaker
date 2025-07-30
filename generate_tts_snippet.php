<?php
require_once 'db.php';

$text1 = $_POST['text1'] ?? '';
$text2 = $_POST['text2'] ?? '';
$lang1 = strtolower($_POST['lang1'] ?? 'czech');
$lang2 = strtolower($_POST['lang2'] ?? 'english');

$silence = file_get_contents('silence.mp3');
if (!$silence) {
    die("Silence file missing.");
}

$googleApiKey = 'YOUR_GOOGLE_CLOUD_API_KEY';

$voiceMap = [
    'czech'   => ['name' => 'cs-CZ-Standard-B', 'languageCode' => 'cs-CZ'],
    'english' => ['name' => 'en-GB-Standard-O', 'languageCode' => 'en-GB'],
    'german'  => ['name' => 'de-DE-Wavenet-H',  'languageCode' => 'de-DE'],
];

if (!isset($voiceMap[$lang1]) || !isset($voiceMap[$lang2])) {
    http_response_code(400);
    die("❌ Unsupported language configuration.");
}

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

$audio1 = $text1 ? generateTTS($text1, $voiceMap[$lang1], $googleApiKey) : '';
$audio2 = $text2 ? generateTTS($text2, $voiceMap[$lang2], $googleApiKey) : '';
$final = $audio1 . $silence . $audio2;

if (!$final) {
    http_response_code(500);
    die("Failed to generate audio.");
}

header("Content-Type: audio/mpeg");
echo $final;
exit;
?>