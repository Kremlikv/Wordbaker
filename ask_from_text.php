<?php
// ask_from_text.php — WordBaker add-on (DB-driven, MySQLi + conf.php)
// Purpose: Read scanned text and/or pull a bilingual table directly from MySQL (no JSON),
// generate 6 comprehension questions with AI, ask them one by one, check correctness,
// and suggest grammar/spelling improvements.
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');
session_start();

// ========================= CONFIG ========================= //
// Prefer conf.php (gitignored) for keys and DB connection
if (file_exists(__DIR__ . '/config.php')) {
  include __DIR__ . '/config.php'; // may define $conn (mysqli) and AI vars
}

// Build MySQLi connection if conf.php didn't set it
if (!isset($conn) || !$conn) {
  $DB_HOST = isset($DB_HOST) ? $DB_HOST : (getenv('DB_HOST') ?: 'mysql-victork.alwaysdata.net');
  $DB_NAME = isset($DB_NAME) ? $DB_NAME : (getenv('DB_NAME') ?: 'victork_database1');
  $DB_USER = isset($DB_USER) ? $DB_USER : (getenv('DB_USER') ?: 'victork');
  $DB_PASS = isset($DB_PASS) ? $DB_PASS : (getenv('DB_PASS') ?: '');
  $conn = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  if (!$conn) {
    http_response_code(500);
    echo '<h1>DB connection failed</h1><pre>' . htmlspecialchars(mysqli_connect_error()) . '</pre>';
    exit;
  }
}

// AI provider settings (prefer conf.php values)
$AI_PROVIDER      = isset($AI_PROVIDER) ? $AI_PROVIDER : (getenv('AI_PROVIDER') ?: 'openrouter');
$OPENROUTER_KEY   = isset($OPENROUTER_KEY) ? $OPENROUTER_KEY : (getenv('OPENROUTER_API_KEY') ?: '');
$OPENAI_KEY       = isset($OPENAI_KEY) ? $OPENAI_KEY : (getenv('OPENAI_API_KEY') ?: '');
$MODEL_OPENROUTER = isset($MODEL_OPENROUTER) ? $MODEL_OPENROUTER : (getenv('AI_MODEL_OPENROUTER') ?: 'anthropic/claude-3-haiku');
$MODEL_OPENAI     = isset($MODEL_OPENAI) ? $MODEL_OPENAI : (getenv('AI_MODEL_OPENAI') ?: 'gpt-4o-mini');
$SIMULATE_MODE    = isset($SIMULATE_MODE) ? (bool)$SIMULATE_MODE : (bool)filter_var(getenv('SIMULATE_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN);

// ========= File Explorer integration (folders + tables) ========= //
// If another controller already prepares $folders and $folderData, we reuse it.
// Otherwise, we build a very simple default: one root folder "My" with all tables.
if (!isset($folders) || !isset($folderData)) {
  $folders = ['My' => []];
  $folderData = ['My' => []];
  try {
    $tables = [];
    if ($res = mysqli_query($conn, 'SHOW TABLES')) {
      while ($row = mysqli_fetch_row($res)) { $tables[] = $row[0]; }
      mysqli_free_result($res);
    }
    foreach ($tables as $t) {
      $folderData['My'][] = [
        'table' => $t,
        'display' => 'my/_/' . $t
      ];
    }
  } catch (Throwable $e) { /* ignore */ }
}

// Selected table + columns from POST (set by explorer)
$selectedFullTable = isset($_POST['table']) ? trim($_POST['table']) : ($_SESSION['selected_table'] ?? '');
$column1 = isset($_POST['col1']) ? trim($_POST['col1']) : ($_SESSION['col1'] ?? 'czech');
$column2 = isset($_POST['col2']) ? trim($_POST['col2']) : ($_SESSION['col2'] ?? 'english');
if ($selectedFullTable) {
  $_SESSION['selected_table'] = $selectedFullTable;
  $_SESSION['col1'] = $column1;
  $_SESSION['col2'] = $column2;
}

// ========================================================= //

function json_response($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function sanitize_text($s) {
  $s = preg_replace('/[