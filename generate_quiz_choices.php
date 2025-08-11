<?php
// generate_quiz_choices.php — EDIT-FIRST workflow + batching (20 items/call) + raw AI candidates + daily free-request counter
// - Shows only Model + Daily usage (used / left)
// - Tracks daily usage per MODEL locally in MySQL (table api_daily_usage)
// - Respects OpenRouter short-window rate-limit for sleeps
// - NEW: Review/Edit table BEFORE calling AI; generates only from edited/kept rows.
// Worked with:    $OPENROUTER_MODEL = 'deepseek/deepseek-chat-v3-0324:free';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

$conn->set_charset('utf8mb4');

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

// ====== Fetch key info (for rate limit window & tier). Credits are irrelevant to banner now. ======
$key_info = null; $rate_requests = 10; $rate_interval_s = 10; $is_free_tier = true;
try {
    list($body, $err, $info) = or_http_get('https://openrouter.ai/api/v1/key', $OPENROUTER_API_KEY);
    if (!$err && ($info['http_code'] ?? 0) === 200) {
        $key_info = json_decode($body, true)['data'] ?? null;
        if (!empty($key_info['rate_limit']['requests'])) $rate_requests = (int)$key_info['rate_limit']['requests'];
        if (!empty($key_info['rate_limit']['interval'])) {
            $rate_interval_s = (int)preg_replace('/[^0-9]/', '', $key_info['rate_limit']['interval']); // e.g. "10s" -> 10
        }
        $is_free_tier = !!($key_info['is_free_tier'] ?? true);
    }
} catch (Throwable $e) { /* non-fatal */ }

// ====== Rate-limit derived delay (ms). E.g., 10 req / 10 s -> ~1000 ms per call ======
$per_call_ms = (int)ceil(($rate_interval_s * 1000) / max(1, $rate_requests));
if ($per_call_ms < 900) $per_call_ms = 1000; // conservative default

// ====== DAILY FREE COUNTER (local storage) ======
// Create a small table to track API calls per day per model.
$conn->query("CREATE TABLE IF NOT EXISTS api_daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usage_date DATE NOT NULL,
    model VARCHAR(128) NOT NULL,
    calls INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_date_model (usage_date, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function daily_limit_for_model($key_info, $model) {
    // Try to infer a daily limit from OpenRouter if it exposes a daily window; else fall back to 50.
    // Note: The /key endpoint typically returns short-window limits (e.g., 10 per 10s). Daily caps are policy, not always exposed.
    // So we provide a sane default and allow override via config: $DAILY_FREE_LIMIT
    global $DAILY_FREE_LIMIT;
    if (isset($DAILY_FREE_LIMIT) && is_numeric($DAILY_FREE_LIMIT) && $DAILY_FREE_LIMIT > 0) return (int)$DAILY_FREE_LIMIT;

    // Heuristic: if key_info->rate_limit->interval contains 'd', use requests from there.
    if (!empty($key_info['rate_limit']['interval']) && strpos($key_info['rate_limit']['interval'], 'd') !== false) {
        $req = (int)($key_info['rate_limit']['requests'] ?? 50);
        return max(1, $req);
    }

    // Default daily cap for free models
    return 50;
}

function daily_used_for_model($conn, $model) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT calls FROM api_daily_usage WHERE usage_date=? AND model=?");
    $stmt->bind_param('ss', $today, $model);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int)$row['calls'] : 0;
}

function daily_inc_for_model($conn, $model, $delta = 1) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO api_daily_usage (usage_date, model, calls) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE calls = calls + VALUES(calls)");
    $stmt->bind_param('ssi', $today, $model, $delta);
    $stmt->execute();
    $stmt->close();
}

// Convenience
$MODEL = $OPENROUTER_MODEL;
$DAILY_LIMIT = daily_limit_for_model($key_info, $MODEL);
$DAILY_USED  = daily_used_for_model($conn, $MODEL);
$DAILY_LEFT  = max(0, $DAILY_LIMIT - $DAILY_USED);

// ====== Helpers ======
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

