<?php
/**
 * generate_wav_batched.php — Bilingual TTS (batched, WAV‑precise) with VOICE PICKER + PREVIEW
 * - Lists voices with gender + model badges, lets the user preview samples
 * - Defaults to SSML‑safe voices (WaveNet/Standard); optional toggle to show all
 * - Uses v1/voices for listing, v1beta1/text:synthesize for SSML timepoints
 * - Writes cache/<table>.wav, then redirects to main.php
 * - ?debug=1 shows progress; stream previews via ?preview=1&voice=..&lang=..
 */

// ---- Debug controls ----
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

// ---- Bootstrap ----
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // should define $GOOGLE_API_KEY

if (!isset($GOOGLE_API_KEY) || !$GOOGLE_API_KEY) {
  $env = getenv('GOOGLE_API_KEY'); if ($env) { $GOOGLE_API_KEY = $env; }
}
if (empty($GOOGLE_API_KEY)) { fail('Missing GOOGLE_API_KEY. Define $GOOGLE_API_KEY in config.php.'); }
if (!extension_loaded('curl')) { fail('PHP cURL extension is not enabled.'); }

// ---- Config ----
$SAMPLE_RATE_HZ  = 22050;
$BITS_PER_SAMPLE = 16;
$ITEM_BREAK_MS   = 300;
$PAIR_GAP_MS     = 400;
$TAIL_PAD_MS     = 120;
$MAX_SSML_BYTES  = 4600;
$BATCH_MIN       = 20;

// Preferred safe voices (used as fallback if a chosen voice fails)
$VOICE_PREFS = [
  'czech'   => [ ['name' => 'cs-CZ-Wavenet-A', 'code' => 'cs-CZ'], ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'] ],
  'english' => [ ['name' => 'en-GB-Wavenet-B', 'code' => 'en-GB'], ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'] ],
  'german'  => [ ['name' => 'de-DE-Wavenet-B', 'code' => 'de-DE'], ['name' => 'de-DE-Standard-H', 'code' => 'de-DE'] ],
  'french'  => [ ['name' => 'fr-FR-Wavenet-C', 'code' => 'fr-FR'], ['name' => 'fr-FR-Standard-G', 'code' => 'fr-FR'] ],
  'italian' => [ ['name' => 'it-IT-Wavenet-C', 'code' => 'it-IT'], ['name' => 'it-IT-Standard-F', 'code' => 'it-IT'] ],
  'spanish' => [ ['name' => 'es-ES-Wavenet-A', 'code' => 'es-ES'], ['name' => 'es-ES-Standard-G', 'code' => 'es-ES'] ],
];

// Label → language code
$LANG_KEYS = [
  'czech'   => 'cs-CZ',
  'english' => 'en-GB',
  'german'  => 'de-DE',
  'french'  => 'fr-FR',
  'italian' => 'it-IT',
  'spanish' => 'es-ES',
];

// Preview phrases (text‑only)
$PREVIEW_TEXT = [
  'cs-CZ' => 'Ahoj! Toto je ukázkový hlas.',
  'de-DE' => 'Hallo! Das ist eine Stimmprobe.',
  'en-GB' => 'Hello! This is a voice sample.',
  'fr-FR' => 'Bonjour! Ceci est un échantillon de voix.',
  'it-IT' => 'Ciao! Questo è un campione di voce.',
  'es-ES' => '¡Hola! Esta es una muestra de voz.'
];

// ---- Inputs from session ----
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';
if ($table === '' || $col1 === '' || $col2 === '') fail('Missing session info (table/col1/col2).');

$srcKey  = strtolower($col1);
$tgtKey  = strtolower($col2);
if (!isset($LANG_KEYS[$srcKey], $LANG_KEYS[$tgtKey])) { fail("Unsupported language columns: $col1 / $col2"); }
$srcCode = $LANG_KEYS[$srcKey];
$tgtCode = $LANG_KEYS[$tgtKey];

say("Start: table={$table}, col1={$col1} ({$srcCode}), col2={$col2} ({$tgtCode})");

// ------------------- Utility: classify voice model & gender label -------------------
function model_type_from_name(string $name): string {
  $n = strtolower($name);
  if (str_contains($n, 'chirp'))   return 'Chirp';
  if (str_contains($n, 'studio'))  return 'Studio';
  if (str_contains($n, 'neural2')) return 'Neural2';
  if (str_contains($n, 'wavenet')) return 'WaveNet';
  if (str_contains($n, 'polyglot'))return 'Polyglot';
  if (str_contains($n, 'standard'))return 'Standard';
  return 'Other';
}
function gender_label(?string $g): string {
  $g = strtoupper((string)$g);
  if ($g === 'MALE') return 'Male';
  if ($g === 'FEMALE') return 'Female';
  if ($g === 'NEUTRAL') return 'Neutral';
  return '—';
}

// ------------------- Voices API -------------------
function list_voices_for_language(string $apiKey, string $languageCode): array {
  $url = 'https://texttospeech.googleapis.com/v1/voices?languageCode=' . urlencode($languageCode) . '&key=' . urlencode($apiKey);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 20
  ]);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $code !== 200) {
    TRACE("list_voices FAILED for $languageCode: HTTP $code, err=$err, body=$res");
    return [];
  }
  $j = json_decode($res, true);
  if (!isset($j['voices']) || !is_array($j['voices'])) return [];

  $out = [];
  foreach ($j['voices'] as $v) {
    if (empty($v['name'])) continue;
    // Keep only voices that include our requested language code
    if (!empty($v['languageCodes']) && !in_array($languageCode, $v['languageCodes'], true)) continue;
    $name  = $v['name'];
    $gender= $v['ssmlGender'] ?? null;
    $sr    = $v['naturalSampleRateHertz'] ?? null;
    $model = model_type_from_name($name);
    $out[] = ['name'=>$name, 'gender'=>$gender_label = gender_label($gender), 'sampleRate'=>$sr, 'model'=>$model];
  }

  // Sort: Studio/Neural2/WaveNet/Standard/others; then by name
  usort($out, function($a,$b){
    $rank = ['Studio'=>0,'Neural2'=>1,'WaveNet'=>2,'Standard'=>3,'Polyglot'=>4,'Chirp'=>5,'Other'=>6];
    $ra = $rank[$a['model']] ?? 99; $rb = $rank[$b['model']] ?? 99;
    if ($ra !== $rb) return $ra <=> $rb;
    return strcmp($a['name'], $b['name']);
  });
  return $out;
}

