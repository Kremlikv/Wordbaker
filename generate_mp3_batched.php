<?php
/**
 * generate_mp2_batched.php — Bilingual TTS (batched, WAV-precise)
 *
 * - Uses Google Cloud TTS v1beta1 with SSML <mark/> timepoints
 * - Synthesizes TWO big WAV blobs (col1 language and col2 language) per batch
 * - Slices by exact PCM samples (no MP3 drift) and assembles as: L1 → gap → L2 → gap …
 * - Supports cs/en/de/fr/it/es (WaveNet first, fallback to Standard)
 * - Writes cache/<table>.wav and redirects to main.php
 *
 * Debug: append ?debug=1 to the URL
 */

// ---- Debug controls ----
$DEBUG = isset($_GET['debug']);
if ($DEBUG) { ini_set('display_errors', 1); error_reporting(E_ALL); }

function fail($msg, $httpCode = 500) {
  @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] FAIL(mp2): $msg\n", FILE_APPEND);
  http_response_code($httpCode);
  echo "❌ $msg";
  exit;
}

// ---- Bootstrap ----
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // should define $GOOGLE_API_KEY

if (!isset($GOOGLE_API_KEY) || !$GOOGLE_API_KEY) {
  $env = getenv('GOOGLE_API_KEY');
  if ($env) { $GOOGLE_API_KEY = $env; }
}
if (empty($GOOGLE_API_KEY)) {
  fail('Missing GOOGLE_API_KEY. Define $GOOGLE_API_KEY in config.php.');
}
if (!extension_loaded('curl')) { fail('PHP cURL extension is not enabled.'); }

// ---- Config ----
$SAMPLE_RATE_HZ = 22050;       // WAV sample rate
$BITS_PER_SAMPLE = 16;         // LINEAR16
$ITEM_BREAK_MS  = 300;         // pause after each item INSIDE mono blobs
$PAIR_GAP_MS    = 400;         // gap between L1 and L2 in FINAL output
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

// ---- Inputs from session ----
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
function ssml_escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function tts_google_wav(string $apiKey, string $voiceName, string $langCode, string $ssml, int $sampleRate, int $bits = 16, bool $wantMarks = false): array {
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
    @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] HTTP $code\nPayload: ".json_encode($payload)."\nResponse: $res\n\n", FILE_APPEND);
    fail('Google TTS failed (WAV). See log_batched.txt');
  }
  $bytes = base64_decode($j['audioContent']);
  $marks = $j['timepoints'] ?? [];
  return [$bytes, $marks];
}

function synth_blob_with_fallback(array $voicePrefs, string $apiKey, string $ssml, int $sampleRate, bool $wantMarks): array {
  $last = null;
  foreach ($voicePrefs as $v) {
    try { return tts_google_wav($apiKey, $v['name'], $v['code'], $ssml, $sampleRate, 16, $wantMarks); }
    catch (Throwable $e) { $last = $e; }
  }
  if ($last) fail('All voice fallbacks failed: ' . $last->getMessage());
  fail('No voice available');
}

// Parse a minimal WAV header and return [fmt] + PCM bytes.
function wav_decode(string $bytes): array {
  if (substr($bytes,0,4)!=="RIFF" || substr($bytes,8,4)!=="WAVE") {
    throw new RuntimeException("Not a WAV file");
  }
  $pos = 12; $len = strlen($bytes);
  $fmt = null; $dataOff = null; $dataLen = null;
  while ($pos + 8 <= $len) {
    $id = substr($bytes,$pos,4);
    $sz = unpack('V', substr($bytes,$pos+4,4))[1];
    $pos += 8;
    if ($id === "fmt ") {
      $fmt = unpack('vAudioFormat/vNumChannels/VSampleRate/VByteRate/vBlockAlign/vBitsPerSample', substr($bytes,$pos,16));
    } elseif ($id === "data") {
      $dataOff = $pos; $dataLen = $sz;
    }
    $pos += $sz;
    if ($pos % 2) $pos++; // padding
  }
  if (!$fmt || $dataOff===null) throw new RuntimeException("WAV missing fmt/data");
  $pcm = substr($bytes, $dataOff, $dataLen);
  return [$fmt, $pcm];
}

// Build a WAV blob from raw PCM + fmt.
function wav_encode(array $fmt, string $pcm): string {
  $dataLen = strlen($pcm);
  $fmtBin  = pack('vvVVvv',
    $fmt['AudioFormat'], $fmt['NumChannels'], $fmt['SampleRate'],
    $fmt['ByteRate'], $fmt['BlockAlign'], $fmt['BitsPerSample']
  );
  $chunks  = "fmt " . pack('V', 16) . $fmtBin;
  $chunks .= "data" . pack('V', $dataLen) . $pcm;
  $riffSz  = 4 + strlen($chunks);
  return "RIFF" . pack('V',$riffSz) . "WAVE" . $chunks;
}