function normalizeStr($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u', '', $s);
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
        if (preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u', $c)) continue;
        if (isSameWord($c, $correctAnswer)) continue;
        $n = normalizeStr($c);
        $dup = false; foreach ($out as $o) if (normalizeStr($o) === $n) { $dup = true; break; }
        if ($dup) continue;
        $out[] = $c;
        if (count($out) === 3) break;
    }
    return $out;
}

function ensureAiCandidatesColumn($conn, $quizTable) {
    $quizTableEsc = $conn->real_escape_string($quizTable);
    $dbNameRes = $conn->query('SELECT DATABASE() AS db');
    $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
    $dbName = $dbNameRow ? $dbNameRow['db'] : '';

    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$conn->real_escape_string($dbName)."' AND TABLE_NAME='".$quizTableEsc."' AND COLUMN_NAME='ai_candidates'";
    $chk = $conn->query($sql);
    $has = false; if ($chk && ($row = $chk->fetch_assoc())) { $has = ((int)$row['cnt'] > 0); }
    if (!$has) {
        $conn->query("ALTER TABLE `".$quizTableEsc."` ADD COLUMN ai_candidates TEXT");
    }
}

// ====== Batched OpenRouter call returning up to 12 candidates per item ======
function callOpenRouterBatch($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $items, $targetLang, $referer, $appTitle) {
    $systemMessage =
        "You are an expert language teacher preparing multiple-choice vocabulary quizzes. "
       ."Reply ONLY in strict JSON: {\"results\":[{\"index\":<int>,\"candidates\":[\"...\",...]}...]}. "
       ."Provide 8–12 PLAUSIBLE WRONG ANSWERS per item in priority order under \"candidates\". "
       ."Do NOT include the correct answer or trivial variants. No extra keys or text.";

    $listLines = [];
    foreach ($items as $it) {
        $i  = (int)$it['index'];
        $cz = $it['czech'];
        $co = $it['correct'];
        $listLines[] = "$i) Czech: \"$cz\" → Correct $targetLang: \"$co\"";
    }

    $userMessage = implode("\n", $listLines)."\n\nRules:\n"
       ."- Only JSON with key \"results\".\n"
       ."- For each item, return {\"index\":N, \"candidates\":[\"...\",...]} with 8–12 plausible distractors.\n"
       ."- Avoid including the correct answer or trivial inflections-only variants.\n"
       ."- Prefer realistic mistakes: article/gender mixups, false friends, similar spelling/sound, same category, diacritic confusion.";

    $payload = [
        'model' => $OPENROUTER_MODEL,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user',   'content' => $userMessage],
        ],
        'max_tokens'  => 2200,
        'temperature' => 0.4,
    ];

    $url = 'https://openrouter.ai/api/v1/chat/completions';

    for ($attempt = 0; $attempt < 3; $attempt++) {
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
            CURLOPT_TIMEOUT => 35,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch); $err  = curl_error($ch); $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno) { error_log("[OpenRouterBatch] cURL error $errno: $err"); break; }
        $code = (int)($info['http_code'] ?? 0);
        if ($code === 429) { usleep(800000); $payload['temperature'] = 0.2; continue; }
        if ($code >= 400) { error_log("[OpenRouterBatch] HTTP $code: $response"); break; }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $obj = json_decode($content, true);

        $out = [];
        if (is_array($obj) && isset($obj['results']) && is_array($obj['results'])) {
            foreach ($obj['results'] as $entry) {
                if (!isset($entry['index']) || !isset($entry['candidates']) || !is_array($entry['candidates'])) continue;
                $idx = (int)$entry['index'];
                $out[$idx] = [
                    'candidates' => array_values(array_filter(array_map('trim', $entry['candidates'])))
                ];
            }
        }

        if (!empty($out)) return $out;

        // Minimal salvage attempt if JSON fails completely
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if (!empty($lines)) {
            $maybe = [];
            foreach ($lines as $ln) {
                if (preg_match('/^\s*(\d+)\)\s*(.+)$/u', $ln, $m)) {
                    $idx = (int)$m[1];
                    $parts = preg_split('/[;,|]/u', $m[2]);
                    if ($parts && count($parts) >= 3) {
                        $maybe[$idx] = ['candidates' => array_map('trim', $parts)];
                    }
                }
            }
            if (!empty($maybe)) return $maybe;
        }

        $payload['temperature'] = 0.2;
    }

    return [];
}

