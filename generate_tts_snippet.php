<?php
require_once 'db.php';
session_start();

// === CONFIG ===
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU'; // Replace with your actual key
$logFile = __DIR__ . '/log_snippet_errors.txt';

// === INPUT ===
$text = $_GET['text'] ?? '';
$lang = strtolower($_GET['lang'] ?? '');
$table = $_SESSION['table'] ?? 'default';

if (!$text || !$lang) {
    http_response_code(400);
    echo "Missing text or language.";
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

// === Build safe filename ===
$safeText = preg_replace('/[^a-z0-9]/i', '_', strtolower($text));
$folder = __DIR__ . "/cache/$table";
$filename = "$folder/$safeText" . "_$lang.mp3";

// === Create folder if needed ===
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// === Serve from cache ===
if (file_exists($filename)) {
    header('Content-Type: audio/mpeg');
    readfile($filename);
    exit;
}

// === Google TTS Request ===
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
    file_put_contents($logFile, "❌ TTS failed [$lang]: $text\nResponse: $response\n\n", FILE_APPEND);
    exit;
}

// === Save and Output ===
file_put_contents($filename, base64_decode($data['audioContent']));
header('Content-Type: audio/mpeg');
readfile($filename);
