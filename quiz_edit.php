<?php

// ===============================
// FILE: quiz_edit.php (updated with batch regenerate for empty pools)
// ===============================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'session.php';
include 'styling.php';
require_once __DIR__ . '/config.php';

$OPENROUTER_REFERER = $OPENROUTER_REFERER ?? (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '');
$APP_TITLE          = $APP_TITLE          ?? 'KahootGenerator';
$BATCH_SIZE         = 20;   // same as generator
$CAND_COUNT         = 18;
$MODEL_PREFERENCES  = [
  $OPENROUTER_MODEL ?? 'qwen/qwen-2-7b-instruct:free',
  'mistralai/mistral-7b-instruct-v0.3:free',
];

$conn->set_charset('utf8mb4');
$table = $_GET['table'] ?? $_POST['table'] ?? '';
if($table===''){ die('No table specified.'); }

// Helpers reused
function norm($s){ $s=trim(mb_strtolower($s,'UTF-8')); $s=preg_replace('/^[\-\d\.)\:\"\']+|[\s\"\']+$/u','',$s); $s=preg_replace('/\s+/u',' ',$s); return $s; }
function stripDia($s){ $tr=['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z','Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z']; return strtr($s,$tr);} 
function sameWord($a,$b){ $na=stripDia(norm($a)); $nb=stripDia(norm($b)); return $na===$nb || $na===strrev($nb); }

function genBatchDistractors($apiKey, $models, $items, $targetLang, $referer, $appTitle, $candCount){
  $list=[]; foreach($items as $it){ $list[]=['id'=>$it['id'],'czech'=>$it['q'],'correct'=>$it['c']]; }
  $sys='Return JSON ONLY as {"results":[{"id":<int>,"distractors":["...","...",...]}, ...]}.';
  $user = "For each item, create $candCount plausible WRONG answers for $targetLang. Avoid the correct answer, trivial plural-only variants, nonsense, symbols. Prefer article/gender confusion, false friends, similar spelling/sound, same category, diacritic confusion. Items: ".json_encode($list, JSON_UNESCAPED_UNICODE);
  $payload=['response_format'=>['type'=>'json_object'],'messages'=>[['role'=>'system','content'=>$sys],['role'=>'user','content'=>$user]],'max_tokens'=>max(300,$candCount*items_count($items??[])*10),'temperature'=>0.4];
  foreach($models as $model){
    $payload['model']=$model;
    $ch=curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Authorization: Bearer $apiKey","HTTP-Referer: $referer","X-Title: $appTitle"],CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>30]);
    $res=curl_exec($ch); $info=curl_getinfo($ch); $err=curl_error($ch); curl_close($ch);
    $code=(int)($info['http_code']??0); if($err||$code==429){ continue; } if($code>=400){ continue; }
    $outer=json_decode($res,true); $content=$outer['choices'][0]['message']['content']??''; $obj=json_decode($content,true);
    if(!is_array($obj)||!isset($obj['results'])||!is_array($obj['results'])){ continue; }
    $map=[]; foreach($obj['results'] as $r){ $rid=$r['id']??null; $arr=is_array($r['distractors']??null)?$r['distractors']:[]; if($rid===null) continue; $seen=[]; $out=[]; $corr=''; foreach($items as $it){ if($it['id']==$rid){ $corr=$it['c']; break; } } foreach($arr as $s){ $s=trim((string)$s); if($s===''||mb_strlen($s,'UTF-8')>50) continue; if(preg_match('/[^a-zA-Zá-žÁ-Ž0-9\s\-]/u',$s)) continue; if(sameWord($s,$corr)) continue; $k=norm($s); if(isset($seen[$k])) continue; $seen[$k]=true; $out[]=$s; } $map[$rid]=$out; }
    return $map;
  }
  return [];
}
function items_count($a){ return is_array($a)?count($a):0; }

// Save picks
if(isset($_POST['save']) && isset($_POST['id'])){
  foreach($_POST['id'] as $i=>$id){ $id=(int)$id; $w1=trim($_POST['w1'][$i]??''); $w2=trim($_POST['w2'][$i]??''); $w3=trim($_POST['w3'][$i]??''); $u=$conn->prepare("UPDATE `$table` SET wrong1=?, wrong2=?, wrong3=? WHERE id=?"); $u->bind_param('sssi',$w1,$w2,$w3,$id); $u->execute(); $u->close(); }
  echo "<div class='content' style='background:#ecfeff;border:1px solid #a5f3fc;padding:8px;border-radius:8px;margin:10px 0;'>✅ Saved.</div>";
}