// Convert SSML timepoints to PCM byte ranges (exact sample math).
function timepoints_to_pcm_slices(array $timepoints, int $sampleRate, int $blockAlign, float $trailingBreakSec, int $totalPcmBytes): array {
  $n = count($timepoints);
  $slices = [];
  for ($i=0; $i<$n; $i++) {
    $tStart = (float)$timepoints[$i]['timeSeconds'];
    $tEnd   = ($i+1 < $n) ? (float)$timepoints[$i+1]['timeSeconds'] : ($tStart + $trailingBreakSec);
    $sStart = (int)floor($tStart * $sampleRate);
    $sEnd   = (int)ceil($tEnd   * $sampleRate);
    $bStart = $sStart * $blockAlign;
    $bEnd   = min($totalPcmBytes, $sEnd * $blockAlign);
    if ($bEnd < $bStart) $bEnd = $bStart;
    $slices[] = [$bStart, $bEnd];
  }
  return $slices;
}

// Build batches of item indexes so each SSML stays under MAX_SSML_BYTES
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

// ---- Prepare reusable GAP WAV once ----
$gapSSML = "<speak><break time='{$PAIR_GAP_MS}ms'/></speak>";
[$gapBytes,] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $gapSSML, $SAMPLE_RATE_HZ, false);
[$gapFmt, $gapPcm] = wav_decode($gapBytes);

$finalPcm = '';
$finalFmt = $gapFmt; // will validate against blob fmts

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
    [$wav1, $marks1] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $ssml1, $SAMPLE_RATE_HZ, true);
    [$wav2, $marks2] = synth_blob_with_fallback($VOICE_PREFS[$tgtKey], $GOOGLE_API_KEY, $ssml2, $SAMPLE_RATE_HZ, true);
  } catch (Throwable $e) {
    fail('Synthesis failed: ' . $e->getMessage());
  }

  if (empty($marks1) || empty($marks2)) {
    @file_put_contents(__DIR__.'/log_batched.txt', "[".date('c')."] No timepoints returned (mp2)\nSSML1 len=".strlen($ssml1).", SSML2 len=".strlen($ssml2)."\n", FILE_APPEND);
    fail('No timepoints returned by Google TTS');
  }

  // Decode WAV headers → PCM
  [$fmt1, $pcm1] = wav_decode($wav1);
  [$fmt2, $pcm2] = wav_decode($wav2);

  // Basic consistency check (channels, sample rate, bits)
  foreach (['NumChannels','SampleRate','BitsPerSample'] as $k) {
    if ($fmt1[$k] !== $fmt2[$k] || $fmt1[$k] !== $gapFmt[$k]) {
      fail("WAV format mismatch on $k");
    }
  }
  $finalFmt = $fmt1; // store for final WAV encoding

  $sampleRate = $fmt1['SampleRate'];
  $blockAlign = $fmt1['BlockAlign'];
  $trailSec   = $ITEM_BREAK_MS / 1000.0;

  $t1_total = (float)end($marks1)['timeSeconds'] + $trailSec;
  $t2_total = (float)end($marks2)['timeSeconds'] + $trailSec;

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

  // Compute precise slices
  $slices1 = timepoints_to_pcm_slices($tp1, $sampleRate, $blockAlign, $trailSec, strlen($pcm1));
  $slices2 = timepoints_to_pcm_slices($tp2, $sampleRate, $blockAlign, $trailSec, strlen($pcm2));

  // Append bilingual pairs in PCM
  for ($j = 0; $j < count($batch); $j++) {
    [$b1s, $b1e] = $slices1[$j];
    [$b2s, $b2e] = $slices2[$j];
    $seg1 = substr($pcm1, $b1s, max(0, $b1e - $b1s));
    $seg2 = substr($pcm2, $b2s, max(0, $b2e - $b2s));
    $finalPcm .= $seg1 . $gapPcm . $seg2 . $gapPcm; // order: COL1 → gap → COL2 → gap
  }
}

// ---- Save final WAV ----
$outDir  = __DIR__ . '/cache';
if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
if (!is_writable($outDir)) { fail('cache/ directory is not writable.'); }

$finalWav = wav_encode($finalFmt, $finalPcm);
$outPath  = $outDir . '/' . $table . '.wav';
file_put_contents($outPath, $finalWav);

// ---- Redirect ----
header('Location: main.php?table=' . urlencode($table));
exit;
