<?php
// === CONFIG ===
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU'; // Replace with your real key
$cacheDir = __DIR__ . '/cache/';
$logFile = __DIR__ . '/log_snippet_errors.txt';

// === INPUT ===
$text = $_GET['text'] ?? '';
$lang = strtolower($_GET['lang'] ?? '');

if (!$text || !$lang) {
    http_response_code(400);
    echo "Missing 'text' or 'lang'.";
    file_put_contents($logFile, "❌ Missing input: text='$text', lang='$lang'\n", FILE_APPEND);
    exit;
}

// === VOICE MAPPING ===
$voiceMap = [
    'czech'   => 'cs-CZ-Standard-B',
    'english' => 'en-GB-Standard-O',
    'german'  => 'de-DE-Wavenet-H'
];

if (!isset($voiceMap[$lang])) {
    http_response_code(400);
    echo "Unsupported language: $lang";
    file_put_contents($logFile, "❌ Unsupported language: $lang\n", FILE_APPEND);
    exit;
}

$voice = $voiceMap[$lang];
$langCode = substr($voice, 0, 5);
$hash = md5($lang . '_' . $text);
$filename = $cacheDir . $hash . '.mp3';

// === Return from cache if available ===
if (file_exists($filename)) {
    header('Content-Type: audio/mpeg');
    readfile($filename);
    exit;
}

// === GOOGLE TTS API REQUEST ===
$payload = json_encode([
    'input' => ['text' => $text],
    'voice' => [
        'languageCode' => $langCode,
        'name' => $voice
    ],
    'audioConfig' => ['audioEncoding' => 'MP3']
]);

$ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['audioContent'])) {
    http_response_code(500);
    echo "TTS failed.";
    file_put_contents($logFile, "❌ TTS API failed [$lang] \"$text\"\nResponse: $response\n\n", FILE_APPEND);
    exit;
}

// === SAVE AND STREAM MP3 ===
file_put_contents($filename, base64_decode($data['audioContent']));
header('Content-Type: audio/mpeg');
readfile($filename);