// ------------------- Preview endpoint (text‑only, no SSML) -------------------
if (isset($_GET['preview']) && $_GET['preview'] === '1') {
  $voice = trim($_GET['voice'] ?? '');
  $lang  = trim($_GET['lang'] ?? '');
  if ($voice === '' || $lang === '' || empty($PREVIEW_TEXT[$lang])) {
    http_response_code(400); echo 'Missing or invalid voice/lang'; exit;
  }
  $payload = [
    'input'       => ['text' => $PREVIEW_TEXT[$lang]],
    'voice'       => ['languageCode' => $lang, 'name' => $voice],
    'audioConfig' => ['audioEncoding' => 'MP3', 'sampleRateHertz' => 22050]
  ];
  $ch = curl_init('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode($GOOGLE_API_KEY));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload)
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($res === false || $code !== 200) { http_response_code(502); echo 'Preview synth failed'; exit; }

  $j = json_decode($res, true);
  if (empty($j['audioContent'])) { http_response_code(502); echo 'No audio'; exit; }
  $bytes = base64_decode($j['audioContent']);
  header('Content-Type: audio/mpeg');
  header('Cache-Control: no-cache');
  echo $bytes; exit;
}

// ------------------- TTS helpers -------------------
function tts_google_wav(string $apiKey, string $voiceName, string $langCode, string $ssml, int $sampleRate, bool $wantMarks = false): array {
  $payload = [
    'input'       => ['ssml' => $ssml],
    'voice'       => ['languageCode' => $langCode, 'name' => $voiceName],
    'audioConfig' => ['audioEncoding' => 'LINEAR16', 'sampleRateHertz' => $sampleRate]
  ];
  if ($wantMarks) $payload['enableTimePointing'] = ['SSML_MARK'];

  $ch = curl_init('https://texttospeech.googleapis.com/v1beta1/text:synthesize?key=' . urlencode($apiKey));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload)
  ]);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false) fail('cURL error: ' . $err);
  $j = json_decode($res, true);
  if ($code !== 200 || empty($j['audioContent'])) {
    @file_put_contents(__DIR__.'/log_batched.txt', '['.date('c')."] HTTP $code\nPayload: ".json_encode($payload)."\nResponse: $res\n\n", FILE_APPEND);
    fail('Google TTS failed (WAV). See log_batched.txt');
  }
  $bytes = base64_decode($j['audioContent']);
  $marks = $j['timepoints'] ?? [];
  return [$bytes, $marks];
}
function synth_blob_with_fallback(array $voicePrefs, string $apiKey, string $ssml, int $sampleRate, bool $wantMarks): array {
  $last = null;
  foreach ($voicePrefs as $v) {
    try { return tts_google_wav($apiKey, $v['name'], $v['code'], $ssml, $sampleRate, $wantMarks); }
    catch (Throwable $e) { $last = $e; }
  }
  if ($last) fail('All voice fallbacks failed: ' . $last->getMessage());
  fail('No voice available');
}

