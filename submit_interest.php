<?php
// submit_interest.php — SMTP version using PHPMailer, with dual-port retry + debug.
// Place PHPMailer files at /lib/PHPMailer/src/{PHPMailer.php,SMTP.php,Exception.php}

mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';

// ---------------- CONFIG ----------------
$TO_EMAIL  = 'kremlik@seznam.cz';       // your inbox
$SITE_NAME = 'WordBaker';
$FROM_NAME = 'WordBaker Notifications';

// SMTP defaults (Brevo)
$SMTP_HOST = 'smtp-relay.brevo.com';

// Pull secrets from config.php (strings!)
$SMTP_USER = $BREVO_USER ?? '';
$SMTP_PASS = $BREVO_PASS ?? '';
$FROM_EMAIL = $FROM_EMAIL ?? 'no-reply@wordbaker.cz';

// Debug flag (optional, set in config.php)
$SMTP_DEBUG = isset($SMTP_DEBUG) ? (bool)$SMTP_DEBUG : false;

// -----------------------------------------------

// Load PHPMailer classes (no Composer)
require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper: safe read + trim
function read_field($name, $default = '') {
  return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo '<!doctype html><meta charset="utf-8"><p>Method Not Allowed.</p>';
  exit;
}

// Collect fields from the Classes form
$name       = read_field('name');
$contact    = read_field('contact'); // email or phone
$level      = read_field('level');
$start_date = read_field('start_date');
$notes      = read_field('notes');

$days       = isset($_POST['days'])  && is_array($_POST['days'])  ? $_POST['days']  : [];
$times      = isset($_POST['times']) && is_array($_POST['times']) ? $_POST['times'] : [];

// Validate
$errors = [];
if ($name === '') $errors[] = 'Name is required.';
if ($contact === '') {
  $errors[] = 'Contact (email or phone) is required.';
} else {
  if (strpos($contact, '@') !== false) {
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
  } else {
    if (!preg_match('/^[0-9+\-\s()]{6,}$/', $contact)) $errors[] = 'Please provide a valid phone number.';
  }
}
$allowed_levels = ['A1','A2','B1','B2','C1','C2'];
if ($level === '' || !in_array($level, $allowed_levels, true)) {
  $errors[] = 'Please choose a valid level.';
}
if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
  $errors[] = 'Invalid start date format.';
}

