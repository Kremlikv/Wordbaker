<?php
// generate_quiz_questions.php — patched version with usage banner, free-model guard, and throttling

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

// ====== CONFIG (override here if not set in config.php) ======
// $OPENROUTER_MODEL   = $OPENROUTER_MODEL   ?? 'deepseek/deepseek-r1:free';
// $OPENROUTER_REFERER = $OPENROUTER_REFERER ?? (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '');
// $APP_TITLE          = $APP_TITLE          ?? 'KahootGenerator';

// ====== SIMPLE HTTP GET helper for JSON endpoints ======
function or_http_get($url, $apiKey) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$body, $err, $info];
}

// ====== Fetch key info & credits (for banner + guards) ======
$key_info = $credits_info = null; $rate_requests = 10; $rate_interval_s = 10; $is_free_tier = true; $credits_left = 0.0; $total_usage = 0.0;
try {
    list($body, $err, $info) = or_http_get('https://openrouter.ai/api/v1/key', $OPENROUTER_API_KEY);
    if (!$err && ($info['http_code'] ?? 0) === 200) {
        $key_info = json_decode($body, true)['data'] ?? null;
        if (!empty($key_info['rate_limit']['requests'])) $rate_requests = (int)$key_info['rate_limit']['requests'];
        if (!empty($key_info['rate_limit']['interval'])) {
            // interval is like "10s"
            $rate_interval_s = (int)preg_replace('/[^0-9]/', '', $key_info['rate_limit']['interval']);
        }
        $is_free_tier = !!($key_info['is_free_tier'] ?? true);
    }

    list($body2, $err2, $info2) = or_http_get('https://openrouter.ai/api/v1/credits', $OPENROUTER_API_KEY);
    if (!$err2 && ($info2['http_code'] ?? 0) === 200) {
        $credits_info = json_decode($body2, true)['data'] ?? null;
        $total_credits = (float)($credits_info['total_credits'] ?? 0);
        $total_usage   = (float)($credits_info['total_usage'] ?? 0);
        $credits_left  = max(0.0, $total_credits - $total_usage);
    }
} catch (Throwable $e) {
    // Non-fatal — we continue without banner
}

// ====== Rate-limit derived delay (ms). E.g., 10 req / 10 s -> ~1000 ms per call ======
$per_call_ms = (int)ceil(($rate_interval_s * 1000) / max(1, $rate_requests));
if ($per_call_ms < 900) $per_call_ms = 1000; // be conservative

// ====== Guard: if no credits and model is not free, stop ======
if ($credits_left <= 0 && strpos($OPENROUTER_MODEL, ':free') === false) {
    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>";
    echo "⚠️ You have 0 credits and selected a paid model (<code>".htmlspecialchars($OPENROUTER_MODEL)."</code>). Choose a :free model or top up credits.";
    echo "</div>";
}


// ====== Your existing helpers ======
function quizTableExists($conn, $table) {
    $quizTable = (strpos($table, 'quiz_choices_') === 0) ? $table : "quiz_choices_" . $table;
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $res && $res->num_rows > 0;
}

function cleanAIOutput($answers) {
    return array_values(array_filter(array_map(function($a) {
        $clean = trim($a);
        $clean = preg_replace('/^[-\d\.)\:\"\']+/', '', $clean);
        if (strlen($clean) > 50 || preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u', $clean)) {
            return '';
        }
        return $clean;
    }, $answers)));
}


