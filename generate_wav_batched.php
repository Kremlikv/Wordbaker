<?php
/**
 * generate_wav_batched.php — Bilingual TTS (batched, WAV-precise) with FIXED VOICES + STYLE PRESETS
 *
 * - Voices: fixed Standard voices (Czech=female; Foreign=male) as provided by user
 * - Styles: user picks from 5 prosody presets per side (neutral, slow, fast, emphatic, calm)
 * - Google Cloud TTS v1beta1 + SSML <mark/> timepoints → LINEAR16 WAV
 * - Exact PCM slicing by timepoints; L1 → gap → L2 per word pair; gap between pairs
 * - Output: cache/<table>.wav; ?debug=1 streams trace; ?limit=NN limits rows; logs to log_batched.txt
 */

//////////////////// Debug + logging ////////////////////
$DEBUG = isset($_GET['debug']);
if ($DEBUG) { ini_set('display_errors', 1); error_reporting(E_ALL); }

function TRACE($msg) {
  @file_put_contents(__DIR__.'/log_batched.txt', '['.date('c')." ] mp2: $msg\n", FILE_APPEND);
}
function say($msg) {
  global $DEBUG; TRACE($msg);
  if ($DEBUG) { echo htmlspecialchars($msg)."<br>\n"; @ob_flush(); @flush(); }
}
function fail($msg, $httpCode = 500) {
  TRACE('FAIL: '.$msg);
  http_response_code($httpCode);
  echo '❌ '.htmlspecialchars($msg);
  exit;
}

//////////////////// Bootstrap ////////////////////
session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/config.php'; // defines $GOOGLE_API_KEY

if (!isset($GOOGLE_API_KEY) || !$GOOGLE_API_KEY) { $env = getenv('GOOGLE_API_KEY'); if ($env) $GOOGLE_API_KEY = $env; }
if (empty($GOOGLE_API_KEY)) { fail('Missing GOOGLE_API_KEY. Define $GOOGLE_API_KEY in config.php.'); }
if (!extension_loaded('curl')) { fail('PHP cURL extension is not enabled.'); }

//////////////////// Config ////////////////////
$SAMPLE_RATE_HZ  = 22050;     // WAV sample rate
$ITEM_BREAK_MS   = 300;       // pause between items inside the mono blobs
$PAIR_GAP_MS     = 400;       // gap between L1 and L2 within a pair, and between pairs
$TAIL_PAD_MS     = 120;       // small end pad for last slices
$MAX_SSML_BYTES  = 4600;      // keep below ~5000
$BATCH_MIN       = 20;        // min items per batch before splitting

// Fixed voices (as specified by you)
$FIXED_VOICES = [
  'czech'   => ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'], // FEMALE
  'english' => ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'], // MALE
  'german'  => ['name' => 'de-DE-Standard-H', 'code' => 'de-DE'], // MALE
  'french'  => ['name' => 'fr-FR-Standard-G', 'code' => 'fr-FR'], // MALE
  'italian' => ['name' => 'it-IT-Standard-F', 'code' => 'it-IT'], // MALE
  'spanish' => ['name' => 'es-ES-Standard-G', 'code' => 'es-ES'], // MALE
];

// Map table column headers → language codes
$LANG_KEYS = [
  'czech'   => 'cs-CZ',
  'english' => 'en-GB',
  'german'  => 'de-DE',
  'french'  => 'fr-FR',
  'italian' => 'it-IT',
  'spanish' => 'es-ES',
];

// Style presets (prosody only; safe with timepoints)
$STYLE_PRESETS = [
  'neutral'  => ['label'=>'Neutral',          'rate'=>'100%', 'pitch'=>'0st',  'volume'=>'0dB'],
  'slow'     => ['label'=>'Slow & Clear',     'rate'=>'85%',  'pitch'=>'-1st', 'volume'=>'+0dB'],
  'fast'     => ['label'=>'Fast & Natural',   'rate'=>'110%', 'pitch'=>'+0st', 'volume'=>'+0dB'],
  'emphatic' => ['label'=>'Emphatic',         'rate'=>'100%', 'pitch'=>'+2st', 'volume'=>'+2dB'],
  'calm'     => ['label'=>'Soft / Calm',      'rate'=>'92%',  'pitch'=>'-2st', 'volume'=>'-2dB'],
];

