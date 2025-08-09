<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// 1. Read input text from JSON body
$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? '';

if (!$text) {
    echo json_encode(['error' => 'No text provided.']);
    exit;
}

// 2. Your OpenRouter API key
// $CLEANER_API_KEY = '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'; // Sign up at https://openrouter.ai
// key defined in config.php 


// 3. Model to use (must be one that allows free tier, like this one)
$model = 'tngtech/deepseek-r1t2-chimera:free';

// 4. Construct the API request
$data = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'You are an AI that cleans up OCR-scanned text. Fix spacing, remove hyphen breaks, correct typos and punctuation.'],
        ['role' => 'user', 'content' => $text]
    ],
    'temperature' => 0.3
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $CLEANER_API_KEY",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 5. Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 6. Handle errors
if (!$response) {
    echo json_encode(['error' => "Curl error: $curlError"]);
    exit;
}

$result = json_decode($response, true);
$cleaned = $result['choices'][0]['message']['content'] ?? '';

if ($cleaned) {
    echo json_encode(['cleaned' => $cleaned]);
} else {
    echo json_encode([
        'error' => 'No cleaned result returned.',
        'raw_response' => $response,
        'http_code' => $httpCode
    ]);
}
