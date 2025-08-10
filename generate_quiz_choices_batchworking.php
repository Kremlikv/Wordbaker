<?php
// generate_quiz_choices.php — batched (20 items/call) + raw AI candidates preview
// - Keeps usage banner, free-model guard, and throttling
// - Stores full candidate list per row in ai_candidates (read-only preview)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

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

// ====== Guard: if no credits and model is not free, show warning (we still render UI) ======
if ($credits_left <= 0 && strpos($OPENROUTER_MODEL, ':free') === false) {
    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>";
    echo "⚠️ You have 0 credits and selected a paid model (<code>".htmlspecialchars($OPENROUTER_MODEL)."</code>). Choose a :free model or top up credits.";
    echo "</div>";
}

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
    // Return: index => ['candidates' => [...]]
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
            $fields = $res->fetch_fields();
            $col1 = $fields[0]->name; // question (Czech)
            $col2 = $fields[1]->name; // correct answer (target)

            // Create with ai_candidates column
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

            // Guard: if no credits and not a free model, stop early (before any work)
            if ($credits_left <= 0 && strpos($OPENROUTER_MODEL, ':free') === false) {
                // nothing inserted; fall through to preview
            } else {
                // Ensure column exists even if table pre-existed somehow
                ensureAiCandidatesColumn($conn, $quizTable);

                // Load all rows first (so we can batch easily)
                $rows = [];
                $res2 = $conn->query("SELECT `$col1` AS q, `$col2` AS c FROM `$selectedTable`");
                while ($r = $res2->fetch_assoc()) {
                    $q = trim($r['q']); $c = trim($r['c']);
                    if ($q === '' || $c === '') continue;
                    $rows[] = ['question' => $q, 'correct' => $c];
                }

                // Process in chunks of up to 20 items
                $batchSize = 20;
                for ($offset = 0; $offset < count($rows); $offset += $batchSize) {
                    $chunk = array_slice($rows, $offset, $batchSize);

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

                    // Pause between batches to respect rate-limit info
                    usleep($per_call_ms * 1000);
                }
            }
        }
    }
    $generatedTable = $quizTable ?? '';
}

// ====== UI ======
echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices</h2>";
echo "<p style='text-align:center;'>This AI is designed for vocabulary, not sentences!</p>";
echo "<p style='text-align:center;'>Distractors suggested by AI require your manual review and editing. </p>";

// ====== Banner ======

echo "<div class='content'>";
$banner_parts = [];
$banner_parts[] = 'Model: <code>'.htmlspecialchars($OPENROUTER_MODEL).'</code>';
$banner_parts[] = 'Tier: '.($is_free_tier ? 'Free' : 'Paid');
$banner_parts[] = 'Rate limit: '.intval($rate_requests).' / '.intval($rate_interval_s).'s (~'.$per_call_ms.'ms/call)';
if ($credits_info !== null) {
    $banner_parts[] = 'Credits left: '.number_format($credits_left, 2).' (used: '.number_format($total_usage, 2).')';
}
echo "<div class='content' style='background:#f1f5f9; text-align:center;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;margin:10px 0;'>".implode(' · ', $banner_parts)."</div>";
echo "</div>";

include 'file_explorer.php';

if (!empty($generatedTable)) {
    echo "<h3 style='text-align:center;'>Preview: <code>".htmlspecialchars($generatedTable)."</code></h3>";
    echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
            <tr><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>AI candidates (raw)</th></tr>";
    $res = $conn->query("SELECT * FROM `$generatedTable` LIMIT 20");
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
    echo "</table></div><br>";
    echo "<div style='text-align:center;'>
            <a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit</a>
          </div>";
}
?>