// Preview text (short)
$PREVIEW_TEXT = [
  'cs-CZ' => 'Ahoj! Toto je ukázkový hlas.',
  'de-DE' => 'Hallo! Das ist eine Stimmprobe.',
  'en-GB' => 'Hello! This is a voice sample.',
  'fr-FR' => 'Bonjour! Ceci est un échantillon de voix.',
  'it-IT' => 'Ciao! Questo è un campione di voce.',
  'es-ES' => '¡Hola! Esta es una muestra de voz.'
];

//////////////////// Session inputs ////////////////////
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';
if ($table==='' || $col1==='' || $col2==='') fail('Missing session info (table/col1/col2).');

$srcKey = strtolower($col1);
$tgtKey = strtolower($col2);
if (!isset($FIXED_VOICES[$srcKey])) fail("Unsupported source language: $col1");
if (!isset($FIXED_VOICES[$tgtKey])) fail("Unsupported target language: $col2");

$srcVoice = $FIXED_VOICES[$srcKey]; // fixed female for Czech
$tgtVoice = $FIXED_VOICES[$tgtKey]; // fixed male for foreign lang
$srcCode  = $srcVoice['code'];
$tgtCode  = $tgtVoice['code'];
$v1       = $srcVoice['name'];
$v2       = $tgtVoice['name'];

say("Start: table={$table}, col1={$col1} ({$srcCode}), col2={$col2} ({$tgtCode})");
say("Voices locked: L1={$v1}, L2={$v2}");

//////////////////// Helpers ////////////////////
function ssml_escape(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// v1beta1 synth (WAV) with optional timepoints
function tts_google_wav(string $apiKey, string $voiceName, string $langCode, string $ssml, int $sampleRate, bool $wantMarks=false): array {
  $payload = [
    'input'       => ['ssml' => $ssml],
    'voice'       => ['languageCode' => $langCode, 'name' => $voiceName],
    'audioConfig' => ['audioEncoding' => 'LINEAR16', 'sampleRateHertz' => $sampleRate]
  ];
  if ($wantMarks) $payload['enableTimePointing'] = ['SSML_MARK'];

  $ch = curl_init('https://texttospeech.googleapis.com/v1beta1/text:synthesize?key='.urlencode($apiKey));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 60
  ]);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false) fail('cURL error: '.$err);
  $j = json_decode($res, true);
  if ($code !== 200 || empty($j['audioContent'])) {
    @file_put_contents(__DIR__.'/log_batched.txt', '['.date('c')."] HTTP $code\nPayload: ".json_encode($payload)."\nResponse: $res\n\n", FILE_APPEND);
    fail('Google TTS failed (WAV). See log_batched.txt');
  }
  return [base64_decode($j['audioContent']), $j['timepoints'] ?? []];
}

// Minimal WAV decode/encode
function wav_decode(string $bytes): array {
  if (substr($bytes,0,4)!=="RIFF" || substr($bytes,8,4)!=="WAVE") throw new RuntimeException('Not a WAV file');
  $pos=12; $len=strlen($bytes); $fmt=null; $dataOff=null; $dataLen=null;
  while ($pos+8 <= $len) {
    $id=substr($bytes,$pos,4); $sz=unpack('V', substr($bytes,$pos+4,4))[1]; $pos+=8;
    if ($id==='fmt ')   $fmt = unpack('vAudioFormat/vNumChannels/VSampleRate/VByteRate/vBlockAlign/vBitsPerSample', substr($bytes,$pos,16));
    elseif ($id==='data'){ $dataOff=$pos; $dataLen=$sz; }
    $pos += $sz; if ($pos % 2) $pos++; // align
  }
  if (!$fmt || $dataOff===null) throw new RuntimeException('WAV missing fmt/data');
  return [$fmt, substr($bytes,$dataOff,$dataLen)];
}
function wav_encode(array $fmt, string $pcm): string {
  $channels=(int)$fmt['NumChannels']; $sr=(int)$fmt['SampleRate']; $bits=(int)$fmt['BitsPerSample'];
  $blockAlign=(int)max(1, ($channels*$bits)/8); $byteRate=(int)($sr*$blockAlign);
  $dataLen=strlen($pcm); $pad = ($dataLen % 2) ? "\x00" : "";
  $fmtBin=pack('vvVVvv', 1, $channels, $sr, $byteRate, $blockAlign, $bits);
  $chunks = "fmt ".pack('V',16).$fmtBin;
  $chunks.= "data".pack('V',$dataLen).$pcm.$pad;
  $riffSz = 4 + strlen($chunks);
  return "RIFF".pack('V',$riffSz)."WAVE".$chunks;
}