// ====== PRE-EXPLORER LOGIC ======
$username = strtolower($_SESSION['username'] ?? '');

function getUserFoldersAndTables($conn, $username) {
    $allTables = [];
    $result = $conn->query('SHOW TABLES');
    if ($result) {
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

// Determine selected table (avoids undefined variable)
$selectedTable = $_POST['table'] ?? $_GET['table'] ?? '';
$selectedTable = is_string($selectedTable) ? trim($selectedTable) : '';

// Try to infer language labels (only when selected)
$autoSourceLang = '';
$autoTargetLang = '';
if ($selectedTable !== '') {
    $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
    if ($columnsRes && $columnsRes->num_rows >= 2) {
        $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
        $autoSourceLang = ucfirst($cols[0]['Field']);
        $autoTargetLang = ucfirst($cols[1]['Field']);
    }
}

// ====== UI Header ======
echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";
echo "<p style='text-align:center;'>This AI is designed for vocabulary, not sentences!</p>";
echo "<p style='text-align:center;'>Distractors suggested by AI require your manual review and editing.</p>";

// ====== Minimal Banner: Model + Daily usage ======
$banner_parts = [];
$banner_parts[] = 'Model: <code>'.htmlspecialchars($MODEL).'</code>';
$banner_parts[] = 'Daily: used '.intval($DAILY_USED).' / '.intval($DAILY_LIMIT).' (left '.intval(max(0,$DAILY_LIMIT-$DAILY_USED)).')';
// echo "<div class='content' style='background:#f1f5f9; text-align:center;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;margin:10px 0;'>".implode(' · ', $banner_parts)."</div>";

echo "<div style='max-width:800px;margin:10px auto;text-align:center;
background:#f1f5f9;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;'>"
     . implode(' · ', $banner_parts) .
     "</div>";



// ====== File Explorer (uses $folderData) ======
include 'file_explorer.php';

// ====== EDIT-FIRST WORKFLOW ======
$generatedTable = '';

if ($selectedTable !== '') {
    // Which stage: show edit table, or generate from edited rows?
    $stage = $_POST['stage'] ?? 'edit';

    if ($stage === 'generate' && !empty($_POST['items']) && is_array($_POST['items'])) {
        // --- Build the list of edited rows ---
        $editedRows = [];
        foreach ($_POST['items'] as $idx => $item) {
            $q   = trim($item['q'] ?? '');
            $c   = trim($item['c'] ?? '');
            $del = isset($item['del']) && $item['del'] === '1';
            if ($del || $q === '' || $c === '') continue;

            // Optional: lightweight guard against likely sentences (you can relax/tighten)
            $looksSentence = (mb_strlen($q, 'UTF-8') > 40 || mb_strlen($c, 'UTF-8') > 40
                              || preg_match('/[.!?]/u', $q) || preg_match('/[.!?]/u', $c));
            if ($looksSentence) continue;

            $editedRows[] = ['question' => $q, 'correct' => $c];
        }

        if (empty($editedRows)) {
            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>
                    Nothing to generate. Keep at least one short word/phrase (not a full sentence).
                  </div>";
            $stage = 'edit';
        } else {
            // --- Proceed with generation using the edited rows only ---
            $quizTable = 'quiz_choices_' . $selectedTable;

            if (!quizTableExists($conn, $selectedTable)) {
                // Create target table if missing
                $conn->query("CREATE TABLE `$quizTable` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    question TEXT,
                    correct_answer TEXT,
                    wrong1 TEXT,
                    wrong2 TEXT,
                    wrong3 TEXT,
                    source_lang VARCHAR(50),
                    target_lang VARCHAR(50),
                    image_url TEXT,
                    ai_candidates TEXT
                )");
            }
            ensureAiCandidatesColumn($conn, $quizTable);

            // Process edited rows in chunks of up to 20 items
            $batchSize = 20;
            for ($offset = 0; $offset < count($editedRows); $offset += $batchSize) {
                $DAILY_USED  = daily_used_for_model($conn, $MODEL);
                $DAILY_LEFT  = max(0, $DAILY_LIMIT - $DAILY_USED);
                if ($DAILY_LEFT <= 0 && $is_free_tier) {
                    echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>
                            Reached today’s free limit. Generated up to this point; you can resume tomorrow.
                          </div>";
                    break;
                }

                $chunk = array_slice($editedRows, $offset, $batchSize);

                // Prepare items with stable 1..N indexes for this chunk
                $items = [];
                foreach ($chunk as $i => $r) {
                    $items[] = [
                        'index'   => $i + 1, // per-chunk index
                        'czech'   => $r['question'],
                        'correct' => $r['correct'],
                    ];
                }

                // Single AI request for up to 20 word-pairs (returns up to 12 candidates each)
                $map = callOpenRouterBatch(
                    $OPENROUTER_API_KEY,
                    $OPENROUTER_MODEL,
                    $items,
                    $autoTargetLang,
                    $OPENROUTER_REFERER,
                    $APP_TITLE
                );

                // Count this call against the daily quota (consistent behavior)
                daily_inc_for_model($conn, $MODEL, 1);

                // Insert rows (validate + fallbacks), also store raw candidates
                $stmt = $conn->prepare("INSERT INTO `$quizTable`
                    (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang, ai_candidates)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($items as $it) {
                    $q  = $it['czech'];
                    $co = $it['correct'];

                    $cands = [];
                    if (isset($map[$it['index']]['candidates']) && is_array($map[$it['index']]['candidates'])) {
                        $cands = $map[$it['index']]['candidates'];
                    }

                    // Choose 3 validated distractors from candidates
                    $chosen = validateDistractors($q, $co, $cands);
                    if (count($chosen) < 3) {
                        $fallbacks = cleanAIOutput([$co.'x', strrev($co), 'wrong']);
                        foreach ($fallbacks as $f) {
                            if (count($chosen) < 3 && !isSameWord($f, $co)) $chosen[] = $f;
                        }
                        while (count($chosen) < 3) $chosen[] = '';
                    }
                    [$w1, $w2, $w3] = array_slice($chosen, 0, 3);

                    // Save the whole candidate list (pipe-separated) for display
                    $aiNote = '';
                    if (!empty($cands)) {
                        $safeCands = [];
                        foreach ($cands as $c) {
                            $c = trim($c);
                            if ($c === '') continue;
                            if (mb_strlen($c, 'UTF-8') > 60) continue;
                            if (preg_match('/[\r\n]/u', $c)) continue;
                            $safeCands[] = $c;
                        }
                        $aiNote = implode(' | ', $safeCands);
                    }

                    $stmt->bind_param('ssssssss', $q, $co, $w1, $w2, $w3, $autoSourceLang, $autoTargetLang, $aiNote);
                    $stmt->execute();
                }

                $stmt->close();

                // Pause between batches to respect short-window rate-limit info
                usleep($per_call_ms * 1000);
            }

            $generatedTable = $quizTable;
        }
    }

    if ($stage === 'edit') {
        // --- Show editable table taken from the selected source table ---
        $rows = [];
        $col1 = $col2 = '';
        $columnsRes = $conn->query("SHOW COLUMNS FROM `$selectedTable`");
        if ($columnsRes && $columnsRes->num_rows >= 2) {
            $cols = $columnsRes->fetch_all(MYSQLI_ASSOC);
            $col1 = $cols[0]['Field']; // Czech / question
            $col2 = $cols[1]['Field']; // target / correct
            $res2 = $conn->query("SELECT `$col1` AS q, `$col2` AS c FROM `$selectedTable`");
            while ($r = $res2->fetch_assoc()) {
                $q = trim($r['q']); $c = trim($r['c']);
                if ($q === '' || $c === '') continue;
                $rows[] = ['q' => $q, 'c' => $c];
            }
        }

        echo "<h3 style='text-align:center;'>Review & Edit: <code>".htmlspecialchars($selectedTable)."</code></h3>";
        echo "<form method='post' style='margin:10px 0;'>";
        echo "<input type='hidden' name='table' value='".htmlspecialchars($selectedTable, ENT_QUOTES)."'>";
        echo "<input type='hidden' name='stage' value='generate'>";

        // small helper controls
        echo "<div class='content' style='display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center;margin-bottom:8px;'>
                <button type='button' id='markSentences' style='padding:6px 10px;'>Mark suspected sentences</button>
                <button type='button' id='deleteMarked'  style='padding:6px 10px;'>Delete marked</button>
              </div>";

        echo "<div style='overflow-x:auto;'>";
        echo "<table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>";
        $ansHeader = htmlspecialchars($autoTargetLang ?: 'Answer');
        echo "<tr><th>Delete</th><th>Czech (question)</th><th>{$ansHeader}</th></tr>";

        foreach ($rows as $i => $r) {
            $q = htmlspecialchars($r['q'], ENT_QUOTES);
            $c = htmlspecialchars($r['c'], ENT_QUOTES);
            echo "<tr>
                    <td style='text-align:center;'>
                        <input type='checkbox' name='items[$i][del]' value='1'>
                    </td>
                    <td>
                        <input type='text' name='items[$i][q]' value='$q' style='width:100%; padding:6px;'>
                    </td>
                    <td>
                        <input type='text' name='items[$i][c]' value='$c' style='width:100%; padding:6px;'>
                    </td>
                  </tr>";
        }
        echo "</table></div>";

        echo "<div style='text-align:center; margin:14px 0;'>
                <button type='submit' style='padding:10px 14px; background:#4CAF50; color:#fff; border:none; border-radius:6px;'>
                    ▶ Generate with AI (edited rows only)
                </button>
              </div>";

        echo "</form>";

        // Client-side helpers: mark likely sentences (length>40 or has .!?)
        echo "<script>
            (function(){
                const btnMark = document.getElementById('markSentences');
                const btnDelete = document.getElementById('deleteMarked');
                if (!btnMark) return;

                btnMark.onclick = function(){
                    const rows = document.querySelectorAll('table tr');
                    for (let r = 1; r < rows.length; r++) {
                        const inputs = rows[r].querySelectorAll('input[type=text]');
                        if (inputs.length !== 2) continue;
                        const q = inputs[0].value.trim();
                        const c = inputs[1].value.trim();
                        const looksSentence = (q.length > 40 || c.length > 40 || /[.!?]/.test(q) || /[.!?]/.test(c));
                        if (looksSentence) {
                            const del = rows[r].querySelector('input[type=checkbox]');
                            if (del) del.checked = true;
                            rows[r].style.background = '#fff7ed'; // light highlight
                        }
                    }
                };
                btnDelete.onclick = function(){
                    const rows = document.querySelectorAll('table tr');
                    for (let r = rows.length - 1; r >= 1; r--) {
                        const del = rows[r].querySelector('input[type=checkbox]');
                        if (del && del.checked) rows[r].remove();
                    }
                };
            })();
        </script>";
    }
}

// ====== If we generated, show a small preview like before ======
if (!empty($generatedTable)) {
    echo "<h3 style='text-align:center;'>Preview: <code>".htmlspecialchars($generatedTable)."</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>AI candidates (raw)</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable` LIMIT 20");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "<tr>
                    <td>".htmlspecialchars($row['question'])."</td>
                    <td>".htmlspecialchars($row['correct_answer'])."</td>
                    <td>".htmlspecialchars($row['wrong1'])."</td>
                    <td>".htmlspecialchars($row['wrong2'])."</td>
                    <td>".htmlspecialchars($row['wrong3'])."</td>
                    <td style='max-width:520px; white-space:normal;'>".htmlspecialchars($row['ai_candidates'] ?? '')."</td>
                  </tr>";
        }
    }
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit</a>
          </div>";
}
?>