// ------------------- WAV helpers -------------------
function wav_decode(string $bytes): array {
  if (substr($bytes,0,4)!=="RIFF" || substr($bytes,8,4)!=="WAVE") { throw new RuntimeException('Not a WAV file'); }
  $pos = 12; $len = strlen($bytes);
  $fmt = null; $dataOff = null; $dataLen = null;
  while ($pos + 8 <= $len) {
    $id = substr($bytes,$pos,4);
    $sz = unpack('V', substr($bytes,$pos+4,4))[1];
    $pos += 8;
    if ($id === 'fmt ') {
      $fmt = unpack('vAudioFormat/vNumChannels/VSampleRate/VByteRate/vBlockAlign/vBitsPerSample', substr($bytes,$pos,16));
    } elseif ($id === 'data') {
      $dataOff = $pos; $dataLen = $sz;
    }
    $pos += $sz; if ($pos % 2) $pos++;
  }
  if (!$fmt || $dataOff===null) throw new RuntimeException('WAV missing fmt/data');
  $pcm = substr($bytes, $dataOff, $dataLen);
  return [$fmt, $pcm];
}
function wav_encode(array $fmt, string $pcm): string {
  $channels = (int)$fmt['NumChannels'];
  $sr       = (int)$fmt['SampleRate'];
  $bits     = (int)$fmt['BitsPerSample'];
  $blockAlign = (int)max(1, ($channels * $bits) / 8);
  $byteRate   = (int)($sr * $blockAlign);

  $dataLen = strlen($pcm);
  $pad     = ($dataLen % 2) ? "\x00" : "";

  $fmtBin  = pack('vvVVvv', 1, $channels, $sr, $byteRate, $blockAlign, $bits);
  $chunks  = "fmt " . pack('V', 16) . $fmtBin;
  $chunks .= "data" . pack('V', $dataLen) . $pcm . $pad;
  $riffSz  = 4 + strlen($chunks);
  return "RIFF" . pack('V', $riffSz) . "WAVE" . $chunks;
}

/** Convert timepoints to byte slices using real PCM duration + tail pad. */
function timepoints_to_pcm_slices(array $timepoints, int $sampleRate, int $blockAlign, float $defaultTrailingBreakSec, float $totalSecondsExact, int $totalPcmBytes, float $tailPadSec): array {
  $n = count($timepoints); $slices = [];
  for ($i=0; $i<$n; $i++) {
    $tStart = (float)$timepoints[$i]['timeSeconds'];
    $tEnd = ($i + 1 < $n) ? (float)$timepoints[$i+1]['timeSeconds'] : ($totalSecondsExact + $tailPadSec);
    $sStart = (int)floor($tStart * $sampleRate);
    $sEnd   = (int)ceil($tEnd   * $sampleRate);
    $bStart = $sStart * $blockAlign;
    $bEnd   = min($totalPcmBytes, $sEnd * $blockAlign);
    if ($bEnd < $bStart) $bEnd = $bStart;
    $slices[] = [$bStart, $bEnd];
  }
  return $slices;
}