// ---- normalization + validation helpers ----
function normalizeStr($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u', '', $s); // strip bullets/quotes
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = str_replace(['–','—'], '-', $s);
    return $s;
}
function stripDiacritics($s) {
    $trans = [ 'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
               'Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z' ];
    return strtr($s, $trans);
}
function isSameWord($a, $b) {
    $na = stripDiacritics(normalizeStr($a));
    $nb = stripDiacritics(normalizeStr($b));
    return $na === $nb || $na === strrev($nb);
}
function validateDistractors($czechWord, $correctAnswer, $cands) {
    $out = [];
    foreach ($cands as $c) {
        $c = trim($c);
        if ($c === '') continue;
        if (mb_strlen($c, 'UTF-8') > 50) continue;
        // basic charset guard (letters, digits, space, hyphen)
        if (preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u', $c)) continue;
        if (isSameWord($c, $correctAnswer)) continue; // avoid identical or reversed
        $n = normalizeStr($c);
        $dup = false; foreach ($out as $o) if (normalizeStr($o) === $n) { $dup = true; break; }
        if ($dup) continue;
        $out[] = $c;
        if (count($out) === 3) break;
    }
    return $out;
}

//  

function callOpenRouter($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $czechWord, $correctAnswer, $targetLang, $referer, $appTitle) {
    // Ask for STRICT JSON so we can reliably parse/validate
    $systemMessage = "You are an expert language teacher preparing multiple-choice vocabulary quizzes. You must reply ONLY in JSON with the shape: {\"distractors\":[\"...\",\"...\",\"...\"]}. No explanations, no extra keys.";

    // one tiny few-shot to bias the format/content
    $fewShot = [
        [
            'role' => 'user',
            'content' => "Czech: 'stůl' → Correct German: 'Tisch' — give 3 plausible wrong answers (JSON as specified)."
        ],
        [
            'role' => 'assistant',
            'content' => json_encode(['distractors' => ['Stuhl','Tasche','Tische']], JSON_UNESCAPED_UNICODE)
        ]
    ];

    $userMessage = <<<USR
Czech word: "$czechWord"
Correct $targetLang translation: "$correctAnswer"

Rules:
- Only JSON: {"distractors":["...","...","..."]}
- No numbering or bullets
- Do NOT include the correct answer or trivial variants (plural-only)
- Prefer realistic student mistakes: article/gender mixups, false friends, similar spelling/sound, same category, diacritic confusion
USR;

    $payload = [
        'model' => $OPENROUTER_MODEL,              // e.g. deepseek/deepseek-r1:free
        'response_format' => ['type' => 'json_object'],
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemMessage]],
            $fewShot,
            [['role' => 'user', 'content' => $userMessage]]
        ),
        'max_tokens' => 120,
        'temperature' => 0.4,
    ];

    $url = 'https://openrouter.ai/api/v1/chat/completions';

    $attempts = 0;
    while ($attempts < 3) {
        $attempts++;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "Authorization: Bearer $OPENROUTER_API_KEY",
                "HTTP-Referer: $referer",
                "X-Title: $appTitle",
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch); $err  = curl_error($ch); $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno) { error_log("[OpenRouter] cURL error $errno: $err"); break; }
        $code = (int)($info['http_code'] ?? 0);
        if ($code === 429) { usleep(700000); continue; }
        if ($code >= 400) { error_log("[OpenRouter] HTTP $code: $response"); break; }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';

        // Expect strict JSON
        $obj = json_decode($content, true);
        if (is_array($obj) && isset($obj['distractors']) && is_array($obj['distractors'])) {
            $valid = validateDistractors($czechWord, $correctAnswer, $obj['distractors']);
            if (count($valid) === 3) return $valid;
        }

        // If model disobeyed JSON, try to salvage lines
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $salvaged = validateDistractors($czechWord, $correctAnswer, $lines);
        if (count($salvaged) === 3) return $salvaged;

        // tighten prompt on retry
        $payload['temperature'] = 0.2;
    }

    return [];
}


// ====== PRE-EXPLORER LOGIC (unchanged) ======
$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset('utf8mb4');

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_array()) {
        $table = $row[0];
        if (strpos($table, 'quiz_choices_') === 0) continue; // skip quiz tables
        if (stripos($table, $username . '_') === 0) {
            $suffix = substr($table, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts = explode('_', $suffix, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') { $folder = $parts[0]; $file = $parts[1]; }
            else { $folder = 'Uncategorized'; $file = $suffix; }
            $allTables[$folder][] = [ 'table_name' => $table, 'display_name' => $file ];
        }
    }
    return $allTables;
}

$folders = getUserFoldersAndTables($conn, $username);
$folders['Shared'][] = ['table_name' => 'difficult_words', 'display_name' => 'Difficult Words'];
$folders['Shared'][] = ['table_name' => 'mastered_words', 'display_name' => 'Mastered Words'];

$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [ 'table' => $entry['table_name'], 'display' => $entry['display_name'] ];
    }
}

