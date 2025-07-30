<?php
session_start();
require_once 'db.php';

// Google Cloud TTS API Key
$apiKey = 'AIzaSyCTj5ksARALCyr7tXmQhgJBx8_tvgT76xU'; // Replace with your actual key

// Get table and columns from session
$table = $_SESSION['table'] ?? '';
$col1 = $_SESSION['col1'] ?? '';
$col2 = $_SESSION['col2'] ?? '';

if (!$table || !$col1 || !$col2) {
    die("❌ Missing table or column session info.");
}

// Define voice mapping
$voiceMap = [
    'czech' => 'cs-CZ-Standard-B',        // Female Czech
    'english' => 'en-GB-Standard-O',      // Male English (UK)
    'german' => 'de-DE-Wavenet-H'         // Male German
];

// Normalize keys for language lookup
$sourceKey = strtolower($col1);
$targetKey = strtolower($col2);

$sourceVoice = $voiceMap[$sourceKey] ?? null;
$targetVoice = $voiceMap[$targetKey] ?? null;

if (!$sourceVoice || !$targetVoice) {
    die("❌ No voice configured for columns: $col1 / $col2");
}

// Ensure cache/{table}/ exists
$folder = "cache/$table";
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// Google TTS function
function googleTTS($text, $voiceName, $apiKey) {
    $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key=$apiKey";

    $payload = json_encode([
        'input' => [ 'text' => $text ],
        'voice' => [
            'languageCode' => substr($voiceName, 0, 5),
            'name' => $voiceName
        ],
        'audioConfig' => [ 'audioEncoding' => 'MP3' ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json' ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    return base64_decode($data['audioContent'] ?? '');
}

// Fetch and generate individual snippets
$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);

if ($result) {
    $index = 1;
    while ($row = $result->fetch_assoc()) {
        $src = trim($row[$col1]);
        $tgt = trim($row[$col2]);

        if (!$src || !$tgt) continue;

        $srcMp3 = googleTTS($src, $sourceVoice, $apiKey);
        $tgtMp3 = googleTTS($tgt, $targetVoice, $apiKey);

        if ($srcMp3 && $tgtMp3) {
            $filename = sprintf("%s/row_%03d_src.mp3", $folder, $index);
            file_put_contents($filename, $srcMp3);
            $filename2 = sprintf("%s/row_%03d_tgt.mp3", $folder, $index);
            file_put_contents($filename2, $tgtMp3);
        }

        $index++;
    }
}

$conn->close();
header("Location: main.php");
exit;
