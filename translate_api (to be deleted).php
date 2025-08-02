<?php
// translate_api.php
header('Content-Type: application/json; charset=utf-8');

// Validate input
$text = $_POST['text'] ?? '';
$sourceLang = $_POST['source'] ?? '';
$targetLang = $_POST['target'] ?? 'cs';

if (!$text) {
    echo json_encode(['error' => 'Missing text']);
    exit;
}

// Ensure mbstring functions work properly
mb_internal_encoding("UTF-8");

function translate_text($text, $source, $target) {
    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$source}|{$target}";
    $response = @file_get_contents($url);
    if (!$response) return '[Translation failed]';

    $data = json_decode($response, true);

    // Prefer the main responseData
    $best = $data['responseData']['translatedText'] ?? '';

    // Go through matches to find the highest score (if any)
    if (!empty($data['matches']) && is_array($data['matches'])) {
        $maxMatch = 0;
        $bestMatch = $best;

        foreach ($data['matches'] as $match) {
            $score = floatval($match['match'] ?? 0);
            if ($score > $maxMatch && !empty($match['translation'])) {
                $maxMatch = $score;
                $bestMatch = $match['translation'];
            }
        }

        // Use the best-scoring match only if it's strong enough (e.g. ≥ 0.70)
        if ($maxMatch >= 0.70) {
            return $bestMatch;
        }
    }

    return $best ?: '[Translation failed]';
}


// Clean and split text into sentences (mimics translator.php)
$mergedText = preg_replace("/\s+\n\s+|\n+/", ' ', $text);
$sentences = preg_split('/(?<=[.!?:])\s+(?=[A-Z\xC0-\xFF])/', $mergedText);
$sentences = array_filter(array_map('trim', $sentences));

// Translate each sentence
$translated = [];
foreach ($sentences as $line) {
    $translated[] = translate_text($line, $sourceLang, $targetLang);
    usleep(500000); // 500ms delay to avoid throttling
}

// Return full translation as string
echo json_encode(['translated' => implode(' ', $translated)], JSON_UNESCAPED_UNICODE);
