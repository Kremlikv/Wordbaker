<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/config.php';

$GOOGLE_API_KEY = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU';  // Replace with your real key

$table = $_SESSION['table'] ?? '';
$col1 = $_SESSION['col1'] ?? '';
$col2 = $_SESSION['col2'] ?? '';
if (!$table || !$col1 || !$col2) {
    die("❌ Missing session info.");
}

$voices = [
    'czech'   => ['name' => 'cs-CZ-Standard-B', 'code' => 'cs-CZ'],
    'english' => ['name' => 'en-GB-Standard-O', 'code' => 'en-GB'],
    'german'  => ['name' => 'de-DE-Standard-H', 'code' => 'de-DE']
];

$srcLang = strtolower($col1);
$tgtLang = strtolower($col2);
if (!isset($voices[$srcLang], $voices[$tgtLang])) {
    die("❌ Unsupported language columns: $col1 / $col2");
}

$srcVoice = $voices[$srcLang];
$tgtVoice = $voices[$tgtLang];

// Fetch rows
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if (!$result || $result->num_rows === 0) {
    die("❌ No data found in table.");
}
$conn->close();

// Build SSML chunks
$chunks = [];
$current = "<speak>\n";
$limit = 4900;  // Stay below 5000 bytes for safety

while ($row = $result->fetch_assoc()) {
    $srcText = trim($row[$col1]);
    $tgtText = trim($row[$col2]);
    if ($srcText === '' || $tgtText === '') continue;

    $entry  = '<voice name="' . $srcVoice['name'] . '">' . htmlspecialchars($srcText) . ".</voice>\n";
    $entry .= '<break time="2s"/>' . "\n";
    $entry .= '<voice name="' . $tgtVoice['name'] . '">' . htmlspecialchars($tgtText) . ".</voice>\n";
    $entry .= '<break time="2s"/>' . "\n";

    if (strlen($current . $entry . "</speak>") > $limit) {
        $chunks[] = $current . "</speak>";
        $current = "<speak>\n" . $entry;
    } else {
        $current .= $entry;
    }
}
if (trim($current) !== "<speak>") {
    $chunks[] = $current . "</speak>";
}

// Send each SSML chunk and collect audio
$finalAudio = '';
foreach ($chunks as $ssml) {
    $payload = [
        'input' => ['ssml' => $ssml],
        'voice' => [
            'languageCode' => $srcVoice['code'],
            'name' => $srcVoice['name']
        ],
        'audioConfig' => ['audioEncoding' => 'MP3']
    ];

    $ch = curl_init("https://texttospeech.googleapis.com/v1/text:synthesize?key=$GOOGLE_API_KEY");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !isset($data['audioContent'])) {
        file_put_contents("log_ssml.txt", "❌ Failed\nHTTP: $httpCode\n$response\n", FILE_APPEND);
        die("❌ Google TTS failed. See log_ssml.txt");
    }

    $finalAudio .= base64_decode($data['audioContent']);  // Append MP3 bytes
}

// Save final audio
file_put_contents("cache/$table.mp3", $finalAudio);
header("Location: main.php?table=" . urlencode($table));
exit;
