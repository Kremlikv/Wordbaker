<?php
// ask_from_text.php — WordBaker add-on (DB-driven, MySQLi + config.php/ conf.php + env fallback)
// Purpose: Read scanned text and/or pull a bilingual table directly from MySQL (no JSON),
// generate 6 comprehension questions with AI, ask them one by one, check correctness,
// and suggest grammar/spelling improvements.
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');
session_start();

// ========================= CONFIG LOAD ORDER ========================= //
// 1) config.php (preferred, gitignored in your app)
// 2) conf.php   (alternate name)
// 3) getenv()   (hosting panel env vars)
$__cfg = null;
if (file_exists(__DIR__ . '/config.php')) {
  $__cfg = __DIR__ . '/config.php';
  include $__cfg; // may define $conn, $DB_*, OPENROUTER_KEY/OPENAI_KEY, AI_PROVIDER, etc.
} elseif (file_exists(__DIR__ . '/conf.php')) {
  $__cfg = __DIR__ . '/conf.php';
  include $__cfg;
}

// Helper to fetch config with precedence: var -> CONST -> env -> default
function cfg_val($varSet, $constName, $envName, $default=null) {
  if ($varSet !== null && $varSet !== '') return $varSet;
  if ($constName && defined($constName)) return constant($constName);
  $e = ($envName !== null) ? getenv($envName) : false;
  if ($e !== false && $e !== '') return $e;
  return $default;
}

// ========================= DB (MySQLi) ========================= //
// If config.php/conf.php already created $conn (mysqli), we reuse it.
if (!isset($conn) || !$conn) {
  // Accept variables OR constants OR env vars
  $DB_HOST = cfg_val(isset($DB_HOST)?$DB_HOST:null, 'DB_HOST', 'DB_HOST', 'mysql-victork.alwaysdata.net');
  $DB_NAME = cfg_val(isset($DB_NAME)?$DB_NAME:null, 'DB_NAME', 'DB_NAME', 'victork_database1');
  $DB_USER = cfg_val(isset($DB_USER)?$DB_USER:null, 'DB_USER', 'DB_USER', 'victork');
  $DB_PASS = cfg_val(isset($DB_PASS)?$DB_PASS:null, 'DB_PASS', 'DB_PASS', '');
  $conn = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  if (!$conn) {
    http_response_code(500);
    echo '<h1>DB connection failed</h1><pre>' . htmlspecialchars(mysqli_connect_error()) . '</pre>';
    exit;
  }
}

// ========================= AI SETTINGS ========================= //
$AI_PROVIDER      = cfg_val(isset($AI_PROVIDER)?$AI_PROVIDER:null,      'AI_PROVIDER',      'AI_PROVIDER',      'openrouter');
$OPENROUTER_KEY   = cfg_val(isset($OPENROUTER_KEY)?$OPENROUTER_KEY:null,'OPENROUTER_KEY',   'OPENROUTER_API_KEY','');
$OPENAI_KEY       = cfg_val(isset($OPENAI_KEY)?$OPENAI_KEY:null,        'OPENAI_KEY',       'OPENAI_API_KEY',   '');
$MODEL_OPENROUTER = cfg_val(isset($MODEL_OPENROUTER)?$MODEL_OPENROUTER:null, 'MODEL_OPENROUTER','AI_MODEL_OPENROUTER','anthropic/claude-3-haiku');
$MODEL_OPENAI     = cfg_val(isset($MODEL_OPENAI)?$MODEL_OPENAI:null,        'MODEL_OPENAI',     'AI_MODEL_OPENAI',   'gpt-4o-mini');
$SIMULATE_MODE    = (bool) cfg_val(isset($SIMULATE_MODE)?$SIMULATE_MODE:null, 'SIMULATE_MODE','SIMULATE_MODE', false);

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
      $folderData['My'][] = [ 'table' => $t, 'display' => 'my/_/' . $t ];
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