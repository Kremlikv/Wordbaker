<?php

// ===============================
// FILE: generate_quiz_questions.php (BATCHED VERSION)
// Purpose: Create quiz table and generate distractor POOLS in BATCHES (default 10 words per API call)
// ===============================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

// ====== CONFIG ======
$OPENROUTER_REFERER = $OPENROUTER_REFERER ?? (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '');
$APP_TITLE          = $APP_TITLE          ?? 'KahootGenerator';
$BATCH_SIZE         = 20;   // <- how many source rows per API call
$CAND_COUNT         = 18;   // how many candidates per word
// Prefer JSON-obedient free models; add fallbacks (will try in order)
$MODEL_PREFERENCES  = [
  $OPENROUTER_MODEL ??  'qwen/qwen-2-7b-instruct:free',
  'mistralai/mistral-7b-instruct-v0.3:free',
];

// ====== Simple GET to read key info for rate-limit banner ======
function or_http_get($url, $apiKey) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey"],
    CURLOPT_TIMEOUT => 15,
  ]);
  $body = curl_exec($ch); $err = curl_error($ch); $info = curl_getinfo($ch); curl_close($ch);
  return [$body, $err, $info];
}

$key_info=$credits_info=null; $rate_requests=10; $rate_interval_s=10; $is_free_tier=true; $credits_left=0.0; $total_usage=0.0;
try {
  list($b1,$e1,$i1)=or_http_get('https://openrouter.ai/api/v1/key',$OPENROUTER_API_KEY);
  if(!$e1 && ($i1['http_code']??0)==200){
    $d=json_decode($b1,true)['data']??null; $key_info=$d;
    $rate_requests=(int)($d['rate_limit']['requests']??10);
    $rate_interval_s=(int)preg_replace('/[^0-9]/','',$d['rate_limit']['interval']??'10');
    $is_free_tier = !!($d['is_free_tier']??true);
  }
  list($b2,$e2,$i2)=or_http_get('https://openrouter.ai/api/v1/credits',$OPENROUTER_API_KEY);
  if(!$e2 && ($i2['http_code']??0)==200){
    $c=json_decode($b2,true)['data']??null; $credits_info=$c;
    $total_usage=(float)($c['total_usage']??0); $tot=(float)($c['total_credits']??0); $credits_left=max(0.0,$tot-$total_usage);
  }
}catch(Throwable $e){/* ignore */}
$per_call_ms = (int)ceil(($rate_interval_s*1000)/max(1,$rate_requests)); if($per_call_ms<900)$per_call_ms=1000;

// ====== DB helpers ======
$conn->set_charset('utf8mb4');
function quizTableExists($conn, $table){ $qt=(strpos($table,'quiz_choices_')===0)?$table:("quiz_choices_".$table); $r=$conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($qt)."'"); return $r&&$r->num_rows>0; }
function ensureWrongCandidatesColumn($conn,$qt){ $r=$conn->query("SHOW COLUMNS FROM `$qt` LIKE 'wrong_candidates'"); if(!$r||$r->num_rows===0){ $ok=$conn->query("ALTER TABLE `$qt` ADD COLUMN wrong_candidates JSON NULL AFTER image_url"); if(!$ok){ $conn->query("ALTER TABLE `$qt` ADD COLUMN wrong_candidates TEXT NULL AFTER image_url"); } } }

// ====== Cleaning helpers ======
function norm($s){ $s=trim(mb_strtolower($s,'UTF-8')); $s=preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u','',$s); $s=preg_replace('/\s+/u',' ',$s); return $s; }
function stripDia($s){ $tr=['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z','Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z']; return strtr($s,$tr);} 
function sameWord($a,$b){ $na=stripDia(norm($a)); $nb=stripDia(norm($b)); return $na===$nb || $na===strrev($nb); }

// ====== BATCH CALL ======
function genBatchDistractors($apiKey, $models, $items, $targetLang, $referer, $appTitle, $candCount){
  // Build prompt for multiple items
  // items: array of [id, question, correct]
  $list=[]; foreach($items as $it){ $list[]=[ 'id'=>$it['id'], 'czech'=>$it['q'], 'correct'=>$it['c'] ]; }
  $sys = 'Return JSON ONLY with this shape: {"results":[{"id":<int>,"distractors":["...","...",...]}, ...]}. No explanations.';
  $user = "For each item, create $candCount plausible WRONG answers for the target language ($targetLang)."
        . "\nAvoid the correct answer, trivial plural-only variants, nonsense, or symbols. Prefer: article/gender confusion, false friends, similar spelling/sound, same category, diacritic confusion."
        . "\nItems: " . json_encode($list, JSON_UNESCAPED_UNICODE);

  $payload = [
    'response_format'=>['type'=>'json_object'],
    'messages'=>[
      ['role'=>'system','content'=>$sys],
      ['role'=>'user','content'=>$user],
    ],
    'max_tokens'=> max(300, $candCount*items_count($items??[])*10),
    'temperature'=>0.4,
  ];

  foreach($models as $model){
    $payload['model']=$model;
    $ch=curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>[
        'Content-Type: application/json',
        "Authorization: Bearer $apiKey",
        "HTTP-Referer: $referer",
        "X-Title: $appTitle",
      ],
      CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT=>30,
    ]);
    $res=curl_exec($ch); $info=curl_getinfo($ch); $err=curl_error($ch); curl_close($ch);
    $code=(int)($info['http_code']??0);
    if($err){ error_log("[OR] cURL error: $err"); continue; }
    if($code==429){ error_log("[OR] 429 on model $model — trying fallback if available"); continue; }
    if($code>=400){ error_log("[OR] HTTP $code on model $model: ".substr((string)$res,0,400)); continue; }
    $outer=json_decode($res,true); $content=$outer['choices'][0]['message']['content']??''; $obj=json_decode($content,true);
    if(!is_array($obj)||!isset($obj['results'])||!is_array($obj['results'])){ error_log('[OR] Bad JSON body: '.substr($content,0,400)); continue; }
    // Build mapping id => cleaned candidates
    $map=[]; foreach($obj['results'] as $r){
      $rid=$r['id']??null; $arr=is_array($r['distractors']??null)?$r['distractors']:[];
      if($rid===null) continue;
      $seen=[]; $out=[];
      // find corresponding correct for filtering
      $correct_by_id=''; foreach($items as $it){ if($it['id']==$rid){ $correct_by_id=$it['c']; break; } }
      foreach($arr as $s){
        $s=trim((string)$s);
        if($s===''||mb_strlen($s,'UTF-8')>50) continue;
        if(preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u',$s)) continue;
        if(sameWord($s,$correct_by_id)) continue;
        $k=norm($s); if(isset($seen[$k])) continue; $seen[$k]=true; $out[]=$s;
      }
      $map[$rid]=$out;
    }
    return $map; // success with this model
  }
  return []; // all models failed
}

