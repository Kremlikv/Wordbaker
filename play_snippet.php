<?php
// Parameters
$table = $_GET['table'] ?? '';
$row = $_GET['row'] ?? '';
$side = $_GET['side'] ?? '';

if (!$table || !$row || !in_array($side, ['A', 'B'])) {
    http_response_code(400);
    echo "Missing or invalid parameters.";
    exit;
}

$filename = "cache/" . basename($table) . "/word_" . str_pad($row, 3, '0', STR_PAD_LEFT) . $side . ".mp3";

if (!file_exists($filename)) {
    http_response_code(404);
    echo "Audio file not found.";
    exit;
}

header('Content-Type: audio/mpeg');
readfile($filename);
exit;
