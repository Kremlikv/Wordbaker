<?php
header('Content-Type: application/json');

$text = $_POST['text'] ?? '';
$source = $_POST['source'] ?? 'auto';
$target = $_POST['target'] ?? 'cs';

if (!$text) {
    echo json_encode(['error' => 'Missing text']);
    exit;
}

function translate_line($line, $source, $target) {
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($line) . "&langpair={$source}|{$target}";
    $response = @file_get_contents($url);
    if (!$response) return '[Translation failed]';

    $data = json_decode($response, true);

    // Check for best match with a threshold
    if (!empty($data['matches'])) {
        foreach ($data['matches'] as $match) {
            if ($match['match'] >= 0.80 && !empty($match['translation'])) {
                return $match['translation'];
            }
        }
    }

    // Fallback to default
    return $data['responseData']['translatedText'] ?? '[Translation failed]';
}




// Clean and split the input text into lines/sentences
$merged = preg_replace('/\s*\n\s*/', ' ', $text);
$sentences = preg_split('/(?<=[.!?:])\s+(?=[A-Z\xC0-\xFF])/', $merged);
$sentences = array_filter(array_map('trim', $sentences));

// Translate each sentence
$translated = [];
foreach ($sentences as $s) {
    $translated[] = translate_line($s, $source, $target);
    usleep(500000); // wait 500ms between calls
}

echo json_encode(['translated' => implode(' ', $translated)]);