if ($errors) {
  http_response_code(422);
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Submission Error</title>';
  echo '<style>body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px;} .card{max-width:720px;margin:auto;padding:18px;border:1px solid #ddd;border-radius:12px;background:#fff;} ul{margin:0 0 12px 20px;} a.btn{display:inline-block;margin-top:8px;padding:10px 14px;border-radius:999px;background:#333;color:#fff;text-decoration:none;}</style>';
  echo '</head><body><div class="card"><h2>There were problems with your submission</h2><ul>';
  foreach ($errors as $e) echo '<li>'.htmlspecialchars($e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
  echo '</ul><a class="btn" href="javascript:history.back()">Go back</a></div></body></html>';
  exit;
}

// Normalize day/times
$day_order  = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$selected_days = [];
foreach ($day_order as $d) {
  if (in_array($d, $days, true)) {
    $t = isset($times[$d]) ? trim((string)$times[$d]) : '';
    $selected_days[] = $d . ($t !== '' ? ' — ' . $t : '');
  }
}

// Build message
$submitted_at = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$msg_lines = [
  "New class enquiry ($SITE_NAME)",
  "Submitted: $submitted_at",
  "IP: $ip",
  "",
  "Name: $name",
  "Contact: $contact",
  "Level: $level",
  "Available from: " . ($start_date !== '' ? $start_date : '—'),
  "Days & times:",
  $selected_days ? ('  - ' . implode(PHP_EOL . '  - ', $selected_days)) : '  - —',
  "",
  "Notes:",
  $notes !== '' ? $notes : '—',
];
$message_text = implode(PHP_EOL, $msg_lines);

// -------- PHPMailer with dual-port retry + debug capture --------
$mail_ok = false;
$mail_error = null;

// Capture SMTP debug output into an array (only if $SMTP_DEBUG)
$smtp_debug_log = [];
$debugCapture = function($str) use (&$smtp_debug_log) {
  // Strip trailing newlines for neater output
  $smtp_debug_log[] = rtrim($str, "\r\n");
};

// Two attempts: STARTTLS:587 then SMTPS:465
$attempts = [
  ['label' => 'STARTTLS:587', 'secure' => PHPMailer::ENCRYPTION_STARTTLS, 'port' => 587],
  ['label' => 'SMTPS:465',    'secure' => PHPMailer::ENCRYPTION_SMTPS,   'port' => 465],
];

foreach ($attempts as $try) {
  $mail = new PHPMailer(true);
  try {
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;      // e.g., 95e...@smtp-brevo.com
    $mail->Password   = $SMTP_PASS;      // xkeysib-...
    $mail->SMTPSecure = $try['secure'];
    $mail->Port       = $try['port'];
    $mail->SMTPAutoTLS = true;           // safe default
    $mail->Timeout    = 15;              // seconds

    if ($SMTP_DEBUG) {
      $mail->SMTPDebug  = 2;             // show client+server dialogue
      $mail->Debugoutput = $debugCapture; // capture to array
    }

    // Sender & recipients
    $mail->setFrom($FROM_EMAIL, $FROM_NAME);
    $mail->addAddress($TO_EMAIL);
    if (strpos($contact, '@') !== false && filter_var($contact, FILTER_VALIDATE_EMAIL)) {
      $mail->addReplyTo($contact, $name);
    }

    $mail->Subject = "[$SITE_NAME] New class enquiry from $name";
    $mail->Body    = $message_text;
    $mail->AltBody = $message_text;
    $mail->isHTML(false);

    // Try to send
    if ($mail->send()) {
      $mail_ok = true;
      $mail_error = null;
      break; // success, stop trying
    }
  } catch (Exception $e) {
    // Keep the last error; loop will try next port
    $mail_error = "Attempt {$try['label']} failed: " . ($mail->ErrorInfo ?: $e->getMessage());
    // Close connection before next attempt
    if (method_exists($mail, 'smtpClose')) {
      $mail->smtpClose();
    }
  }
}

// CSV backup
$dir = __DIR__ . '/data';
$file = $dir . '/interest_submissions.csv';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

$csv_row = [
  $submitted_at,
  $ip,
  $name,
  $contact,
  $level,
  $start_date,
  implode(' | ', $selected_days),
  preg_replace('/\s+/', ' ', trim($notes))
];

$csv_ok = false;
if ($fp = @fopen($file, 'ab')) {
  if (flock($fp, LOCK_EX)) {
    if (0 === filesize($file)) {
      fputcsv($fp, ['submitted_at','ip','name','contact','level','available_from','days_times','notes']);
    }
    fputcsv($fp, $csv_row);
    fflush($fp);
    flock($fp, LOCK_UN);
    $csv_ok = true;
  }
  fclose($fp);
}

// Thank-you page
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Thank you</title>';
echo '<style>
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px;background:#f7f7f7;}
  .card{max-width:820px;margin:24px auto;padding:22px;border:2px solid #000;border-radius:14px;background:#fff;}
  h1{margin:0 0 10px;}
  .muted{color:#666;}
  .grid{display:grid;grid-template-columns:160px 1fr;gap:10px 16px;}
  .grid div{padding:4px 0;}
  .btn{display:inline-block;margin-top:14px;padding:10px 14px;border-radius:999px;background:#333;color:#fff;text-decoration:none;}
  pre.log{white-space:pre-wrap;background:#f5f5f5;border:1px solid #ddd;border-radius:8px;padding:10px;margin-top:10px;max-height:320px;overflow:auto;}
  @media (max-width:560px){ .grid{grid-template-columns:1fr;} .grid div{padding:6px 0;} }
</style>';
echo '</head><body><div class="card">';
echo '<h1>Thank you! ✅</h1><p class="muted">Your application has been sent.</p>';

echo '<div class="grid">';
echo '<div><strong>Name</strong></div><div>'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div>';
echo '<div><strong>Contact</strong></div><div>'.htmlspecialchars($contact, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div>';
echo '<div><strong>Level</strong></div><div>'.htmlspecialchars($level, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div>';
echo '<div><strong>Available from</strong></div><div>'.($start_date ? htmlspecialchars($start_date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '—').'</div>';
$days_html = $selected_days ? htmlspecialchars(implode(', ', $selected_days), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '—';
echo '<div><strong>Days & times</strong></div><div>'.$days_html.'</div>';
$notes_html = $notes !== '' ? nl2br(htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : '—';
echo '<div><strong>Notes</strong></div><div>'.$notes_html.'</div>';
echo '</div>';

if (!$mail_ok) {
  echo '<p class="muted">Note: email sending failed';
  if ($mail_error) echo ' — '.htmlspecialchars($mail_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  echo '. Your submission was saved to our internal log.</p>';

  // If debugging is enabled, show the SMTP transcript
  if ($SMTP_DEBUG && !empty($smtp_debug_log)) {
    echo '<details open><summary><strong>SMTP debug log</strong></summary>';
    echo '<pre class="log">'.htmlspecialchars(implode("\n", $smtp_debug_log), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre>';
    echo '</details>';
  }
}
if (!$csv_ok) {
  echo '<p class="muted">Note: failed to write CSV backup. (Check folder permissions.)</p>';
}

echo '<a class="btn" href="index.php">Back to homepage</a>';
echo '</div></body></html>';
