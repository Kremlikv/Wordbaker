<?php

// ===============================
// FILE: quiz_edit.php
// Purpose: Edit UI with dropdowns populated from wrong_candidates; save picks; per-row regenerate button
// ===============================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

// $OPENROUTER_MODEL   = $OPENROUTER_MODEL   ?? 'deepseek/deepseek-chat-v3-0324:free';
// $OPENROUTER_REFERER = $OPENROUTER_REFERER ?? (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '');
// $APP_TITLE          = $APP_TITLE          ?? 'KahootGenerator';
// $CAND_COUNT         = 18;

$conn->set_charset('utf8mb4');
$table = $_GET['table'] ?? $_POST['table'] ?? '';
if ($table === '') { die('No table specified.'); }

// Helpers from generator (minimal duplication)
function normalizeStr($s) { $s = trim(mb_strtolower($s, 'UTF-8')); $s = preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u', '', $s); $s = preg_replace('/\s+/u', ' ', $s); return $s; }
function stripDiacritics($s) { $trans = [ 'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z','Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z' ]; return strtr($s, $trans); }
function isSameWord($a,$b){ $na=stripDiacritics(normalizeStr($a)); $nb=stripDiacritics(normalizeStr($b)); return $na===$nb || $na===strrev($nb);} 

function genManyDistractors($apiKey, $model, $czech, $correct, $targetLang, $referer, $appTitle, $n = 18) {
    $sys = 'Reply ONLY as {"distractors":["...","...",...]} (JSON). No explanations.';
    $user = "Czech word: \"$czech\"\nCorrect $targetLang translation: \"$correct\"\n\nReturn $n plausible wrong answers (no bullets, no numbering). Avoid the correct answer, trivial plural-only variants, nonsense, and symbols. Prefer article/gender confusion, false friends, similar spelling/sound, same category, diacritic confusion.";

    $payload = [
        'model' => $model,
        'response_format' => ['type' => 'json_object'],
        'messages' => [ ['role'=>'system','content'=>$sys], ['role'=>'user','content'=>$user] ],
        'max_tokens' => 300,
        'temperature' => 0.4,
    ];
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json', "Authorization: Bearer $apiKey", "HTTP-Referer: $referer", "X-Title: $appTitle" ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 25,
    ]);
    $res = curl_exec($ch); $info = curl_getinfo($ch); curl_close($ch);
    if (($info['http_code'] ?? 0) !== 200) return [];
    $outer = json_decode($res, true); $content = $outer['choices'][0]['message']['content'] ?? '';
    $obj = json_decode($content, true); $arr = is_array($obj['distractors'] ?? null) ? $obj['distractors'] : [];
    $seen=[]; $out=[]; foreach($arr as $s){ $s=trim($s); if($s===''||mb_strlen($s,'UTF-8')>50)continue; if(preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u',$s))continue; if(isSameWord($s,$correct))continue; $k=normalizeStr($s); if(isset($seen[$k]))continue; $seen[$k]=true; $out[]=$s; }
    return $out;
}

// Process saves
if (isset($_POST['save']) && isset($_POST['id'])) {
    foreach ($_POST['id'] as $i => $id) {
        $id = (int)$id;
        $w1 = trim($_POST['w1'][$i] ?? '');
        $w2 = trim($_POST['w2'][$i] ?? '');
        $w3 = trim($_POST['w3'][$i] ?? '');
        $stmt = $conn->prepare("UPDATE `$table` SET wrong1=?, wrong2=?, wrong3=? WHERE id=?");
        $stmt->bind_param('sssi', $w1, $w2, $w3, $id);
        $stmt->execute();
        $stmt->close();
    }
    echo "<div class='content' style='background:#ecfeff;border:1px solid #a5f3fc;padding:8px;border-radius:8px;margin:10px 0;'>✅ Saved.</div>";
}

// Per-row regeneration
if (isset($_POST['regen_id'])) {
    $rid = (int)$_POST['regen_id'];
    // Fetch the row to get question/correct/langs
    $res0 = $conn->prepare("SELECT question, correct_answer, target_lang FROM `$table` WHERE id=?");
    $res0->bind_param('i',$rid); $res0->execute(); $res0->bind_result($q,$corr,$tlang); $has=$res0->fetch(); $res0->close();
    if ($has) {
        $cands = genManyDistractors($OPENROUTER_API_KEY, $OPENROUTER_MODEL, $q, $corr, $tlang ?: 'Target', $OPENROUTER_REFERER, $APP_TITLE, $CAND_COUNT);
        $json = json_encode($cands, JSON_UNESCAPED_UNICODE);
        $u = $conn->prepare("UPDATE `$table` SET wrong_candidates=? WHERE id=?");
        $u->bind_param('si', $json, $rid); $u->execute(); $u->close();
        echo "<div class='content' style='background:#fef9c3;border:1px solid #fde68a;padding:8px;border-radius:8px;margin:10px 0;'>♻ Regenerated candidates for ID #".htmlspecialchars($rid).".</div>";
    }
}

echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Edit Quiz — Pick 3 Distractors</h2>";

// Fetch rows
$res = $conn->query("SELECT id,question,correct_answer,wrong1,wrong2,wrong3,wrong_candidates FROM `$table` ORDER BY id ASC");

echo "<form method='post' style='margin-bottom:12px;'>";
echo "<input type='hidden' name='table' value='".htmlspecialchars($table)."'>";
echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Pool</th><th>Actions</th></tr>";

function renderDropdown($name, $current, $cands) {
    $html = "<select name='".htmlspecialchars($name)."' style='max-width:220px;'>";
    $used = [];
    foreach ($cands as $c) {
        $v = htmlspecialchars($c);
        if (isset($used[$v])) continue; $used[$v] = true;
        $sel = (mb_strtolower($c,'UTF-8') === mb_strtolower($current,'UTF-8')) ? " selected" : "";
        $html .= "<option value='$v'$sel>$v</option>";
    }
    $html .= "<option value=''".($current===''?" selected":"").">(empty)</option>";
    $html .= "</select>";
    return $html;
}

while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $cands = json_decode($row['wrong_candidates'] ?? '[]', true);
    if (!is_array($cands)) $cands = [];

    echo "<tr>";
    echo "<td>".$id."<input type='hidden' name='id[]' value='".$id."'></td>";
    echo "<td>".htmlspecialchars($row['question'])."</td>";
    echo "<td>".htmlspecialchars($row['correct_answer'])."</td>";
    echo "<td>".renderDropdown("w1[]", $row['wrong1'], $cands)."</td>";
    echo "<td>".renderDropdown("w2[]", $row['wrong2'], $cands)."</td>";
    echo "<td>".renderDropdown("w3[]", $row['wrong3'], $cands)."</td>";
    echo "<td>".count($cands)."</td>";
    echo "<td>";
    echo "<form method='post' style='display:inline;'>";
    echo "<input type='hidden' name='table' value='".htmlspecialchars($table)."'>";
    echo "<input type='hidden' name='regen_id' value='".$id."'>";
    echo "<button type='submit' style='padding:4px 8px;'>♻ Regenerate</button>";
    echo "</form>";
    echo "</td>";
    echo "</tr>";
}

echo "</table></div><br>";
echo "<button name='save' value='1' style='padding:8px 14px;background:#4CAF50;color:#fff;border:none;border-radius:6px;'>💾 Save Changes</button>";
echo "</form>";
?>