/** Build batches under size limits. */
function build_batches(array $rows, int $maxSsmlBytes, int $minPerBatch, int $itemBreakMs): array {
  $batches = []; $cur = []; $curLen = strlen('<speak></speak>');
  foreach ($rows as $idx => $pair) {
    [$a, $b] = $pair;
    $piece = strlen("<mark name='m$idx'/>" . htmlspecialchars($a, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<break time='{$itemBreakMs}ms'/>");
    if (!empty($cur) && ($curLen + $piece) > $maxSsmlBytes && count($cur) >= $minPerBatch) {
      $batches[] = $cur; $cur = []; $curLen = strlen('<speak></speak>');
    }
    $cur[] = $idx; $curLen += $piece;
  }
  if (!empty($cur)) $batches[] = $cur; return $batches;
}

// ------------------- Voice Picker UI -------------------
function render_voice_picker(string $table, string $srcKey, string $srcCode, string $tgtKey, string $tgtCode, array $voices1, array $voices2, bool $showAll) {
  $badge = function($m) {
    $colors = ['Studio'=>'#8b5cf6','Neural2'=>'#059669','WaveNet'=>'#2563eb','Standard'=>'#475569','Polyglot'=>'#0ea5e9','Chirp'=>'#dc2626','Other'=>'#6b7280'];
    $bg = $colors[$m] ?? '#6b7280';
    return "<span style='display:inline-block;padding:2px 6px;border-radius:999px;background:$bg;color:#fff;font-size:11px;line-height:1;'>$m</span>";
  };
  $info = function($v) use ($badge) {
    $parts = [];
    if (!empty($v['gender'])) $parts[] = $v['gender'];
    if (!empty($v['model']))  $parts[] = $badge($v['model']);
    if (!empty($v['sampleRate'])) $parts[] = ($v['sampleRate']/1000).'kHz';
    return implode(' · ', $parts);
  };

  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Pick Voices</title>";
  echo "<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:24px;}
    .card{max-width:720px;border:1px solid #e5e7eb;border-radius:12px;padding:18px;}
    label{display:block;margin:14px 0 6px;font-weight:600}
    select,button,input[type=checkbox]{font-size:14px;padding:8px;}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
    .muted{color:#64748b;font-size:12px;}
    .list{margin:10px 0 6px;}
    .voice-row{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px dashed #e5e7eb;}
    audio{height:28px;}
  </style>";
  echo "</head><body><div class='card'>";
  echo "<h2>Choose voices for <code>".htmlspecialchars($table)."</code></h2>";
  echo "<div class='muted'>Language 1: <b>".htmlspecialchars($srcKey)." ($srcCode)</b> &nbsp;&nbsp;|&nbsp;&nbsp; Language 2: <b>".htmlspecialchars($tgtKey)." ($tgtCode)</b></div>";

  // Notice about SSML support
  echo "<p class='muted'>Default list shows voices that generally work with SSML marks (WaveNet/Standard). ".
       "Tick “Show experimental voices” to include Studio/Chirp/etc. (may fail with SSML/timepoints).</p>";

  echo "<form method='post' action='' style='margin:0 0 14px 0;'>";
  echo "<input type='hidden' name='show_all' value='".($showAll ? "1":"0")."'>";
  echo "<label>Voice for ".htmlspecialchars($srcKey)." ($srcCode)</label>";
  echo "<select name='voice1' required>";
  foreach ($voices1 as $v) {
    $opt = $v['name']." — ".$info($v);
    echo "<option value='".htmlspecialchars($v['name'],ENT_QUOTES)."'>".htmlspecialchars($opt)."</option>";
  }
  echo "</select>";

  echo "<label>Voice for ".htmlspecialchars($tgtKey)." ($tgtCode)</label>";
  echo "<select name='voice2' required>";
  foreach ($voices2 as $v) {
    $opt = $v['name']." — ".$info($v);
    echo "<option value='".htmlspecialchars($v['name'],ENT_QUOTES)."'>".htmlspecialchars($opt)."</option>";
  }
  echo "</select>";

  echo "<div class='row' style='margin-top:14px;'>
          <button type='submit'>🎧 Generate WAV</button>
          <a href='main.php?table=".urlencode($table)."' style='text-decoration:none;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;'>Cancel</a>
        </div>";
  echo "</form>";

  // Toggle “show all”
  $toggleUrl = $_SERVER['PHP_SELF'].'?pick=1&all='.($showAll? '0':'1');
  echo "<div class='row' style='margin-top:6px;'>
          <a href='".htmlspecialchars($toggleUrl,ENT_QUOTES)."' style='text-decoration:none;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;'>".
          ($showAll ? "Hide experimental voices" : "Show experimental voices")."</a>
        </div>";

  // Quick preview lists
  $renderList = function($title,$lang,$arr) {
    echo "<h3 style='margin:18px 0 6px;'>Preview: ".htmlspecialchars($title)."</h3>";
    echo "<div class='list'>";
    foreach ($arr as $v) {
      $q = http_build_query(['preview'=>1,'voice'=>$v['name'],'lang'=>$lang]);
      $src = htmlspecialchars($_SERVER['PHP_SELF'].'?'.$q, ENT_QUOTES);
      echo "<div class='voice-row'>
              <div>".htmlspecialchars($v['name'])."</div>
              <div class='muted'>".htmlspecialchars($v['gender'])."</div>
              <div class='muted'>".htmlspecialchars($v['model'])."</div>
              <audio controls src='$src'></audio>
            </div>";
    }
    echo "</div>";
  };

  // Show up to 6 previews per side to keep the page light
  $renderList($srcKey." ($srcCode)", $srcCode, array_slice($voices1, 0, 6));
  $renderList($tgtKey." ($tgtCode)", $tgtCode, array_slice($voices2, 0, 6));

  echo "</div></body></html>";
  exit;
}

// ------------------- Build the picker (GET) or proceed (POST) -------------------
$wantAll = (isset($_GET['all']) && $_GET['all'] === '1') || (isset($_POST['show_all']) && $_POST['show_all'] === '1');

// If no POST selection yet, render picker
if (!isset($_POST['voice1'], $_POST['voice2'])) {
  // Fetch all available voices
  $v1 = list_voices_for_language($GOOGLE_API_KEY, $srcCode);
  $v2 = list_voices_for_language($GOOGLE_API_KEY, $tgtCode);

  // If Czech shows only a couple, that's Google's catalog. (It’s normal.)
  // Filter to SSML‑friendly by default: keep WaveNet + Standard (+ Neural2 if you want; comment in/out)
  $is_ssml_ok = function($v) use ($wantAll) {
    if ($wantAll) return true;
    $m = $v['model'];
    return ($m === 'WaveNet' || $m === 'Standard'); // safest for SSML marks
    // If you find Neural2 works with marks in your project, allow it:
    // return ($m === 'WaveNet' || $m === 'Standard' || $m === 'Neural2');
  };

  $v1 = array_values(array_filter($v1, $is_ssml_ok));
  $v2 = array_values(array_filter($v2, $is_ssml_ok));

  // Fallback to prefs if list is empty
  if (empty($v1)) {
    $v1 = array_map(function($p){ return ['name'=>$p['name'],'gender'=>'—','sampleRate'=>22050,'model'=>model_type_from_name($p['name'])]; }, $VOICE_PREFS[$srcKey] ?? []);
  }
  if (empty($v2)) {
    $v2 = array_map(function($p){ return ['name'=>$p['name'],'gender'=>'—','sampleRate'=>22050,'model'=>model_type_from_name($p['name'])]; }, $VOICE_PREFS[$tgtKey] ?? []);
  }

  render_voice_picker($table, $srcKey, $srcCode, $tgtKey, $tgtCode, $v1, $v2, $wantAll);
}

// ------------------- Proceed with synthesis -------------------
$selectedVoice1 = trim($_POST['voice1'] ?? '');
$selectedVoice2 = trim($_POST['voice2'] ?? '');
if ($selectedVoice1 === '' || $selectedVoice2 === '') fail('Please select both voices.');

say("Chosen voices: L1={$selectedVoice1} | L2={$selectedVoice2}");

// ---- Load data ----
$conn->set_charset('utf8mb4');
$tableEsc = str_replace('`','``',$table);
$col1Esc  = str_replace('`','``',$col1);
$col2Esc  = str_replace('`','``',$col2);
$sql = "SELECT `{$col1Esc}` AS c1, `{$col2Esc}` AS c2 FROM `{$tableEsc}`";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) fail('No data found in table.');
$rows = [];
while ($r = $result->fetch_assoc()) { $a = trim((string)$r['c1']); $b = trim((string)$r['c2']); if ($a !== '' && $b !== '') $rows[] = [$a,$b]; }
$result->free(); $conn->close();
if (empty($rows)) fail('No usable rows (empty values).');

say('Rows loaded: '.count($rows));

// Optional: limit for testing
if (isset($_GET['limit'])) {
  $lim = max(1, (int)$_GET['limit']); $rows = array_slice($rows, 0, $lim);
  say('LIMIT active: '.$lim.' rows');
}

// Build batches
$batches = build_batches($rows, $MAX_SSML_BYTES, $BATCH_MIN, $ITEM_BREAK_MS);
say('Batches: '.count($batches).' (ITEM_BREAK_MS='.$ITEM_BREAK_MS.', MAX_SSML_BYTES='.$MAX_SSML_BYTES.')');
if (empty($batches)) fail('Batch builder produced 0 batches.');

// Prepare gap WAV using selected L1; fallback to prefs if it fails
$gapSSML = '<speak><break time="'.$PAIR_GAP_MS.'ms"/></speak>';
try {
  [$gapBytes,] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice1, $srcCode, $gapSSML, $SAMPLE_RATE_HZ, false);
} catch (Throwable $e) {
  TRACE("Gap synth with selected L1 failed: ".$e->getMessage()." — falling back.");
  [$gapBytes,] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $gapSSML, $SAMPLE_RATE_HZ, false);
}
[$gapFmt, $gapPcm] = wav_decode($gapBytes);
say('Gap clip: bytes='.strlen($gapBytes));

$finalPcm = '';
$finalFmt = $gapFmt;
$batchCount = count($batches);
$batchIdx = 0;

foreach ($batches as $batch) {
  $batchIdx++;

  $ssml1 = '<speak>'; $ssml2 = '<speak>';
  foreach ($batch as $i) {
    $ssml1 .= "<mark name='m{$i}'/>" . htmlspecialchars($rows[$i][0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<break time='{$ITEM_BREAK_MS}ms'/>";
    $ssml2 .= "<mark name='m{$i}'/>" . htmlspecialchars($rows[$i][1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "<break time='{$ITEM_BREAK_MS}ms'/>";
  }
  $ssml1 .= '</speak>'; $ssml2 .= '</speak>';

  say('Batch size='.count($batch).' | SSML1='.strlen($ssml1).' | SSML2='.strlen($ssml2));

  // Synthesize with selected voices; on failure, fallback to safe prefs
  try { [$wav1, $marks1] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice1, $srcCode, $ssml1, $SAMPLE_RATE_HZ, true); }
  catch (Throwable $e) {
    TRACE("Selected L1 voice failed: ".$e->getMessage()." — falling back.");
    [$wav1, $marks1] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $ssml1, $SAMPLE_RATE_HZ, true);
  }
  try { [$wav2, $marks2] = tts_google_wav($GOOGLE_API_KEY, $selectedVoice2, $tgtCode, $ssml2, $SAMPLE_RATE_HZ, true); }
  catch (Throwable $e) {
    TRACE("Selected L2 voice failed: ".$e->getMessage()." — falling back.");
    [$wav2, $marks2] = synth_blob_with_fallback($VOICE_PREFS[$tgtKey], $GOOGLE_API_KEY, $ssml2, $SAMPLE_RATE_HZ, true);
  }

  say('Synth L1: wav='.strlen($wav1).' bytes, marks='.count($marks1));
  say('Synth L2: wav='.strlen($wav2).' bytes, marks='.count($marks2));
  if (empty($marks1) || empty($marks2)) {
    @file_put_contents(__DIR__.'/log_batched.txt', '['.date('c')."] No timepoints returned (mp2)\nSSML1 len=".strlen($ssml1).", SSML2 len=".strlen($ssml2)."\n\n", FILE_APPEND);
    fail('No timepoints returned by Google TTS');
  }

  [$fmt1, $pcm1] = wav_decode($wav1);
  [$fmt2, $pcm2] = wav_decode($wav2);
  say('Formats: ch='.$fmt1['NumChannels'].' sr='.$fmt1['SampleRate'].' bits='.$fmt1['BitsPerSample'].' blockAlign='.$fmt1['BlockAlign']);
  say('PCM sizes: L1='.strlen($pcm1).' L2='.strlen($pcm2).' GAP='.strlen($gapPcm));

  foreach (['NumChannels','SampleRate','BitsPerSample'] as $k) {
    if ($fmt1[$k] !== $fmt2[$k] || $fmt1[$k] !== $gapFmt[$k]) { fail('WAV format mismatch on '.$k); }
  }
  $finalFmt   = $fmt1;
  $sampleRate = $fmt1['SampleRate'];
  $blockAlign = $fmt1['BlockAlign'];
  $trailSec   = $ITEM_BREAK_MS / 1000.0;
  $tailPadSec = $TAIL_PAD_MS / 1000.0;

  $totalSec1 = strlen($pcm1) / $blockAlign / $sampleRate;
  $totalSec2 = strlen($pcm2) / $blockAlign / $sampleRate;

  $map1 = []; foreach ($marks1 as $m) { $map1[$m['markName']] = (float)$m['timeSeconds']; }
  $map2 = []; foreach ($marks2 as $m) { $map2[$m['markName']] = (float)$m['timeSeconds']; }
  $tp1 = []; $tp2 = [];
  foreach ($batch as $i) {
    $k = 'm'.$i; if (!isset($map1[$k], $map2[$k])) fail('Missing timepoint for index '.$i);
    $tp1[] = ['markName'=>$k, 'timeSeconds'=>$map1[$k]];
    $tp2[] = ['markName'=>$k, 'timeSeconds'=>$map2[$k]];
  }

  $slices1 = timepoints_to_pcm_slices($tp1, $sampleRate, $blockAlign, $trailSec, $totalSec1, strlen($pcm1), $tailPadSec);
  $slices2 = timepoints_to_pcm_slices($tp2, $sampleRate, $blockAlign, $trailSec, $totalSec2, strlen($pcm2), $tailPadSec);
  say('Slices: L1='.count($slices1).' L2='.count($slices2));

  $pairCount = count($batch);
  for ($j = 0; $j < $pairCount; $j++) {
    [$b1s, $b1e] = $slices1[$j]; [$b2s, $b2e] = $slices2[$j];
    $seg1 = substr($pcm1, $b1s, max(0, $b1e - $b1s));
    $seg2 = substr($pcm2, $b2s, max(0, $b2e - $b2s));

    $finalPcm .= $seg1 . $gapPcm . $seg2;

    $isLastPairOfLastBatch = ($batchIdx === $batchCount) && ($j === $pairCount - 1);
    if (!$isLastPairOfLastBatch) { $finalPcm .= $gapPcm; }
  }
  say('Appended batch PCM, total so far: '.strlen($finalPcm));
}

// ---- Save final WAV ----
$outDir  = __DIR__ . '/cache'; if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
if (!is_writable($outDir)) { fail('cache/ directory is not writable.'); }
if ($finalPcm === '') fail('Final PCM empty (no audio assembled).');

$finalFmt['AudioFormat']   = 1;
$finalFmt['NumChannels']   = (int)$finalFmt['NumChannels'];
$finalFmt['SampleRate']    = (int)$finalFmt['SampleRate'];
$finalFmt['BitsPerSample'] = (int)$finalFmt['BitsPerSample'];

$finalWav = wav_encode($finalFmt, $finalPcm);
$outPath  = $outDir . '/' . $table . '.wav';
file_put_contents($outPath, $finalWav);

$ok = file_exists($outPath) ? filesize($outPath) : 0; say('WAV written: '.$outPath.' (bytes='.$ok.')');
if ($ok <= 44) { fail('Output WAV too small (header only) — likely empty PCM.'); }

if ($DEBUG) { echo '<hr><b>Done.</b> <a href="main.php?table='.htmlspecialchars($table,ENT_QUOTES).'">Back</a>'; exit; }

header('Location: main.php?table=' . urlencode($table));
exit;
