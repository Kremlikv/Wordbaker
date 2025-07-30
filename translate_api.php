<?php
// translate_api.php
header('Content-Type: application/json');

$text = $_POST['text'] ?? '';
$source = $_POST['source'] ?? 'auto';
$target = $_POST['target'] ?? 'cs';

if (!$text) {
    echo json_encode(['error' => 'Missing text']);
    exit;
}

$url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$source}|{$target}";
$response = @file_get_contents($url);

if (!$response) {
    echo json_encode(['error' => 'Translation failed']);
    exit;
}

$data = json_decode($response, true);
$translated = $data['responseData']['translatedText'] ?? '[Translation failed]';

echo json_encode(['translated' => $translated]);
