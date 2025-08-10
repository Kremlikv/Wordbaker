<?php
// or_check.php — minimal, noisy debug

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Start\n";

// --- Load config with your $OPENROUTER_API_KEY ---

$OPENROUTER_API_KEY = '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';
$OPENROUTER_MODEL = 'anthropic/claude-3-haiku';
$OPENROUTER_REFERER = 'https://kremlik.byethost15.com';
$APP_TITLE = 'KahootGenerator';

// require_once __DIR__ . '/config.php';
// echo "config loaded\n";

// --- Verify key presence ---
$key = isset($OPENROUTER_API_KEY) ? $OPENROUTER_API_KEY : getenv('OPENROUTER_API_KEY');
if (!$key) {
    die("ERROR: OPENROUTER API key not found. Define \$OPENROUTER_API_KEY in config.php or set env OPENROUTER_API_KEY.\n");
}
echo "Key length: ".strlen($key)." (won't print the key)\n";

// --- Helper for GET calls ---
function http_get($url, $key) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer ".$key],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$body, $err, $info];
}

// --- /api/v1/key ---
echo "\n--- GET /api/v1/key ---\n";
list($body, $err, $info) = http_get('https://openrouter.ai/api/v1/key', $key);
echo "HTTP code: ".($info['http_code'] ?? 'n/a')."\n";
if ($err) echo "cURL error: $err\n";
echo "Response:\n$body\n";

// --- /api/v1/credits ---
echo "\n--- GET /api/v1/credits ---\n";
list($body2, $err2, $info2) = http_get('https://openrouter.ai/api/v1/credits', $key);
echo "HTTP code: ".($info2['http_code'] ?? 'n/a')."\n";
if ($err2) echo "cURL error: $err2\n";
echo "Response:\n$body2\n";

echo "\nDone\n";