// Exact slicing by timepoints using true PCM end for last slice
function timepoints_to_pcm_slices(array $timepoints, int $sampleRate, int $blockAlign, float $totalSec, int $totalBytes, float $tailPadSec): array {
  $n = count($timepoints); $slices=[];
  for ($i=0; $i<$n; $i++) {
    $tStart = (float)$timepoints[$i]['timeSeconds'];
    $tEnd   = ($i+1 < $n) ? (float)$timepoints[$i+1]['timeSeconds'] : ($totalSec + $tailPadSec);
    $sStart = (int)floor($tStart*$sampleRate);
    $sEnd   = (int)ceil($tEnd*$sampleRate);
    $bStart = $sStart*$blockAlign;
    $bEnd   = min($totalBytes, $sEnd*$blockAlign);
    if ($bEnd < $bStart) $bEnd = $bStart;
    $slices[] = [$bStart, $bEnd];
  }
  return $slices;
}

// Batch builder (limit SSML size)
function build_batches(array $rows, int $maxSsmlBytes, int $minPerBatch, int $itemBreakMs): array {
  $batches=[]; $cur=[]; $curLen=strlen('<speak></speak>');
  foreach ($rows as $idx => $pair) {
    [$a,$b] = $pair;
    $piece  = strlen("<mark name='m$idx'/>".ssml_escape($a)."<break time='{$itemBreakMs}ms'/>");
    if (!empty($cur) && ($curLen+$piece) > $maxSsmlBytes && count($cur) >= $minPerBatch) {
      $batches[]=$cur; $cur=[]; $curLen=strlen('<speak></speak>');
    }
    $cur[]=$idx; $curLen+=$piece;
  }
  if (!empty($cur)) $batches[]=$cur;
  return $batches;
}

//////////////////// Preview endpoint (style-aware) ////////////////////
if (isset($_GET['preview']) && $_GET['preview']==='1') {
  $voice = trim($_GET['voice'] ?? '');
  $lang  = trim($_GET['lang'] ?? '');
  $styleKey = trim($_GET['style'] ?? 'neutral');

  global $PREVIEW_TEXT, $STYLE_PRESETS, $GOOGLE_API_KEY;
  if ($voice==='' || $lang==='' || empty($PREVIEW_TEXT[$lang])) { http_response_code(400); echo 'Missing voice/lang'; exit; }

  $st = $STYLE_PRESETS[$styleKey] ?? $STYLE_PRESETS['neutral'];
  $ssml = "<speak><prosody rate=\"{$st['rate']}\" pitch=\"{$st['pitch']}\" volume=\"{$st['volume']}\">"
        . ssml_escape($PREVIEW_TEXT[$lang])
        . "</prosody></speak>";

  $payload = [
    'input'       => ['ssml' => $ssml],
    'voice'       => ['languageCode' => $lang, 'name' => $voice],
    'audioConfig' => ['audioEncoding' => 'MP3', 'sampleRateHertz' => 22050]
  ];
  $ch = curl_init('https://texttospeech.googleapis.com/v1beta1/text:synthesize?key='.urlencode($GOOGLE_API_KEY));
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>json_encode($payload)]);
  $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($res===false || $code!==200) { http_response_code(502); echo 'Preview synth failed'; exit; }
  $j=json_decode($res,true); if (empty($j['audioContent'])) { http_response_code(502); echo 'No audio'; exit; }
  header('Content-Type: audio/mpeg'); header('Cache-Control: no-cache'); echo base64_decode($j['audioContent']); exit;
}

