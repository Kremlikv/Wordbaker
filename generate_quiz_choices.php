<?php

// ===============================
// FILE: generate_quiz_questions.php
// Purpose: Generate quiz rows AND store a larger pool of distractors per row (JSON)
// Notes:
//  - Adds/uses a JSON column `wrong_candidates` in the quiz table
//  - Leaves wrong1..3 empty; you pick the final 3 in quiz_edit.php
//  - Uses a free model by default and throttles requests to respect rate limits
// ===============================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

// ====== CONFIG (override here if not set in config.php) ======
// $OPENROUTER_MODEL   = $OPENROUTER_MODEL   ?? 'deepseek/deepseek-chat-v3-0324:free'; // JSON-friendlier than R1
// $OPENROUTER_REFERER = $OPENROUTER_REFERER ?? (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '');
// $APP_TITLE          = $APP_TITLE          ?? 'KahootGenerator';
// $CAND_COUNT         = 18;  // how many candidates to request per row

// ====== Small HTTP helper ======
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

// ====== Fetch key info & credits (for banner + throttle) ======
$key_info = $credits_info = null; $rate_requests = 10; $rate_interval_s = 10; $is_free_tier = true; $credits_left = 0.0; $total_usage = 0.0;
try {
    list($body, $err, $info) = or_http_get('https://openrouter.ai/api/v1/key', $OPENROUTER_API_KEY);
    if (!$err && ($info['http_code'] ?? 0) === 200) {
        $key_info = json_decode($body, true)['data'] ?? null;
        if (!empty($key_info['rate_limit']['requests'])) $rate_requests = (int)$key_info['rate_limit']['requests'];
        if (!empty($key_info['rate_limit']['interval'])) $rate_interval_s = (int)preg_replace('/[^0-9]/', '', $key_info['rate_limit']['interval']);
        $is_free_tier = !!($key_info['is_free_tier'] ?? true);
    }
    list($body2, $err2, $info2) = or_http_get('https://openrouter.ai/api/v1/credits', $OPENROUTER_API_KEY);
    if (!$err2 && ($info2['http_code'] ?? 0) === 200) {
        $credits_info = json_decode($body2, true)['data'] ?? null;
        $total_credits = (float)($credits_info['total_credits'] ?? 0);
        $total_usage   = (float)($credits_info['total_usage'] ?? 0);
        $credits_left  = max(0.0, $total_credits - $total_usage);
    }
} catch (Throwable $e) { /* non-fatal */ }

$per_call_ms = (int)ceil(($rate_interval_s * 1000) / max(1, $rate_requests));
if ($per_call_ms < 900) $per_call_ms = 1000; // conservative 1 rps

// ====== DB helpers ======
$conn->set_charset('utf8mb4');
function quizTableExists($conn, $table) {
    $quizTable = (strpos($table, 'quiz_choices_') === 0) ? $table : "quiz_choices_" . $table;
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($quizTable) . "'");
    return $res && $res->num_rows > 0;
}

function ensureWrongCandidatesColumn($conn, $quizTable) {
    $res = $conn->query("SHOW COLUMNS FROM `$quizTable` LIKE 'wrong_candidates'");
    if (!$res || $res->num_rows === 0) {
        // Try JSON, fallback to TEXT if JSON not supported
        $ok = $conn->query("ALTER TABLE `$quizTable` ADD COLUMN wrong_candidates JSON NULL AFTER image_url");
        if (!$ok) {
            $conn->query("ALTER TABLE `$quizTable` ADD COLUMN wrong_candidates TEXT NULL AFTER image_url");
        }
    }
}

