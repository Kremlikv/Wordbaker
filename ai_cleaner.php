<?php
// ai_cleaner.php
header('Content-Type: application/json');

$apiKey = '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'; //

$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? ''; 

if (!$text) {
    http_response_code(400);
    echo json_encode(['error' => 'No text provided']);
    exit;
}

$prompt = "Clean up the following text extracted from a scanned PDF. Fix OCR errors, broken words, strange characters, and punctuation. Do NOT translate or add content. Only correct and restore the original text:\n\n" . $text;

$data = [
    "model" => "gpt-3.5-turbo",
    "messages" => [
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.4
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . 'Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to OpenAI API']);
    exit;
}

$result = json_decode($response, true);
$cleaned = $result['choices'][0]['message']['content'] ?? '';

if (!$cleaned) {
    http_response_code(500);
    echo json_encode(['error' => 'No cleaned content received']);
    exit;
}

echo json_encode(['cleaned' => trim($cleaned)]);
?>
