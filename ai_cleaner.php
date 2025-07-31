<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$text = trim($data['text'] ?? '');

if (!$text) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

// === CONFIG SECTION ===
$useOpenRouter = true;

$openrouter_key = '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'; // Sign up at https://openrouter.ai
$openai_key     = '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';     // Optional fallback if needed

$model_openrouter = 'tngtech/deepseek-r1t2-chimera:free';
$model_openai     = 'gpt-3.5-turbo';

$headers = [
    'Content-Type: application/json',
];

// === Prepare Payload ===
$messages = [
    ["role" => "system", "content" => "You are a helpful assistant that improves raw OCR text by fixing broken words, punctuation, and structure."],
    ["role" => "user", "content" => $text]
];

$payload = json_encode([
    "model" => $useOpenRouter ? $model_openrouter : $model_openai,
    "messages" => $messages,
    "temperature" => 0.2,
]);

// === Choose API endpoint ===
if ($useOpenRouter && $openrouter_key) {
    $api_url = "https://openrouter.ai/api/v1/chat/completions";
    $headers[] = "Authorization: Bearer $openrouter_key";
} elseif ($openai_key) {
    $api_url = "https://api.openai.com/v1/chat/completions";
    $headers[] = "Authorization: Bearer $openai_key";
} else {
    echo json_encode(['error' => 'No API key configured']);
    exit;
}

// === Send request ===
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// === Parse result ===
if ($error || !$response) {
    echo json_encode(['error' => 'Connection failed: ' . $error]);
    exit;
}

$result = json_decode($response, true);

$reply = $result['choices'][0]['message']['content'] ?? null;

if ($reply) {
    echo json_encode(['cleaned' => $reply]);
} else {
    echo json_encode(['error' => 'No cleaned response received']);
}
