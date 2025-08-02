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

function translate_text($text, $source, $target) {
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$source}|{$target}";
    $response = @file_get_contents($url);
    if (!$response) return '[Translation failed]';
    $data = json_decode($response, true);
    return $data['responseData']['translatedText'] ?? '[Translation failed]';
}

// Use same sentence splitting as in translator.php
$mergedText = preg_replace("/\s+\n\s+|\n+/", ' ', $text);
$sentences = preg_split('/(?<=[.!?:])\s+(?=[A-Z\xC0-\xFF])/', $mergedText);
$lines = array_filter(array_map('trim', $sentences));

// Translate sentence by sentence
$results = [];
foreach ($lines as $line) {
    $results[] = translate_text($line, $source, $target);
    usleep(500000); // 500ms delay to avoid throttling
}

echo json_encode(['translated' => implode(' ', $results)]);
