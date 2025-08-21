<?php
/**
 * generate_mp3_batched.php — robust version with diagnostics
 * - Batches many rows per language, uses SSML <mark/> timepoints
 * - Few requests, supports cs/en/de/fr/it/es
 * - Writes cache/<table>.mp3
 * - Adds strong error checks + logging to log_batched.txt
 *
 * To debug in browser: append ?debug=1 to the URL to see errors.
 */

// ---- Debug controls ----
$DEBUG = isset($_GET['debug']);
if ($DEBUG) { ini_set('display_errors', 1); error_reporting(E_ALL); }

function fail($msg, $httpCode = 500) {
  @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] FAIL: $msg
", FILE_APPEND);
  http_response_code($httpCode);
  echo "❌ $msg";
  exit;
}

// ---- Bootstrap ----
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // should define $GOOGLE_API_KEY

if (!isset($GOOGLE_API_KEY) || !$GOOGLE_API_KEY) {
  // fallback: try env var
  $env = getenv('GOOGLE_API_KEY');
  if ($env) { $GOOGLE_API_KEY = $env; }
}
if (empty($GOOGLE_API_KEY)) {
  fail('Missing GOOGLE_API_KEY. Define $GOOGLE_API_KEY in config.php.');
}
if (!extension_loaded('curl')) { fail('PHP cURL extension is not enabled.'); }

// ---- Config ----
$SAMPLE_RATE_HZ = 22050;       // Fix output format for consistent slicing
$ITEM_BREAK_MS  = 300;         // pause after each item inside monolingual blobs
$PAIR_GAP_MS    = 400;         // gap between L1 and L2 in final bilingual stream
$MAX_SSML_BYTES = 4600;        // keep below ~5000 bytes safety
$BATCH_MIN      = 20;          // min items per batch before splitting

