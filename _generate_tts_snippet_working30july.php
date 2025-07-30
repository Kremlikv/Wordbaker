<?php

// ✅ Enable error reporting for debugging (optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Use GET parameters for TTS
$text = $_GET['text'] ?? '';
$lang = strtolower(trim($_GET['lang'] ?? ''));

if (!$text || !$lang) {
    http_response_code(400);
    exit("Missing 'text' or 'lang'.");
}

// ✅ Normalize and map language values
$voiceMap = [
    'czech' => 'czech', 'cz' => 'czech',
    'english' => 'english', 'en' => 'english',
    'german' => 'german', 'de' => 'german',
    'french' => 'french', 'fr' => 'french',
    'italian' => 'italian', 'it' => 'italian',
];

$lang = $voiceMap[$lang] ?? '';
if (!$lang) {
    http_response_code(400);
    exit("Unsupported language value.");
}

// ✅ Set correct voice IDs per language
$api_key = 'sk_3fd1ed62c6431f562064ece5d9e46dbb3e9cdf4b96451734';  // Replace with your actual key
$voices = [
    'czech' => 'OAAjJsQDvpg3sVjiLgyl',
    'english' => 'goT3UYdM9bhm0n2lmKQx',
    'german' => 'zl7GSCFv2aKISCB2LjZz',
    'french' => 'INSERT_FRENCH_VOICE_ID_HERE', // Optional
    'italian' => 'gANhjQSlAkHRXHeKewFT',
];

$voice_id = $voices[$lang] ?? '';
if (!$voice_id) {
    http_response_code(400);
    exit("No voice ID configured for: $lang");
}

// ✅ Create cache folder if it doesn't exist
$cacheDir = __DIR__ . '/cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// ✅ Generate a safe filename based on text and language
$hash = md5($text . $lang);
$cachedFile = "$cacheDir/$hash.mp3";

// ✅ Serve from cache if available
if (file_exists($cachedFile)) {
    header('Content-Type: audio/mpeg');
    header('Content-Disposition: inline; filename="snippet.mp3"');
    readfile($cachedFile);
    exit;
}

// ✅ Call ElevenLabs API to generate audio
$url = "https://api.elevenlabs.io/v1/text-to-speech/$voice_id/stream";
$payload = json_encode([
    'text' => $text,
    'model_id' => 'eleven_multilingual_v2',
    'voice_settings' => [
        'stability' => 0.4,
        'similarity_boost' => 0.8
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "xi-api-key: $api_key",
        "Content-Type: application/json",
        "Accept: audio/mpeg"
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    http_response_code(500);
    echo "TTS generation failed. [$http_code] $error";
    exit;
}

// ✅ Save the response to cache
file_put_contents($cachedFile, $response);

// ✅ Serve the generated audio
header('Content-Type: audio/mpeg');
header('Content-Disposition: inline; filename="snippet.mp3"');
echo $response;
exit;
