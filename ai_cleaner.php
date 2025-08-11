<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php'; // expects $CLEANER_API_KEY


// --- after: require_once __DIR__ . '/config.php';
// Trim & basic validation of key to catch empty/rotated keys early
$CLEANER_API_KEY = isset($CLEANER_API_KEY) ? trim($CLEANER_API_KEY) : '';
if ($CLEANER_API_KEY === '' || !preg_match('/^sk-or-.*$/', $CLEANER_API_KEY)) {
    $request_id = $request_id ?? bin2hex(random_bytes(6));
    $err = [
        'error' => 'OpenRouter API key missing or malformed',
        'hint'  => 'Check config.php for $CLEANER_API_KEY (should start with "sk-or-").',
        'request_id' => $request_id,
    ];
    log_ai_cleaner([
        'request_id' => $request_id,
        'stage' => 'preflight_api_key',
        'result' => $err,
    ]);
    echo json_encode($err);
    exit;
}


// -------------------------------
// Logging helpers
// -------------------------------
function ensure_log_dir(string $dir): void {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Block web access to logs directory (Apache)
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
}

function log_ai_cleaner(array $entry): void {
    $dir = __DIR__ . '/logs';
    ensure_log_dir($dir);
    $entry['ts'] = date('c');
    // Never log a real bearer token if it sneaks in
    if (isset($entry['request']['headers']['Authorization'])) {
        $entry['request']['headers']['Authorization'] = 'Bearer ***REDACTED***';
    }
    // Truncate large blobs
    foreach (['raw_response','input_text'] as $k) {
        if (isset($entry[$k]) && is_string($entry[$k]) && strlen($entry[$k]) > 20000) {
            $entry[$k] = substr($entry[$k], 0, 20000) . "...[truncated]";
        }
    }
    @file_put_contents($dir . '/ai_cleaner.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

// -------------------------------
// Read input
// -------------------------------
$body = file_get_contents('php://input');
$input = json_decode($body, true);
$text = $input['text'] ?? '';

$request_id = bin2hex(random_bytes(6)); // short unique id

if (!$text || !is_string($text)) {
    $err = ['error' => 'No text provided.', 'request_id' => $request_id];
    log_ai_cleaner([
        'request_id' => $request_id,
        'stage' => 'validate_input',
        'input_text' => is_string($text) ? $text : '[non-string]',
        'json_error' => json_last_error() ? json_last_error_msg() : null,
        'result' => $err,
    ]);
    echo json_encode($err);
    exit;
}

// -------------------------------
// OpenRouter setup
// -------------------------------
$model = 'tngtech/deepseek-r1t2-chimera:free'; // free-tier friendly
$endpoint = 'https://openrouter.ai/api/v1/chat/completions';

$data = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'You are an AI that cleans up OCR-scanned text. Fix spacing, remove hyphen breaks, correct typos and punctuation.'],
        ['role' => 'user', 'content' => $text]
    ],
    'temperature' => 0.3,
];


$headers = [
    "Authorization: Bearer {$CLEANER_API_KEY}",
    "Content-Type: application/json",
    "Referer: " . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    "X-Title: ai_cleaner.php",
];



// -------------------------------
// cURL call (with header capture)
// -------------------------------
$responseHeaders = [];
$headerFn = function($ch, $header) use (&$responseHeaders) {
    $len = strlen($header);
    $parts = explode(':', $header, 2);
    if (count($parts) == 2) {
        $name = strtolower(trim($parts[0]));
        $value = trim($parts[1]);
        $responseHeaders[$name][] = $value;
    }
    return $len;
};

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER      => $headers,
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => json_encode($data),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_HEADERFUNCTION  => $headerFn,
    CURLOPT_TIMEOUT         => 30,    // total timeout
    CURLOPT_CONNECTTIMEOUT  => 10,    // connect timeout
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// -------------------------------
// Helpers to read rate-limit headers
// -------------------------------
$rate_limit = [
    'remaining' => $responseHeaders['x-ratelimit-remaining'][0] ?? null,
    'reset'     => $responseHeaders['x-ratelimit-reset'][0] ?? null,
    'limit'     => $responseHeaders['x-ratelimit-limit'][0] ?? null,
];

