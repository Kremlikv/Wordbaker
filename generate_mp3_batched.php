<?php
/**
 * generate_mp3_batched.php
 *
 * Bilingual TTS with **few requests** using Google Cloud TTS free tier.
 * - Reads table/columns from session (same as your current script)
 * - Supports CS / EN / DE / FR / IT / ES
 * - Batches many rows into two SSML blobs (one per language) with <mark/>s
 * - Requests timepoints and slices MP3 by time → bytes (constant format)
 * - Assembles CZ→EN (or generally COL1→COL2) with reusable gap MP3
 * - Saves to cache/<table>.mp3 then redirects back to main.php
 */

session_start();
require_once 'db.php';
require_once __DIR__ . '/config.php'; // expects $GOOGLE_API_KEY

// ------------- Config -------------
$SAMPLE_RATE_HZ = 22050;       // Fix output format for consistent slicing
$ITEM_BREAK_MS  = 300;         // pause after each item inside monolingual blobs
$PAIR_GAP_MS    = 400;         // gap between L1 and L2 in final bilingual stream
$MAX_SSML_BYTES = 4600;        // keep buffer below Google's ~5000 bytes limit
$BATCH_MIN      = 20;          // minimum items per batch (we'll fill until size limit)

// Preferred voices (first wins; we auto-fallback to later if a call fails)
$VOICE_PREFS = [
  'czech'   => [ ['name' => 'cs-CZ-Wavenet-A', 'code' => 'cs-CZ'], ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'] ],
  'english' => [ ['name' => 'en-GB-Wavenet-B', 'code' => 'en-GB'], ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'] ],
  'german'  => [ ['name' => 'de-DE-Wavenet-B', 'code' => 'de-DE'], ['name' => 'de-DE-Standard-H', 'code' => 'de-DE'] ],
  'french'  => [ ['name' => 'fr-FR-Wavenet-C', 'code' => 'fr-FR'], ['name' => 'fr-FR-Standard-G', 'code' => 'fr-FR'] ],
  'italian' => [ ['name' => 'it-IT-Wavenet-C', 'code' => 'it-IT'], ['name' => 'it-IT-Standard-F', 'code' => 'it-IT'] ],
  'spanish' => [ ['name' => 'es-ES-Wavenet-A', 'code' => 'es-ES'], ['name' => 'es-ES-Standard-G', 'code' => 'es-ES'] ],
];

// ------------- Session / Inputs -------------
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';
if ($table === '' || $col1 === '' || $col2 === '') {
  http_response_code(400);
  die('❌ Missing session info.');
}

$srcKey = strtolower($col1);
$tgtKey = strtolower($col2);

if (!isset($VOICE_PREFS[$srcKey], $VOICE_PREFS[$tgtKey])) {
  http_response_code(400);
  die("❌ Unsupported language columns: $col1 / $col2");
}

// ------------- Helpers -------------
function ssml_escape(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Call Google TTS; returns [bytes, timepoints[]] */
function tts_google_mp3(string $apiKey, string $voiceName, string $langCode, string $ssml, int $sampleRate, bool $wantMarks = false): array {
  $payload = [
    'input'       => ['ssml' => $ssml],
    'voice'       => ['languageCode' => $langCode, 'name' => $voiceName],
    'audioConfig' => ['audioEncoding' => 'MP3', 'sampleRateHertz' => $sampleRate]
  ];
  if ($wantMarks) {
    $payload['enableTimePointing'] = ['SSML_MARK'];
  }

  $ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=".$apiKey);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload)
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $j = json_decode($res, true);
  if ($code !== 200 || empty($j['audioContent'])) {
    file_put_contents('log_batched.txt', "[".date('c')."] HTTP $code\n$res\n\n", FILE_APPEND);
    throw new RuntimeException("Google TTS failed (HTTP $code)");
  }
  $bytes = base64_decode($j['audioContent']);
  $marks = $j['timepoints'] ?? [];
  return [$bytes, $marks];
}

/** Try voices in order; on failure, fall back. */
function synth_blob_with_fallback(array $voicePrefs, string $apiKey, string $ssml, int $sampleRate, bool $wantMarks): array {
  $lastEx = null;
  foreach ($voicePrefs as $v) {
    try { return tts_google_mp3($apiKey, $v['name'], $v['code'], $ssml, $sampleRate, $wantMarks); }
    catch (Throwable $e) { $lastEx = $e; }
  }
  if ($lastEx) throw $lastEx; else throw new RuntimeException('No voice available.');
}

/**
 * Convert SSML timepoints to byte ranges using an approximate constant bitrate.
 * Returns array of [bStart, bEnd) for each mark index.
 */
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

/** Build batches of item indexes so each SSML stays under MAX_SSML_BYTES */
function build_batches(array $items, int $maxSsmlBytes, int $minPerBatch, int $itemBreakMs): array {
  $batches = [];
  $cur = [];
  $curLen = strlen('<speak></speak>');
  foreach ($items as $idx => $pair) {
    [$a, $b] = $pair; // strings
    // Rough encoding size estimate per item in SSML with mark + break
    $piece = strlen("<mark name='m$idx'/>" . ssml_escape($a) . "<break time='{$itemBreakMs}ms'/>");
    if (!empty($cur) && ($curLen + $piece) > $maxSsmlBytes && count($cur) >= $minPerBatch) {
      $batches[] = $cur; $cur = []; $curLen = strlen('<speak></speak>');
    }
    $cur[] = $idx; $curLen += $piece;
  }
  if (!empty($cur)) $batches[] = $cur;
  return $batches;
}

// ------------- Load data -------------
$conn->set_charset('utf8mb4');
$tableEsc = str_replace('`','``',$table);
$col1Esc  = str_replace('`','``',$col1);
$col2Esc  = str_replace('`','``',$col2);
$sql = "SELECT `{$col1Esc}` AS c1, `{$col2Esc}` AS c2 FROM `{$tableEsc}`";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) { die('❌ No data found in table.'); }
$rows = [];
while ($r = $result->fetch_assoc()) {
  $a = trim((string)$r['c1']);
  $b = trim((string)$r['c2']);
  if ($a === '' || $b === '') continue;
  $rows[] = [$a, $b];
}
$result->free();
$conn->close();
if (empty($rows)) { die('❌ No usable rows (empty values).'); }

// ------------- Build batches -------------
$indexes = range(0, count($rows)-1);
$batches = build_batches($rows, $MAX_SSML_BYTES, $BATCH_MIN, $ITEM_BREAK_MS);

// ------------- Prepare gap MP3 once -------------
$gapSSML = "<speak><break time='{$PAIR_GAP_MS}ms'/></speak>";
[$gapBytes,] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $gapSSML, $SAMPLE_RATE_HZ, false);

$final = '';

// ------------- Process each batch -------------
foreach ($batches as $batch) {
  // Build two SSML blobs: L1 (col1) and L2 (col2)
  $ssml1 = '<speak>';
  $ssml2 = '<speak>';
  foreach ($batch as $i) {
    $ssml1 .= "<mark name='m{$i}'/>" . ssml_escape($rows[$i][0]) . "<break time='{$ITEM_BREAK_MS}ms'/>";
    $ssml2 .= "<mark name='m{$i}'/>" . ssml_escape($rows[$i][1]) . "<break time='{$ITEM_BREAK_MS}ms'/>";
  }
  $ssml1 .= '</speak>';
  $ssml2 .= '</speak>';

  // Synthesize both languages with marks (fallback if needed)
  [$bytes1, $marks1] = synth_blob_with_fallback($VOICE_PREFS[$srcKey], $GOOGLE_API_KEY, $ssml1, $SAMPLE_RATE_HZ, true);
  [$bytes2, $marks2] = synth_blob_with_fallback($VOICE_PREFS[$tgtKey], $GOOGLE_API_KEY, $ssml2, $SAMPLE_RATE_HZ, true);

  // Compute total times (last mark time + trailing break)
  $t1_total = 0.0; if (!empty($marks1)) $t1_total = (float)end($marks1)['timeSeconds'] + ($ITEM_BREAK_MS/1000.0);
  $t2_total = 0.0; if (!empty($marks2)) $t2_total = (float)end($marks2)['timeSeconds'] + ($ITEM_BREAK_MS/1000.0);
  if ($t1_total <= 0.0 || $t2_total <= 0.0) { throw new RuntimeException('No timepoints returned.'); }

  // Build mark maps by original index
  $map1 = []; foreach ($marks1 as $m) { $map1[$m['markName']] = (float)$m['timeSeconds']; }
  $map2 = []; foreach ($marks2 as $m) { $map2[$m['markName']] = (float)$m['timeSeconds']; }

  // Reconstruct ordered arrays aligned to $batch
  $tp1 = []; $tp2 = [];
  foreach ($batch as $i) {
    $k = 'm'.$i;
    if (!isset($map1[$k], $map2[$k])) {
      throw new RuntimeException('Missing timepoint for index '.$i);
    }
    $tp1[] = ['markName'=>$k, 'timeSeconds'=>$map1[$k]];
    $tp2[] = ['markName'=>$k, 'timeSeconds'=>$map2[$k]];
  }

  // Convert timepoints → byte slices
  $slices1 = timepoints_to_slices($tp1, strlen($bytes1), $t1_total);
  $slices2 = timepoints_to_slices($tp2, strlen($bytes2), $t2_total);

  // Assemble bilingual sequence for this batch
  for ($j = 0; $j < count($batch); $j++) {
    [$b1s, $b1e] = $slices1[$j];
    [$b2s, $b2e] = $slices2[$j];
    $seg1 = substr($bytes1, $b1s, $b1e - $b1s);
    $seg2 = substr($bytes2, $b2s, $b2e - $b2s);
    $final .= $seg1 . $gapBytes . $seg2 . $gapBytes; // order: COL1 → gap → COL2 → gap
  }
}

// ------------- Save & redirect -------------
$outPath = __DIR__ . '/cache/' . $table . '.mp3';
if (!is_dir(dirname($outPath))) { @mkdir(dirname($outPath), 0775, true); }
file_put_contents($outPath, $final);
header('Location: main.php?table=' . urlencode($table));
exit;