$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$autoSourceLang = '';
$autoTargetLang = '';
if ($selectedTable) {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $autoSourceLang = ucfirst($cols[0]['Field']);
        $autoTargetLang = ucfirst($cols[1]['Field']);
    }
}

$generatedTable = '';
if ($selectedTable) {
    $quizTable = 'quiz_choices_' . $selectedTable;
    if (!quizTableExists($conn, $selectedTable)) {
        $res = $conn->query("SELECT * FROM `$selectedTable`");
        if ($res && $res->num_rows > 0) {
            $col1 = $res->fetch_fields()[0]->name;
            $col2 = $res->fetch_fields()[1]->name;
            $conn->query("CREATE TABLE `$quizTable` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question TEXT,
                correct_answer TEXT,
                wrong1 TEXT,
                wrong2 TEXT,
                wrong3 TEXT,
                source_lang VARCHAR(50),
                target_lang VARCHAR(50),
                image_url TEXT
            )");
            while ($row = $res->fetch_assoc()) {
                $question = trim($row[$col1]);
                $correct  = trim($row[$col2]);
                if ($question === '' || $correct === '') continue;

                // Guard: if no credits and not a free model, stop early
                if ($credits_left <= 0 && strpos($OPENROUTER_MODEL, ':free') === false) {
                    break;
                }

                $wrongAnswers = callOpenRouter($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $question, $correct, $autoTargetLang, $OPENROUTER_REFERER, $APP_TITLE);
                if (!$wrongAnswers || count($wrongAnswers) < 3) {
                    // Fallbacks (very conservative)
                    $wrongAnswers = array_values(array_filter(cleanAIOutput([
                        $correct.'x',
                        strrev($correct),
                        'wrong'
                    ])));
                }
                [$w1, $w2, $w3] = array_pad($wrongAnswers, 3, '');

                $stmt = $conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssss', $question, $correct, $w1, $w2, $w3, $autoSourceLang, $autoTargetLang);
                $stmt->execute();
                $stmt->close();

                // Throttle to respect rate-limit
                usleep($per_call_ms * 1000); // convert ms -> µs
            }
        }
    }
    $generatedTable = $quizTable ?? '';
}

echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";
echo "<p style='text-align:center;'>This AI is designed for vocabulary, not sentences!</p>";
echo "<p style='text-align:center;'>Distractors suggested by AI require your manual review and editing. </p>";

// ====== Banner ======
  
$banner_parts = [];
$banner_parts[] = 'Model: <code>'.htmlspecialchars($OPENROUTER_MODEL).'</code>';
$banner_parts[] = 'Tier: '.($is_free_tier ? 'Free' : 'Paid');
$banner_parts[] = 'Rate limit: '.intval($rate_requests).' / '.intval($rate_interval_s).'s (~'.$per_call_ms.'ms/call)';
if ($credits_info !== null) {
    $banner_parts[] = 'Credits left: '.number_format($credits_left, 2).' (used: '.number_format($total_usage, 2).')';
}
echo "<div class='content' style='background:#f1f5f9; text-align:center;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;margin:10px 0;'>".implode(' · ', $banner_parts)."</div>";


include 'file_explorer.php';

if (!empty($generatedTable)) {
    echo "<h3 style='text-align:center;'>Preview: <code>".htmlspecialchars($generatedTable)."</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable` LIMIT 20");
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
                <td>".htmlspecialchars($row['question'])."</td>
                <td>".htmlspecialchars($row['correct_answer'])."</td>
                <td>".htmlspecialchars($row['wrong1'])."</td>
                <td>".htmlspecialchars($row['wrong2'])."</td>
                <td>".htmlspecialchars($row['wrong3'])."</td>
              </tr>";
    }
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit</a>
          </div>";
}
?>
