<?php
session_start();
require_once 'db.php';

// Load 1-second silence mp3
$silence = file_get_contents('silence.mp3');
if (!$silence) {
    die("Silence file missing.");
}

// ElevenLabs API setup
$api_key = 'sk_3fd1ed62c6431f562064ece5d9e46dbb3e9cdf4b96451734';
$voices = [
    'czech' => 'OAAjJsQDvpg3sVjiLgyl',  // Denisa O
    'english' => 'goT3UYdM9bhm0n2lmKQx', // Edward
    'german' => 'zl7GSCFv2aKISCB2LjZz',  // Wilhelm
];

// Retrieve from session
$table = $_SESSION['table'] ?? '';
$col1  = $_SESSION['col1'] ?? '';
$col2  = $_SESSION['col2'] ?? '';

// DEBUG
// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";

//if (empty($table) || empty($col1) || empty($col2)) {
//    echo "DEBUG info:<br>";
//    echo "Table: $table<br>";
//    echo "Col1: $col1<br>";
//    echo "Col2: $col2<br>";
//    die("❌ Missing table or column names.");
// }

// Normalize for voice selection
$source_key = strtolower($col1);
$target_key = strtolower($col2);

if (!isset($voices[$source_key]) || !isset($voices[$target_key])) {
    die("Voice not configured for columns: $col1 / $col2");
}

$source_voice = $voices[$source_key];
$target_voice = $voices[$target_key];

// ElevenLabs TTS function
function generateTTS($text, $voice_id, $api_key) {
    $url = "https://api.elevenlabs.io/v1/text-to-speech/$voice_id/stream";
    $payload = json_encode([
        'text' => $text,
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.4,
            'similarity_boost' => 0.8
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "xi-api-key: $api_key",
            "Content-Type: application/json",
            "Accept: audio/mpeg"
        ]
    ]);

    $audio = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$audio) {
        file_put_contents("log.txt", "TTS failed for: $text\nHTTP Code: $http_code\nError: $err\n", FILE_APPEND);
        return null;
    }

    return $audio;
}

// Fetch rows and generate audio
$final_audio = "";

$query = "SELECT `$col1`, `$col2` FROM `$table`";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $source_text = trim($row[$col1]);
        $target_text = trim($row[$col2]);

        if ($source_text === "" || $target_text === "") continue;

        $src_audio = generateTTS($source_text, $source_voice, $api_key);
        $tgt_audio = generateTTS($target_text, $target_voice, $api_key);

        if ($src_audio && $tgt_audio) {
            $final_audio .= $src_audio . $silence . $tgt_audio . $silence;
        }
    }
}

$conn->close();

// Output
if ($final_audio === '') {
    die("No audio was generated. Check if the table contains valid data.");
}

file_put_contents("cache/$table.mp3", $final_audio);
header("Location: main.php?table=" . urlencode($_POST['table']));

exit;