// helper to count items (avoid undefined function on some hosts)
function items_count($a){ return is_array($a)?count($a):0; }

// ====== UI: Folder explorer unchanged-ish ======
$username = strtolower($_SESSION['username'] ?? '');
function getUserFoldersAndTables($conn,$username){ $all=[]; $res=$conn->query('SHOW TABLES'); while($row=$res->fetch_array()){ $t=$row[0]; if(strpos($t,'quiz_choices_')===0) continue; if(stripos($t,$username.'_')===0){ $s=substr($t,strlen($username)+1); $s=preg_replace('/_+/','_',$s); $p=explode('_',$s,2); if(count($p)===2&&trim($p[0])!==''){ $folder=$p[0]; $file=$p[1]; } else { $folder='Uncategorized'; $file=$s; } $all[$folder][]=['table_name'=>$t,'display_name'=>$file]; } } return $all; }

$folders=getUserFoldersAndTables($conn,$username);
$folders['Shared'][]=['table_name'=>'difficult_words','display_name'=>'Difficult Words'];
$folders['Shared'][]=['table_name'=>'mastered_words','display_name'=>'Mastered Words'];

$selectedTable = $_POST['table']
    ?? $_POST['selected_table']
    ?? $_GET['table']
    ?? $_GET['selected_table']
    ?? '';

$autoSourceLang=''; $autoTargetLang='';
if($selectedTable){ $cr=$conn->query("SHOW COLUMNS FROM `$selectedTable`"); if($cr&&$cr->num_rows>=2){ $cols=$cr->fetch_all(MYSQLI_ASSOC); $autoSourceLang=ucfirst($cols[0]['Field']); $autoTargetLang=ucfirst($cols[1]['Field']); } }