// Batch regenerate for empty pools
if(isset($_POST['regen_empty'])){
  $rows=$conn->query("SELECT id,question,correct_answer,target_lang,wrong_candidates FROM `$table` ORDER BY id ASC");
  $todo=[]; while($r=$rows->fetch_assoc()){ $list=json_decode($r['wrong_candidates']??'[]',true); if(!is_array($list)||count($list)==0){ $todo[]=['id'=>(int)$r['id'],'q'=>$r['question'],'c'=>$r['correct_answer'],'t'=>$r['target_lang']?:'Target']; } }
  // chunk into batches
  for($o=0;$o<count($todo);$o+=$BATCH_SIZE){ $chunk=array_slice($todo,$o,$BATCH_SIZE); $tlang = $chunk[0]['t'] ?? 'Target'; $map=genBatchDistractors($OPENROUTER_API_KEY,$MODEL_PREFERENCES,$chunk,$tlang,$OPENROUTER_REFERER,$APP_TITLE,$CAND_COUNT); foreach($chunk as $it){ $rid=$it['id']; $cands=$map[$rid]??[]; $json=json_encode($cands, JSON_UNESCAPED_UNICODE); $u=$conn->prepare("UPDATE `$table` SET wrong_candidates=? WHERE id=?"); $u->bind_param('si',$json,$rid); $u->execute(); $u->close(); } }
  echo "<div class='content' style='background:#fef9c3;border:1px solid #fde68a;padding:8px;border-radius:8px;margin:10px 0;'>♻ Regenerated empty pools in batches.</div>";
}

echo "<div class='content'>👤 Logged in as ".htmlspecialchars($_SESSION['username'] ?? '')." | <a href='logout.php'>Logout</a></div>";
echo "<h2 style='text-align:center;'>Edit Quiz — Pick 3 Distractors</h2>";

echo "<form method='post' style='margin:8px 0;'>";
echo "<input type='hidden' name='table' value='".htmlspecialchars($table)."'>";
echo "<button name='regen_empty' value='1' style='padding:6px 10px;margin-right:8px;'>♻ Regenerate empty pools (batched)</button>";
echo "</form>";

$res=$conn->query("SELECT id,question,correct_answer,wrong1,wrong2,wrong3,wrong_candidates FROM `$table` ORDER BY id ASC");

echo "<form method='post'>";
echo "<input type='hidden' name='table' value='".htmlspecialchars($table)."'>";
echo "<div style='overflow-x:auto;'><table border='1' style='width:100%; border-collapse:collapse;'>";
echo "<tr><th>ID</th><th>Czech</th><th>Correct</th><th>Wrong 1</th><th>Wrong 2</th><th>Wrong 3</th><th>Pool</th></tr>";

function renderDD($name,$current,$cands){ $html="<select name='".htmlspecialchars($name)."' style='max-width:220px;'>"; $used=[]; foreach($cands as $c){ $v=htmlspecialchars($c); if(isset($used[$v]))continue; $used[$v]=true; $sel=(mb_strtolower($c,'UTF-8')===mb_strtolower($current,'UTF-8'))?" selected":""; $html.="<option value='$v'$sel>$v</option>"; } $html.="<option value=''".($current===''?" selected":"").">(empty)</option></select>"; return $html; }

while($row=$res->fetch_assoc()){
  $id=(int)$row['id']; $cands=json_decode($row['wrong_candidates']??'[]',true); if(!is_array($cands)) $cands=[];
  echo "<tr>";
  echo "<td>".$id."<input type='hidden' name='id[]' value='".$id."'></td>";
  echo "<td>".htmlspecialchars($row['question'])."</td>";
  echo "<td>".htmlspecialchars($row['correct_answer'])."</td>";
  echo "<td>".renderDD("w1[]",$row['wrong1'],$cands)."</td>";
  echo "<td>".renderDD("w2[]",$row['wrong2'],$cands)."</td>";
  echo "<td>".renderDD("w3[]",$row['wrong3'],$cands)."</td>";
  echo "<td>".(is_array($cands)?count($cands):0)."</td>";
  echo "</tr>";
}

echo "</table></div><br>";
echo "<button name='save' value='1' style='padding:8px 14px;background:#4CAF50;color:#fff;border:none;border-radius:6px;'>💾 Save Changes</button>";
echo "</form>";
?>