//////////////////// Style picker (GET) ////////////////////
function render_style_picker(string $table, string $srcKey, string $srcCode, string $tgtKey, string $tgtCode, string $v1, string $v2, array $STYLE_PRESETS) {
  $self = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Voice Styles</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:24px;}
    .card{max-width:760px;border:1px solid #e5e7eb;border-radius:12px;padding:18px;}
    label{display:block;margin:12px 0 6px;font-weight:600}
    select,button{font-size:14px;padding:8px;}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
    .muted{color:#64748b;font-size:12px;}
    audio{height:28px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px;}
    .box{border:1px dashed #e5e7eb;border-radius:10px;padding:12px;}
    code{background:#f1f5f9;padding:2px 6px;border-radius:6px;}
  </style>
  </head><body><div class='card'>";
  echo "<h2>Choose style presets</h2>";
  echo "<div class='muted'>Fixed voices:<br>• L1 <b>".htmlspecialchars($srcKey)." ($srcCode)</b> → <code>".htmlspecialchars($v1)."</code> (female)<br>• L2 <b>".htmlspecialchars($tgtKey)." ($tgtCode)</b> → <code>".htmlspecialchars($v2)."</code> (male)</div>";

  echo "<form method='post' action='' id='styleForm' style='margin-top:12px;'>";
  echo "<input type='hidden' name='confirmed' value='1'>";
  echo "<input type='hidden' name='voice1' value='".htmlspecialchars($v1,ENT_QUOTES)."'>";
  echo "<input type='hidden' name='voice2' value='".htmlspecialchars($v2,ENT_QUOTES)."'>";

  echo "<div class='grid'>";

  echo "<div class='box'>";
  echo "<label>Style for ".htmlspecialchars($srcKey)." ($srcCode)</label>";
  echo "<select name='style1' id='style1'>";
  foreach ($STYLE_PRESETS as $key=>$st) {
    echo "<option value='".htmlspecialchars($key,ENT_QUOTES)."'>".htmlspecialchars($st['label'])."</option>";
  }
  echo "</select>";
  $q1 = http_build_query(['preview'=>1,'voice'=>$v1,'lang'=>$srcCode,'style'=>'neutral']);
  echo "<div class='muted' style='margin-top:8px;'>Preview: <audio id='prev1' controls src='".$self."?".htmlspecialchars($q1,ENT_QUOTES)."'></audio></div>";
  echo "</div>";

  echo "<div class='box'>";
  echo "<label>Style for ".htmlspecialchars($tgtKey)." ($tgtCode)</label>";
  echo "<select name='style2' id='style2'>";
  foreach ($STYLE_PRESETS as $key=>$st) {
    echo "<option value='".htmlspecialchars($key,ENT_QUOTES)."'>".htmlspecialchars($st['label'])."</option>";
  }
  echo "</select>";
  $q2 = http_build_query(['preview'=>1,'voice'=>$v2,'lang'=>$tgtCode,'style'=>'neutral']);
  echo "<div class='muted' style='margin-top:8px;'>Preview: <audio id='prev2' controls src='".$self."?".htmlspecialchars($q2,ENT_QUOTES)."'></audio></div>";
  echo "</div>";

  echo "</div>";

  echo "<div class='row' style='margin-top:16px;'>
          <button type='submit'>🎧 Generate WAV</button>
          <a href='main.php?table=".urlencode($table)."' style='text-decoration:none;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;'>Cancel</a>
        </div>";
  echo "</form>";

  echo "<script>
  (function(){
    const self = ".json_encode($_SERVER['PHP_SELF']).";
    const s1 = document.getElementById('style1'), s2 = document.getElementById('style2');
    const a1 = document.getElementById('prev1'),  a2 = document.getElementById('prev2');
    function upd(audio, voice, lang, style){
      const q = new URLSearchParams({preview:'1', voice:voice, lang:lang, style:style}).toString();
      audio.src = self + '?' + q; audio.load();
    }
    s1.addEventListener('change', ()=>upd(a1,".json_encode($v1).",".json_encode($srcCode).",s1.value));
    s2.addEventListener('change', ()=>upd(a2,".json_encode($v2).",".json_encode($tgtCode).",s2.value));
  })();
  </script>";

  echo "</div></body></html>";
  exit;
}

//////////////////// If GET: show style picker ////////////////////
if (!isset($_POST['confirmed'])) {
  render_style_picker($table, $srcKey, $srcCode, $tgtKey, $tgtCode, $v1, $v2, $STYLE_PRESETS);
}

//////////////////// POST: proceed with synthesis ////////////////////
$selectedVoice1 = trim($_POST['voice1'] ?? '');
$selectedVoice2 = trim($_POST['voice2'] ?? '');
$styleKey1      = trim($_POST['style1'] ?? 'neutral');
$styleKey2      = trim($_POST['style2'] ?? 'neutral');
if ($selectedVoice1==='' || $selectedVoice2==='') fail('Voice selection missing.');

$st1 = $STYLE_PRESETS[$styleKey1] ?? $STYLE_PRESETS['neutral'];
$st2 = $STYLE_PRESETS[$styleKey2] ?? $STYLE_PRESETS['neutral'];
say("Chosen voices: L1={$selectedVoice1} | L2={$selectedVoice2} | styles: {$styleKey1} / {$styleKey2}");

//////////////////// Load data ////////////////////
$conn->set_charset('utf8mb4');
$tableEsc = str_replace('`','``',$table);
$col1Esc  = str_replace('`','``',$col1);
$col2Esc  = str_replace('`','``',$col2);
$sql = "SELECT `{$col1Esc}` AS c1, `{$col2Esc}` AS c2 FROM `{$tableEsc}`";
$result = $conn->query($sql);
if (!$result || $result->num_rows===0) fail('No data found in table.');
$rows=[];
while ($r=$result->fetch_assoc()) {
  $a=trim((string)$r['c1']); $b=trim((string)$r['c2']);
  if ($a!=='' && $b!=='') $rows[]=[$a,$b];
}
$result->free(); $conn->close();
if (empty($rows)) fail('No usable rows.');

say('Rows loaded: '.count($rows));
if (isset($_GET['limit'])) {
  $lim=max(1,(int)$_GET['limit']); $rows=array_slice($rows,0,$lim);
  say('LIMIT active: '.$lim.' rows');
}

//////////////////// Build batches ////////////////////
$batches = build_batches($rows, $MAX_SSML_BYTES, $BATCH_MIN, $ITEM_BREAK_MS);
say('Batches: '.count($batches).' (ITEM_BREAK_MS='.$ITEM_BREAK_MS.', MAX_SSML_BYTES='.$MAX_SSML_BYTES.')');
if (empty($batches)) fail('Batch builder produced 0 batches.');

//////////////////// Prepare GAP once (use L1 style prosody) ////////////////////
$gapSSML = '<speak><prosody rate="'.$st1['rate'].'" pitch="'.$st1['pitch'].'" volume="'.$st1['volume'].'"><break time="'.$PAIR_GAP_MS.'ms"/></prosody></speak>';
[$gapBytes,] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice1, $srcCode, $gapSSML, $SAMPLE_RATE_HZ, false);
[$gapFmt, $gapPcm] = wav_decode($gapBytes);
say('Gap clip: bytes='.strlen($gapBytes));

//////////////////// Synthesize & assemble ////////////////////
$finalPcm = '';
$finalFmt = $gapFmt;
$batchCount = count($batches);
$batchIdx = 0;
$tailPadSec = $TAIL_PAD_MS / 1000.0;

foreach ($batches as $batch) {
  $batchIdx++;

  // SSML with prosody wrapper + marks
  $ssml1 = '<speak><prosody rate="'.$st1['rate'].'" pitch="'.$st1['pitch'].'" volume="'.$st1['volume'].'">';
  $ssml2 = '<speak><prosody rate="'.$st2['rate'].'" pitch="'.$st2['pitch'].'" volume="'.$st2['volume'].'">';
  foreach ($batch as $i) {
    $ssml1 .= "<mark name='m{$i}'/>".ssml_escape($rows[$i][0])."<break time='{$ITEM_BREAK_MS}ms'/>";
    $ssml2 .= "<mark name='m{$i}'/>".ssml_escape($rows[$i][1])."<break time='{$ITEM_BREAK_MS}ms'/>";
  }
  $ssml1 .= '</prosody></speak>';
  $ssml2 .= '</prosody></speak>';

  say('Batch size='.count($batch).' | SSML1='.strlen($ssml1).' | SSML2='.strlen($ssml2));

  // Synthesize with timepoints
  [$wav1, $marks1] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice1, $srcCode, $ssml1, $SAMPLE_RATE_HZ, true);
  [$wav2, $marks2] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice2, $tgtCode, $ssml2, $SAMPLE_RATE_HZ, true);

  say('Synth L1: wav='.strlen($wav1).' bytes, marks='.count($marks1));
  say('Synth L2: wav='.strlen($wav2).' bytes, marks='.count($marks2));
  if (empty($marks1) || empty($marks2)) {
    @file_put_contents(__DIR__.'/log_batched.txt', '['.date('c')."] No timepoints returned\nSSML1=".strlen($ssml1).", SSML2=".strlen($ssml2)."\n\n", FILE_APPEND);
    fail('No timepoints returned by Google TTS');
  }

  [$fmt1, $pcm1] = wav_decode($wav1);
  [$fmt2, $pcm2] = wav_decode($wav2);
  say('Formats: ch='.$fmt1['NumChannels'].' sr='.$fmt1['SampleRate'].' bits='.$fmt1['BitsPerSample'].' blockAlign='.$fmt1['BlockAlign']);
  say('PCM sizes: L1='.strlen($pcm1).' L2='.strlen($pcm2).' GAP='.strlen($gapPcm));

  foreach (['NumChannels','SampleRate','BitsPerSample'] as $k) {
    if ($fmt1[$k] !== $fmt2[$k] || $fmt1[$k] !== $gapFmt[$k]) fail('WAV format mismatch on '.$k);
  }

  $finalFmt   = $fmt1;
  $sampleRate = $fmt1['SampleRate'];
  $blockAlign = $fmt1['BlockAlign'];

  // Compute true durations from PCM for accurate last slice
  $totalSec1 = strlen($pcm1) / $blockAlign / $sampleRate;
  $totalSec2 = strlen($pcm2) / $blockAlign / $sampleRate;

  // Map timepoints back to requested order
  $map1=[]; foreach ($marks1 as $m) { $map1[$m['markName']] = (float)$m['timeSeconds']; }
  $map2=[]; foreach ($marks2 as $m) { $map2[$m['markName']] = (float)$m['timeSeconds']; }
  $tp1=[]; $tp2=[];
  foreach ($batch as $i) {
    $k='m'.$i; if (!isset($map1[$k], $map2[$k])) fail('Missing timepoint for index '.$i);
    $tp1[]=['markName'=>$k,'timeSeconds'=>$map1[$k]];
    $tp2[]=['markName'=>$k,'timeSeconds'=>$map2[$k]];
  }

  // Slice precisely
  $slices1 = timepoints_to_pcm_slices($tp1, $sampleRate, $blockAlign, $totalSec1, strlen($pcm1), $tailPadSec);
  $slices2 = timepoints_to_pcm_slices($tp2, $sampleRate, $blockAlign, $totalSec2, strlen($pcm2), $tailPadSec);
  say('Slices: L1='.count($slices1).' L2='.count($slices2));

  // Append pairs: L1 → gap → L2; add inter-pair gap except after very last pair
  $pairCount = count($batch);
  for ($j=0; $j<$pairCount; $j++) {
    [$b1s,$b1e]=$slices1[$j]; [$b2s,$b2e]=$slices2[$j];
    $seg1 = substr($pcm1, $b1s, max(0, $b1e-$b1s));
    $seg2 = substr($pcm2, $b2s, max(0, $b2e-$b2s));
    $finalPcm .= $seg1 . $gapPcm . $seg2;

    $isLast = ($batchIdx === count($batches)) && ($j === $pairCount-1);
    if (!$isLast) $finalPcm .= $gapPcm;
  }
  say('Appended batch PCM, total so far: '.strlen($finalPcm));
}

//////////////////// Save final WAV ////////////////////
$outDir = __DIR__.'/cache'; if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
if (!is_writable($outDir)) fail('cache/ directory is not writable.');
if ($finalPcm==='') fail('Final PCM empty.');

$finalFmt['AudioFormat']   = 1;
$finalFmt['NumChannels']   = (int)$finalFmt['NumChannels'];
$finalFmt['SampleRate']    = (int)$finalFmt['SampleRate'];
$finalFmt['BitsPerSample'] = (int)$finalFmt['BitsPerSample'];

$finalWav = wav_encode($finalFmt, $finalPcm);
$outPath  = $outDir.'/'.$table.'.wav';
file_put_contents($outPath, $finalWav);
$ok = file_exists($outPath) ? filesize($outPath) : 0;
say('WAV written: '.$outPath.' (bytes='.$ok.')');
if ($ok <= 44) fail('Output WAV too small (header only).');

if ($DEBUG) { echo '<hr><b>Done.</b> <a href="main.php?table='.htmlspecialchars($table,ENT_QUOTES).'">Back</a>'; exit; }
header('Location: main.php?table='.urlencode($table)); exit;