// -------------------------------
// Handle transport errors
// -------------------------------
if ($response === false) {
    $err = [
        'error' => 'Network/transport error',
        'curl_error' => $curlError,
        'http_code' => $httpCode,
        'request_id' => $request_id,
        'rate_limit' => $rate_limit,
    ];
    log_ai_cleaner([
        'request_id' => $request_id,
        'stage' => 'curl_exec',
        'model' => $model,
        'input_len' => strlen($text),
        'request' => [
            'endpoint' => $endpoint,
            'headers'  => ['Authorization' => $headers[0], 'Content-Type' => 'application/json'],
            'body_len' => strlen(json_encode($data)),
        ],
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response_headers' => $responseHeaders,
        'raw_response' => null,
        'result' => $err,
    ]);
    echo json_encode($err);
    exit;
}

// -------------------------------
// Decode JSON (even for non-2xx to extract provider error)
// -------------------------------
$decoded = json_decode($response, true);
$jsonErr = json_last_error() ? json_last_error_msg() : null;
$providerError = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : null;

// -------------------------------
// Make error reason friendly
// -------------------------------

function friendly_reason(int $httpCode, ?array $rate_limit, $providerError): string {
    if ($httpCode === 401 || $httpCode === 403) {
        $pm = '';
        if (is_array($providerError)) $pm = $providerError['message'] ?? '';
        elseif (is_string($providerError)) $pm = $providerError;

        if (stripos($pm, 'user not found') !== false || stripos($pm, 'invalid') !== false) {
            return 'OpenRouter API key invalid or not recognized';
        }
        return 'Authentication/authorization failed';
    }
    if ($httpCode === 429) {
        $remaining = $rate_limit['remaining'] ?? null;
        $remaining = is_array($remaining) ? ($remaining[0] ?? null) : $remaining;
        $pm = is_array($providerError) ? ($providerError['message'] ?? '') : (is_string($providerError) ? $providerError : '');
        if ((string)$remaining === '0' || stripos($pm, 'quota') !== false || stripos($pm, 'rate limit') !== false) {
            return 'Daily quota used up';
        }
        return 'Too many requests';
    }
    if ($httpCode >= 500) return 'Upstream service temporarily unavailable';
    if ($httpCode === 400) return 'Bad request to provider';
    if ($httpCode === 408) return 'Upstream timeout';
    if ($httpCode === 413) return 'Input too large';
    if ($httpCode === 415) return 'Unsupported media type';
    if ($httpCode === 422) return 'Unprocessable input';
    return 'AI cleaner request failed';
}


// -------------------------------
// Non-2xx or JSON error → report verbosely
// -------------------------------
if ($httpCode < 200 || $httpCode >= 300 || $jsonErr !== null) {
    $friendly = friendly_reason($httpCode, $rate_limit, $providerError);

    $err = [
        'error' => $friendly,
        'http_code' => $httpCode,
        'json_error' => $jsonErr,
        'provider_error' => $providerError,
        'rate_limit' => $rate_limit,
        'request_id' => $request_id,
    ];

    log_ai_cleaner([
        'request_id' => $request_id,
        'stage' => 'http_or_json_error',
        'model' => $model,
        'input_len' => strlen($text),
        'request' => [
            'endpoint' => $endpoint,
            'headers'  => ['Authorization' => $headers[0], 'Content-Type' => 'application/json'],
            'body'     => $data,
        ],
        'http_code' => $httpCode,
        'response_headers' => $responseHeaders,
        'raw_response' => $response,
        'json_error' => $jsonErr,
        'result' => $err,
    ]);

    echo json_encode($err);
    exit;
}

// -------------------------------
// Success path
// -------------------------------
$cleaned = $decoded['choices'][0]['message']['content'] ?? null;

if (is_string($cleaned) && $cleaned !== '') {
    $out = [
        'cleaned' => $cleaned,
        'request_id' => $request_id,
        'rate_limit' => $rate_limit,
    ];

    log_ai_cleaner([
        'request_id' => $request_id,
        'stage' => 'success',
        'model' => $model,
        'input_len' => strlen($text),
        'http_code' => $httpCode,
        'response_headers' => $responseHeaders,
        'result' => ['ok' => true, 'cleaned_len' => strlen($cleaned)],
    ]);

    echo json_encode($out);
    exit;
}

// -------------------------------
// No cleaned content returned
// -------------------------------
$err = [
    'error' => 'No cleaned result returned.',
    'http_code' => $httpCode,
    'raw_response' => $response,
    'request_id' => $request_id,
    'rate_limit' => $rate_limit,
];

log_ai_cleaner([
    'request_id' => $request_id,
    'stage' => 'no_cleaned_content',
    'model' => $model,
    'input_len' => strlen($text),
    'http_code' => $httpCode,
    'response_headers' => $responseHeaders,
    'raw_response' => $response,
    'result' => $err,
]);

echo json_encode($err);