// ====== Content filters ======
function normalizeStr($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u', '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
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

// ====== AI: get MANY distractors in JSON ======
function genManyDistractors($apiKey, $model, $czech, $correct, $targetLang, $referer, $appTitle, $n = 18) {
    $sys = 'Reply ONLY as {"distractors":["...","...",...]} (JSON). No explanations.';
    $user = "Czech word: \"$czech\"\nCorrect $targetLang translation: \"$correct\"\n\nReturn $n plausible wrong answers (no bullets, no numbering). Avoid the correct answer, trivial plural-only variants, nonsense, and symbols. Prefer article/gender confusion, false friends, similar spelling/sound, same category, diacritic confusion.";

    $payload = [
        'model' => $model,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => $user],
        ],
        'max_tokens' => 300,
        'temperature' => 0.4,
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "Authorization: Bearer $apiKey",
            "HTTP-Referer: $referer",
            "X-Title: $appTitle",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 25,
    ]);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if (($info['http_code'] ?? 0) !== 200) return [];

    $outer = json_decode($res, true);
    $content = $outer['choices'][0]['message']['content'] ?? '';
    $obj = json_decode($content, true);
    $arr = is_array($obj['distractors'] ?? null) ? $obj['distractors'] : [];

    // Clean, dedupe, filter out correct
    $seen = [];
    $out = [];
    foreach ($arr as $s) {
        $s = trim($s);
        if ($s === '' || mb_strlen($s,'UTF-8') > 50) continue;
        if (preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u', $s)) continue;
        if (isSameWord($s, $correct)) continue;
        $k = normalizeStr($s);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $s;
    }
    return $out; // may be < n if model misbehaves
}

// ====== PRE-EXPLORER LOGIC (mostly unchanged) ======
$username = strtolower($_SESSION['username'] ?? '');

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
        }
    }
    // Ensure wrong_candidates column exists
    ensureWrongCandidatesColumn($conn, $quizTable);

    // Fill rows (or append if already exists but empty)
    $res2 = $conn->query("SELECT * FROM `$selectedTable`");
    if ($res2 && $res2->num_rows > 0) {
        $col1 = $res2->fetch_fields()[0]->name;
        $col2 = $res2->fetch_fields()[1]->name;

        while ($row = $res2->fetch_assoc()) {
            $question = trim($row[$col1]);
            $correct  = trim($row[$col2]);
            if ($question === '' || $correct === '') continue;

           // Insert row first (empty wrongs), then update candidates
           $stmt = $conn->prepare(
                "INSERT INTO `$quizTable`
                (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $empty = '';
            $stmt->bind_param(
                'sssssss',
                $question,        // ?
                $correct,         // ?
                $empty,           // wrong1
                $empty,           // wrong2
                $empty,           // wrong3
                $autoSourceLang,  // ?
                $autoTargetLang   // ?
            );
            $stmt->execute();
            $stmt->close();

            usleep($per_call_ms * 1000); // throttle
        }
    }
    $generatedTable = $quizTable ?? '';
}

echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";
echo "<p style='text-align:center;'>Generates a candidate pool per row. Final wrong answers are chosen in the editor.</p>";

// Banner
$banner_parts = [];
$banner_parts[] = 'Model: <code>'.htmlspecialchars($OPENROUTER_MODEL).'</code>';
$banner_parts[] = 'Tier: '.($is_free_tier ? 'Free' : 'Paid');
$banner_parts[] = 'Rate limit: '.intval($rate_requests).' / '.intval($rate_interval_s).'s (~'.$per_call_ms.'ms/call)';
if ($credits_info !== null) { $banner_parts[] = 'Credits left: '.number_format($credits_left, 2).' (used: '.number_format($total_usage, 2).')'; }
echo "<div class='content' style='background:#f1f5f9;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;margin:10px 0;'>".implode(' · ', $banner_parts)."</div>";

include 'file_explorer.php';

if (!empty($generatedTable)) {
    echo "<h3 style='text-align:center;'>Preview (first 20): <code>".htmlspecialchars($generatedTable)."</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Pool size</th></tr>";
    $res = $conn->query("SELECT id,question,correct_answer,wrong_candidates FROM `$generatedTable` LIMIT 20");
    while ($row = $res->fetch_assoc()) {
        $pool = json_decode($row['wrong_candidates'] ?? '[]', true);
        $count = is_array($pool) ? count($pool) : 0;
        echo "<tr>
                <td>".htmlspecialchars($row['question'])."</td>
                <td>".htmlspecialchars($row['correct_answer'])."</td>
                <td>".intval($count)."</td>
              </tr>";
    }
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit & Pick Distractors</a>
          </div>";
}
?>