$VOICE_PREFS = [
  'czech'   => [ ['name' => 'cs-CZ-Wavenet-A', 'code' => 'cs-CZ'], ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'] ],
  'english' => [ ['name' => 'en-GB-Wavenet-B', 'code' => 'en-GB'], ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'] ],
  'german'  => [ ['name' => 'de-DE-Wavenet-B', 'code' => 'de-DE'], ['name' => 'de-DE-Standard-H', 'code' => 'de-DE'] ],
  'french'  => [ ['name' => 'fr-FR-Wavenet-C', 'code' => 'fr-FR'], ['name' => 'fr-FR-Standard-G', 'code' => 'fr-FR'] ],
  'italian' => [ ['name' => 'it-IT-Wavenet-C', 'code' => 'it-IT'], ['name' => 'it-IT-Standard-F', 'code' => 'it-IT'] ],
  'spanish' => [ ['name' => 'es-ES-Wavenet-A', 'code' => 'es-ES'], ['name' => 'es-ES-Standard-G', 'code' => 'es-ES'] ],
];

// ---- Inputs from session (same as your other script) ----
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';
if ($table === '' || $col1 === '' || $col2 === '') fail('Missing session info (table/col1/col2).');

$srcKey = strtolower($col1);
$tgtKey = strtolower($col2);
if (!isset($VOICE_PREFS[$srcKey], $VOICE_PREFS[$tgtKey])) {
  fail("Unsupported language columns: $col1 / $col2");
}

// ---- Helpers ----
function ssml_escape(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tts_google_mp3(string $apiKey, string $voiceName, string $langCode, string $ssml, int $sampleRate, bool $wantMarks = false): array {
  $payload = [
    'input'       => ['ssml' => $ssml],
    'voice'       => ['languageCode' => $langCode, 'name' => $voiceName],
    'audioConfig' => ['audioEncoding' => 'MP3', 'sampleRateHertz' => $sampleRate]
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

  if ($res === false) {
    fail('cURL error: ' . $err);
  }
  $j = json_decode($res, true);
  if ($code !== 200 || empty($j['audioContent'])) {
    @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] HTTP $code
Payload: ".json_encode($payload)."
Response: $res

", FILE_APPEND);
    fail('Google TTS failed. See log_batched.txt');
  }
  $bytes = base64_decode($j['audioContent']);
  $marks = $j['timepoints'] ?? [];
  return [$bytes, $marks];
}

function synth_blob_with_fallback(array $voicePrefs, string $apiKey, string $ssml, int $sampleRate, bool $wantMarks): array {
  $last = null;
  foreach ($voicePrefs as $v) {
    try { return tts_google_mp3($apiKey, $v['name'], $v['code'], $ssml, $sampleRate, $wantMarks); }
    catch (Throwable $e) { $last = $e; }
  }
  if ($last) fail('All voice fallbacks failed: ' . $last->getMessage());
  fail('No voice available');
}

function timepoints_to_slices(array $timepoints, int $totalBytes, float $totalSeconds): array {
  $n = count($timepoints);
  if ($n === 0) return [];
  $bps = ($totalSeconds > 0.0) ? ($totalBytes / $totalSeconds) : 0.0;
  $ranges = [];
  for ($i = 0; $i < $n; $i++) {
    $tStart = (float)$timepoints[$i]['timeSeconds'];
    $tEnd   = ($i+1 < $n) ? (float)$timepoints[$i+1]['timeSeconds'] : $totalSeconds;
    $bStart = (int)floor($tStart * $bps);
    $bEnd   = (int)floor($tEnd   * $bps);
    if ($bStart < 0) $bStart = 0; if ($bEnd < $bStart) $bEnd = $bStart;
    $ranges[] = [$bStart, $bEnd];
  }
  return $ranges;
}

function build_batches(array $rows, int $maxSsmlBytes, int $minPerBatch, int $itemBreakMs): array {
  $batches = [];
  $cur = [];
  $curLen = strlen('<speak></speak>');
  foreach ($rows as $idx => $pair) {
    [$a, $b] = $pair;
    $piece = strlen("<mark name='m$idx'/>" . ssml_escape($a) . "<break time='{$itemBreakMs}ms'/>");
    if (!empty($cur) && ($curLen + $piece) > $maxSsmlBytes && count($cur) >= $minPerBatch) {
      $batches[] = $cur; $cur = []; $curLen = strlen('<speak></speak>');
    }
    $cur[] = $idx; $curLen += $piece;
  }
  if (!empty($cur)) $batches[] = $cur;
  return $batches;
}

// ---- Load data ----
$conn->set_charset('utf8mb4');
$tableEsc = str_replace('`','``',$table);
$col1Esc  = str_replace('`','``',$col1);
$col2Esc  = str_replace('`','``',$col2);
$sql = "SELECT `{$col1Esc}` AS c1, `{$col2Esc}` AS c2 FROM `{$tableEsc}`";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) fail('No data found in table.');
$rows = [];
while ($r = $result->fetch_assoc()) {
  $a = trim((string)$r['c1']);
  $b = trim((string)$r['c2']);
  if ($a === '' || $b === '') continue;
  $rows[] = [$a, $b];
}
$result->free();
$conn->close();
if (empty($rows)) fail('No usable rows (empty values).');

// ---- Build batches ----
$batches = build_batches($rows, $MAX_SSML_BYTES, $BATCH_MIN, $ITEM_BREAK_MS);
if (empty($batches)) fail('Batch builder produced 0 batches.');

// ---- Prepare reusable gap MP3 ----
$gapSSML = "<speak><break time='{$PAIR_GAP_MS}ms'/></speak>";
[$gapBytes,] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $gapSSML, $SAMPLE_RATE_HZ, false);

$final = '';

foreach ($batches as $batch) {
  // Build monolingual blobs with marks
  $ssml1 = '<speak>';
  $ssml2 = '<speak>';
  foreach ($batch as $i) {
    $ssml1 .= "<mark name='m{$i}'/>" . ssml_escape($rows[$i][0]) . "<break time='{$ITEM_BREAK_MS}ms'/>";
    $ssml2 .= "<mark name='m{$i}'/>" . ssml_escape($rows[$i][1]) . "<break time='{$ITEM_BREAK_MS}ms'/>";
  }
  $ssml1 .= '</speak>';
  $ssml2 .= '</speak>';

  // Synthesize both with timepoints
  try {
    [$bytes1, $marks1] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $ssml1, $SAMPLE_RATE_HZ, true);
    [$bytes2, $marks2] = synth_blob_with_fallback($VOICE_PREFS[$tgtKey], $GOOGLE_API_KEY, $ssml2, $SAMPLE_RATE_HZ, true);
  } catch (Throwable $e) {
    fail('Synthesis failed: ' . $e->getMessage());
  }

  if (empty($marks1) || empty($marks2)) {
    @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] No timepoints returned.
SSML1 len=".strlen($ssml1).", SSML2 len=".strlen($ssml2)."
", FILE_APPEND);
    fail('No timepoints returned by Google TTS');
  }

  $t1_total = (float)end($marks1)['timeSeconds'] + ($ITEM_BREAK_MS/1000.0);
  $t2_total = (float)end($marks2)['timeSeconds'] + ($ITEM_BREAK_MS/1000.0);

  // Map marks to original order
  $map1 = []; foreach ($marks1 as $m) { $map1[$m['markName']] = (float)$m['timeSeconds']; }
  $map2 = []; foreach ($marks2 as $m) { $map2[$m['markName']] = (float)$m['timeSeconds']; }
  $tp1 = []; $tp2 = [];
  foreach ($batch as $i) {
    $k = 'm'.$i;
    if (!isset($map1[$k], $map2[$k])) fail('Missing timepoint for index '.$i);
    $tp1[] = ['markName'=>$k, 'timeSeconds'=>$map1[$k]];
    $tp2[] = ['markName'=>$k, 'timeSeconds'=>$map2[$k]];
  }

  // Compute slices
  $slices1 = timepoints_to_slices($tp1, strlen($bytes1), $t1_total);
  $slices2 = timepoints_to_slices($tp2, strlen($bytes2), $t2_total);

  // Append bilingual pairs
  for ($j = 0; $j < count($batch); $j++) {
    [$b1s, $b1e] = $slices1[$j];
    [$b2s, $b2e] = $slices2[$j];
    $seg1 = substr($bytes1, $b1s, max(0, $b1e - $b1s));
    $seg2 = substr($bytes2, $b2s, max(0, $b2e - $b2s));
    $final .= $seg1 . $gapBytes . $seg2 . $gapBytes; // COL1 → gap → COL2 → gap
  }
}

// ---- Save ----
$outDir  = __DIR__ . '/cache';
$outPath = $outDir . '/' . $table . '.mp3';
if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
if (!is_writable($outDir)) { fail('cache/ directory is not writable.'); }

if ($final === '') fail('Final MP3 is empty (nothing synthesized).');
file_put_contents($outPath, $final);

// ---- Redirect ----
header('Location: main.php?table=' . urlencode($table));
exit;