$generatedTable='';
if($selectedTable){
  $quizTable='quiz_choices_'.$selectedTable;
  if(!quizTableExists($conn,$selectedTable)){
    $rs=$conn->query("SELECT * FROM `$selectedTable`");
    if($rs&&$rs->num_rows>0){
      $c1=$rs->fetch_fields()[0]->name; $c2=$rs->fetch_fields()[1]->name;
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
  ensureWrongCandidatesColumn($conn,$quizTable);

  // === Insert all rows (if table newly created) OR append missing ===
  // For simplicity, check if table is empty; if so, insert all
  $countRes=$conn->query("SELECT COUNT(*) AS n FROM `$quizTable`"); $nRow=$countRes?($countRes->fetch_assoc()['n']??0):0;
  if((int)$nRow===0){
    $rs=$conn->query("SELECT * FROM `$selectedTable`");
    if($rs&&$rs->num_rows>0){
      $c1=$rs->fetch_fields()[0]->name; $c2=$rs->fetch_fields()[1]->name;
      while($row=$rs->fetch_assoc()){
        $question=trim($row[$c1]); $correct=trim($row[$c2]); if($question===''||$correct==='') continue;
        $stmt=$conn->prepare("INSERT INTO `$quizTable` (question, correct_answer, wrong1, wrong2, wrong3, source_lang, target_lang) VALUES (?,?,?,?,?,?,?)");
        $empty=''; $stmt->bind_param('sssssss',$question,$correct,$empty,$empty,$empty,$autoSourceLang,$autoTargetLang); $stmt->execute(); $stmt->close();
      }
    }
  }

  // === Fetch all rows and process in batches ===
  $all=$conn->query("SELECT id,question,correct_answer FROM `$quizTable` ORDER BY id ASC");
  $batch=[]; $i=0;
  while($r=$all->fetch_assoc()){
    $id=(int)$r['id']; $q=$r['question']; $c=$r['correct_answer'];
    // Skip if pool already exists and looks non-empty
    $chk=$conn->prepare("SELECT wrong_candidates FROM `$quizTable` WHERE id=?");
    $chk->bind_param('i',$id); $chk->execute(); $chk->bind_result($wc); $chk->fetch(); $chk->close();
    $existing = json_decode($wc?:'[]', true); if(is_array($existing) && count($existing)>0) continue;

    $batch[]=['id'=>$id,'q'=>$q,'c'=>$c]; $i++;
    if($i==$BATCH_SIZE){
      $map=genBatchDistractors($OPENROUTER_API_KEY,$MODEL_PREFERENCES,$batch,$autoTargetLang,$OPENROUTER_REFERER,$APP_TITLE,$CAND_COUNT);
      foreach($batch as $it){ $rid=$it['id']; $cands=$map[$rid]??[]; $json=json_encode($cands, JSON_UNESCAPED_UNICODE); $up=$conn->prepare("UPDATE `$quizTable` SET wrong_candidates=? WHERE id=?"); $up->bind_param('si',$json,$rid); if(!$up->execute()){ error_log('[DB] UPDATE failed id='.$rid.' err='.$conn->error); } $up->close(); }
      $batch=[]; $i=0; usleep($per_call_ms*1000);
    }
  }
  // tail batch
  if($i>0){
    $map=genBatchDistractors($OPENROUTER_API_KEY,$MODEL_PREFERENCES,$batch,$autoTargetLang,$OPENROUTER_REFERER,$APP_TITLE,$CAND_COUNT);
    foreach($batch as $it){ $rid=$it['id']; $cands=$map[$rid]??[]; $json=json_encode($cands, JSON_UNESCAPED_UNICODE); $up=$conn->prepare("UPDATE `$quizTable` SET wrong_candidates=? WHERE id=?"); $up->bind_param('si',$json,$rid); if(!$up->execute()){ error_log('[DB] UPDATE failed id='.$rid.' err='.$conn->error); } $up->close(); }
    $batch=[]; usleep($per_call_ms*1000);
  }

  $generatedTable=$quizTable;
}

echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Generate AI Quiz Choices (Batched)</h2>";
echo "<p style='text-align:center;'>Processes <b>$BATCH_SIZE</b> words per API call and stores <b>$CAND_COUNT</b> candidates per row.</p>";

$banner=[]; $banner[]='Rate limit: '.intval($rate_requests).' / '.intval($rate_interval_s).'s (~'.$per_call_ms.'ms/call)'; $banner[]='Tier: '.($is_free_tier?'Free':'Paid'); if($credits_info!==null){ $banner[]='Credits left: '.number_format($credits_left,2).' (used: '.number_format($total_usage,2).')'; }
echo "<div class='content' style='background:#f1f5f9;border:1px solid #e2e8f0;padding:10px 12px;border-radius:8px;margin:10px 0;'>".implode(' · ',$banner)."</div>";

// --- Simple fallback table picker ---
    echo "<form method='post' style='margin:10px 0; padding:8px; border:1px solid #e2e8f0; border-radius:8px;'>";
    echo "<label for='fallbackTable'><b>Select table (fallback):</b> </label>";
    echo "<select id='fallbackTable' name='table' required>";
    // Build options from the same $folders array you already computed
    foreach ($folders as $folder => $list) {
        foreach ($list as $entry) {
            $t = htmlspecialchars($entry['table_name']);
            $d = htmlspecialchars($entry['display_name']);
            echo "<option value='$t'".($selectedTable===$t?" selected":"").">[$folder] $d</option>";
        }
    }
    echo "</select> ";
    echo "<button type='submit'>Load</button>";
    echo "</form>";
// --- End fallback picker ---

include 'file_explorer.php';

if(!empty($generatedTable)){
  echo "<h3 style='text-align:center;'>Preview: <code>".htmlspecialchars($generatedTable)."</code></h3>";
  echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; max-width:100%; border-collapse:collapse;'>
          <tr><th>ID</th><th>Czech</th><th>Correct</th><th>Pool size</th></tr>";
  $res=$conn->query("SELECT id,question,correct_answer,wrong_candidates FROM `$generatedTable` LIMIT 50");
  while($row=$res->fetch_assoc()){
    $pool=json_decode($row['wrong_candidates']??'[]',true); $count=is_array($pool)?count($pool):0;
    echo "<tr><td>".(int)$row['id']."</td><td>".htmlspecialchars($row['question'])."</td><td>".htmlspecialchars($row['correct_answer'])."</td><td>".intval($count)."</td></tr>";
  }
  echo "</table></div><br>";
  echo "<div style='text-align:center;'><a href='quiz_edit.php?table=".urlencode($generatedTable)."' style='padding:10px; background:#4CAF50; color:#fff; text-decoration:none;'>✏ Edit & Pick Distractors</a></div>";
}
?>

