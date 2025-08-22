<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ⛔ Temporarily disable session checking for AJAX fetch
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    // Skip session check logic inside session.php during AJAX
    require_once 'db.php';
    return;
}

require_once 'db.php';
require_once 'session.php';

// Context menu  - right click


/* ========= QUIZ sharing/copy helpers & handlers (post back to play_quiz.php) ========= */

// Ensure share tables exist (harmless if already created elsewhere)
$conn->query("
CREATE TABLE IF NOT EXISTS shared_tables (
  table_name VARCHAR(255) PRIMARY KEY,
  owner      VARCHAR(64)  NOT NULL,
  shared_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$conn->query("
CREATE TABLE IF NOT EXISTS shared_tables_private (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  table_name      VARCHAR(255) NOT NULL,
  owner           VARCHAR(64)  NOT NULL,
  target_username VARCHAR(64)  NULL,
  target_email    VARCHAR(190) NULL,
  shared_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_table_user (table_name, target_username),
  UNIQUE KEY uq_table_email (table_name, target_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

function safeTablePart(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9_]+/u', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}
function tableExists(mysqli $conn, string $t): bool {
    $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
    return $res && $res->num_rows > 0;
}
function share_add(mysqli $conn, string $table, string $owner): void {
    $stmt = $conn->prepare("INSERT IGNORE INTO shared_tables (table_name, owner) VALUES (?, ?)");
    $stmt->bind_param('ss', $table, $owner); $stmt->execute(); $stmt->close();
}
function share_remove(mysqli $conn, string $table): void {
    $stmt = $conn->prepare("DELETE FROM shared_tables WHERE table_name=?");
    $stmt->bind_param('s', $table); $stmt->execute(); $stmt->close();
}
function share_owner(mysqli $conn, string $table): ?string {
    $stmt = $conn->prepare("SELECT owner FROM shared_tables WHERE table_name=?");
    $stmt->bind_param('s', $table); $stmt->execute();
    $res = $stmt->get_result(); $row = $res? $res->fetch_assoc(): null; $stmt->close();
    return $row['owner'] ?? null;
}
// private shares
function share_private_add(mysqli $conn, string $table, string $owner, ?string $username, ?string $email): void {
    if ($username) {
        $stmt = $conn->prepare("INSERT IGNORE INTO shared_tables_private (table_name, owner, target_username) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $table, $owner, $username); $stmt->execute(); $stmt->close();
    }
    if ($email) {
        $stmt = $conn->prepare("INSERT IGNORE INTO shared_tables_private (table_name, owner, target_email) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $table, $owner, $email); $stmt->execute(); $stmt->close();
    }
}
function share_private_remove(mysqli $conn, string $table, ?string $username, ?string $email): void {
    if ($username) {
        $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=? AND target_username=?");
        $stmt->bind_param('ss', $table, $username); $stmt->execute(); $stmt->close();
    }
    if ($email) {
        $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=? AND target_email=?");
        $stmt->bind_param('ss', $table, $email); $stmt->execute(); $stmt->close();
    }
}
function resolve_share_target(mysqli $conn, string $kind, string $value): array {
    $kind = strtolower(trim($kind));
    $value = trim($value);
    if ($kind === 'email') {
        return [null, mb_strtolower($value)];
    }
    // kind=username
    $u = safeTablePart($value);
    // optional: look up email from users table
    $stmt = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param('s', $u);
    $stmt->execute(); $res = $stmt->get_result();
    $email = null; if ($res && ($row = $res->fetch_assoc())) $email = $row['email'] ?? null;
    $stmt->close();
    return [$u ?: null, $email ? mb_strtolower($email) : null];
}

/* ---------- FOLDER actions (left pane) — quiz tables only ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folder_action'])) {
    $me = strtolower($_SESSION['username'] ?? '');
    if (!$me) { header("Location: play_quiz.php"); exit; }
    $action = $_POST['folder_action'];
    $folder = safeTablePart($_POST['folder_old'] ?? '');

    // collect my quiz tables in this folder: quiz_choices_me_folder_*
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while ($res && ($row = $res->fetch_array())) {
        $t = $row[0];
        if (strpos($t, 'quiz_choices_'.$me.'_') === 0) {
            $suffix = substr($t, strlen('quiz_choices_'.$me.'_')); // folder_rest
            $parts = explode('_', $suffix, 2);
            if (count($parts) === 2 && $parts[0] === $folder) $tables[] = $t;
        }
    }

    if ($action === 'share_folder') {
        foreach ($tables as $t) share_add($conn, $t, $me);
        header("Location: play_quiz.php"); exit;
    }
    if ($action === 'unshare_folder') {
        foreach ($tables as $t) if (share_owner($conn, $t) === $me) share_remove($conn, $t);
        header("Location: play_quiz.php"); exit;
    }
    if ($action === 'share_folder_private') {
        $kind = $_POST['share_target_kind'] ?? '';
        $val  = $_POST['share_target_value'] ?? '';
        [$targetU, $targetE] = resolve_share_target($conn, $kind, $val);
        foreach ($tables as $t) share_private_add($conn, $t, $me, $targetU, $targetE);
        header("Location: play_quiz.php"); exit;
    }
    if ($action === 'unshare_folder_private') {
        $kind = $_POST['share_target_kind'] ?? '';
        $val  = $_POST['share_target_value'] ?? '';
        [$targetU, $targetE] = resolve_share_target($conn, $kind, $val);
        foreach ($tables as $t) share_private_remove($conn, $t, $targetU, $targetE);
        header("Location: play_quiz.php"); exit;
    }

    // COPY folder within my quiz namespace:
    if ($action === 'copy_folder_local') {
        $dest = safeTablePart($_POST['dest_folder'] ?? '');
        $overwrite = !empty($_POST['overwrite']);
        if ($dest === '') { header("Location: play_quiz.php"); exit; }
        foreach ($tables as $src) {
            $suffix = substr($src, strlen('quiz_choices_'.$me.'_')); // folder_rest
            [$oldFolder, $rest] = explode('_', $suffix, 2);
            $dst = 'quiz_choices_'.$me.'_'.$dest.'_'.$oldFolder.'_'.$rest;
            $srcEsc = $conn->real_escape_string($src);
            $dstEsc = $conn->real_escape_string($dst);
            if ($overwrite && tableExists($conn, $dst)) $conn->query("DROP TABLE `{$dstEsc}`");
            if (tableExists($conn, $dst)) continue;
            if ($conn->query("CREATE TABLE `{$dstEsc}` LIKE `{$srcEsc}`")) {
                $conn->query("INSERT INTO `{$dstEsc}` SELECT * FROM `{$srcEsc}`");
            }
        }
        header("Location: play_quiz.php"); exit;
    }

    // RENAME folder
    if ($action === 'rename_folder') {
        $new = safeTablePart($_POST['folder_new'] ?? '');
        if ($new === '' || $new === $folder) { header("Location: play_quiz.php"); exit; }
        $pairs = []; $collisions = [];
        foreach ($tables as $src) {
            $suffix = substr($src, strlen('quiz_choices_'.$me.'_')); // folder_rest
            [$oldFolder, $rest] = explode('_', $suffix, 2);
            $dst = 'quiz_choices_'.$me.'_'.$new.'_'.$rest;
            if (tableExists($conn, $dst)) $collisions[] = $dst; else $pairs[] = ['src'=>$src,'dst'=>$dst];
        }
        if (empty($collisions) && !empty($pairs)) {
            $chunks = [];
            foreach ($pairs as $p) $chunks[] = "`".$conn->real_escape_string($p['src'])."` TO `".$conn->real_escape_string($p['dst'])."`";
            if ($conn->query("RENAME TABLE ".implode(', ', $chunks))) {
                // update public shares you own
                $stmt = $conn->prepare("UPDATE shared_tables SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $me); $stmt->execute(); }
                $stmt->close();
                // update private shares you own
                $stmt = $conn->prepare("UPDATE shared_tables_private SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $me); $stmt->execute(); }
                $stmt->close();
            }
        }
        header("Location: play_quiz.php"); exit;
    }

    // DELETE folder
    if ($action === 'delete_folder') {
        $confirm = safeTablePart($_POST['confirm_text'] ?? '');
        if ($confirm === $folder) {
            foreach ($tables as $t) {
                $tEsc = $conn->real_escape_string($t);
                $conn->query("DROP TABLE `{$tEsc}`");
                share_remove($conn, $t);
                // also remove private shares
                $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=?");
                $stmt->bind_param('s', $t); $stmt->execute(); $stmt->close();
            }
        }
        header("Location: play_quiz.php"); exit;
    }
}

/* ---------- SUBFOLDER actions (right pane) — quiz tables only ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_action'])) {
    $me = strtolower($_SESSION['username'] ?? '');
    if (!$me) { header("Location: play_quiz.php"); exit; }

    $root = safeTablePart($_POST['root_folder'] ?? '');   // top-level left-pane folder (or 'Shared')
    $sub  = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? ''); // tree subpath (underscore-delimited)
    $act  = $_POST['sub_action'];

    if ($act === 'share_subfolder' || $act === 'unshare_subfolder'
        || $act === 'share_subfolder_private' || $act === 'unshare_subfolder_private') {

        // Determine prefix of tables in this subfolder.
        if ($root === 'Shared') {
            // Shared path format: owner_root_sub...
            $parts = array_values(array_filter(explode('_', $sub)));
            if (count($parts) < 2) { header("Location: play_quiz.php"); exit; }
            $srcOwner = strtolower($parts[0]);
            $srcRoot  = $parts[1];
            $srcAfter = (count($parts) > 2) ? implode('_', array_slice($parts, 2)) : '';
            $prefix = $srcOwner.'_'.$srcRoot.($srcAfter ? '_'.$srcAfter : '').'_';
        } else {
            $prefix = $me.'_'.$root.'_'.$sub.'_';
        }

        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            // Only quiz tables
            if (strpos($t, 'quiz_choices_') !== 0) continue;
            $nameAfter = substr($t, strlen('quiz_choices_')); // me_root_sub_file...
            if (strpos($nameAfter, $prefix) !== 0) continue;

            if ($act === 'share_subfolder') {
                if (stripos($t, 'quiz_choices_'.$me.'_') === 0) share_add($conn, $t, $me);
            } elseif ($act === 'unshare_subfolder') {
                if (share_owner($conn, $t) === $me) share_remove($conn, $t);
            } elseif ($act === 'share_subfolder_private') {
                $kind = $_POST['share_target_kind'] ?? '';
                $val  = $_POST['share_target_value'] ?? '';
                [$targetU, $targetE] = resolve_share_target($conn, $kind, $val);
                if (stripos($t, 'quiz_choices_'.$me.'_') === 0) share_private_add($conn, $t, $me, $targetU, $targetE);
            } elseif ($act === 'unshare_subfolder_private') {
                $kind = $_POST['share_target_kind'] ?? '';
                $val  = $_POST['share_target_value'] ?? '';
                [$targetU, $targetE] = resolve_share_target($conn, $kind, $val);
                share_private_remove($conn, $t, $targetU, $targetE);
            }
        }
        header("Location: play_quiz.php"); exit;
    }

    // COPY subfolder (supports copying from Shared → my namespace)
    if ($act === 'copy_subfolder_local') {
        $destFolder = safeTablePart($_POST['dest_folder'] ?? '');
        $overwrite  = !empty($_POST['overwrite']);
        if (!$destFolder || !$sub || !$root) { header("Location: play_quiz.php"); exit; }

        if ($root === 'Shared') {
            $parts = array_values(array_filter(explode('_', $sub)));
            if (count($parts) < 2) { header("Location: play_quiz.php"); exit; }
            $srcOwner = strtolower($parts[0]);
            $srcRoot  = $parts[1];
            $srcAfter = (count($parts) > 2) ? implode('_', array_slice($parts, 2)) : '';
            $srcPrefix = 'quiz_choices_'.$srcOwner.'_'.$srcRoot.($srcAfter? '_'.$srcAfter : '').'_';
            $dstPrefix = 'quiz_choices_'.$me.'_'.$destFolder.($srcAfter? '_'.$srcAfter : '').'_';
        } else {
            $srcPrefix = 'quiz_choices_'.$me.'_'.$root.'_'.$sub.'_';
            $dstPrefix = 'quiz_choices_'.$me.'_'.$destFolder.'_'.$sub.'_';
        }

        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $src = $row[0];
            if (strpos($src, $srcPrefix) !== 0) continue;

            $rest = substr($src, strlen($srcPrefix));
            $dst  = $dstPrefix.$rest;

            $srcEsc = $conn->real_escape_string($src);
            $dstEsc = $conn->real_escape_string($dst);

            if ($overwrite && tableExists($conn, $dst)) $conn->query("DROP TABLE `{$dstEsc}`");
            if (tableExists($conn, $dst)) continue;

            if ($conn->query("CREATE TABLE `{$dstEsc}` LIKE `{$srcEsc}`")) {
                $conn->query("INSERT INTO `{$dstEsc}` SELECT * FROM `{$srcEsc}`");
            }
        }
        header("Location: play_quiz.php"); exit;
    }

    // RENAME subfolder (only my tables)
    if ($act === 'rename_subfolder') {
        $newSeg = safeTablePart($_POST['new_name'] ?? '');
        if (!$newSeg || !$root || !$sub) { header("Location: play_quiz.php"); exit; }

        $prefix = 'quiz_choices_'.$me.'_'.$root.'_'.$sub.'_';
        $parts = explode('_', $sub);
        $parts[count($parts)-1] = $newSeg;
        $newSub = implode('_', $parts);

        $pairs = []; $collisions = [];
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $src = $row[0];
            if (strpos($src, $prefix) !== 0) continue;
            $rest = substr($src, strlen($prefix));
            $dst  = 'quiz_choices_'.$me.'_'.$root.'_'.$newSub.'_'.$rest;
            if (tableExists($conn, $dst)) $collisions[] = $dst;
            else $pairs[] = ['src'=>$src,'dst'=>$dst];
        }
        if (empty($collisions) && !empty($pairs)) {
            $chunks = [];
            foreach ($pairs as $p) $chunks[] = "`".$conn->real_escape_string($p['src'])."` TO `".$conn->real_escape_string($p['dst'])."`";
            if ($conn->query("RENAME TABLE ".implode(', ', $chunks))) {
                // update public + private shares you own
                $stmt = $conn->prepare("UPDATE shared_tables SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $me); $stmt->execute(); }
                $stmt->close();
                $stmt = $conn->prepare("UPDATE shared_tables_private SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $me); $stmt->execute(); }
                $stmt->close();
            }
        }
        header("Location: play_quiz.php"); exit;
    }

    // DELETE subfolder (only my tables)
    if ($act === 'delete_subfolder') {
        $confirm = preg_replace('/[^a-z0-9_]/i', '_', $_POST['confirm_text'] ?? '');
        if (!$root || !$sub || $confirm !== $sub) { header("Location: play_quiz.php"); exit; }

        $prefix = 'quiz_choices_'.$me.'_'.$root.'_'.$sub.'_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) !== 0) continue;
            $tEsc = $conn->real_escape_string($t);
            $conn->query("DROP TABLE `{$tEsc}`");
            share_remove($conn, $t);
            $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=?");
            $stmt->bind_param('s', $t); $stmt->execute(); $stmt->close();
        }
        header("Location: play_quiz.php"); exit;
    }
}




// 🎵 Build a dropdown of FreePD tracks (server-side fetch)
$freepdTracks = [];
$freepdFetchError = '';
$freepdBase = 'https://freepd.com/music/';

$ctx = stream_context_create([
    'http' => [
        'timeout' => 7,
        'user_agent' => 'Mozilla/5.0 (QuizApp FreePD Fetch)'
    ]
]);

$freepdHtml = @file_get_contents($freepdBase, false, $ctx);
if ($freepdHtml !== false) {
    // Find any <a href="...mp3">Title</a>
    if (preg_match_all('#<a[^>]+href="([^"]+\.mp3)"[^>]*>(.*?)</a>#is', $freepdHtml, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $href = html_entity_decode($hit[1], ENT_QUOTES | ENT_HTML5);
            if (stripos($href, 'http') !== 0) {
                $href = rtrim($freepdBase, '/') . '/' . ltrim($href, '/');
            }
            $label = trim(strip_tags($hit[2]));
            if ($label === '') {
                $label = urldecode(basename(parse_url($href, PHP_URL_PATH)));
            }
            $freepdTracks[$label] = $href; // avoid dupes by key
        }
        ksort($freepdTracks, SORT_NATURAL | SORT_FLAG_CASE);
    } else {
        $freepdFetchError = 'Could not parse FreePD track list.';
    }
} else {
    $freepdFetchError = 'FreePD is unreachable right now.';
}


// === Quiz File Explorer prep (quiz_choices_username_foldername_filename) ===
function getQuizFoldersAndFiles(mysqli $conn, string $username): array {
    $folders = [];

    // 1) Own quiz tables
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_array()) {
            $table = $row[0];
            if (strpos($table, 'quiz_choices_') !== 0) continue;

            if (preg_match('/^quiz_choices_([^_]+)_([^_]+)_(.+)$/', $table, $m)) {
                $tUser   = strtolower($m[1]);
                $folder  = $m[2];
                $file    = $m[3];
                if ($tUser !== strtolower($username)) continue;

                $folders[$folder][] = [
                    'table_name'   => $table,
                    'display_name' => $file
                ];
            }
        }
    }

    // 2) Shared quiz tables (public + private for me)
    $me = strtolower($username);
    $meEmail = null;
    // Try to get email from session or users table (optional)
    if (!empty($_SESSION['email'])) {
        $meEmail = mb_strtolower($_SESSION['email']);
    } else if ($stmt = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1")) {
        $stmt->bind_param('s', $me);
        if ($stmt->execute()) {
            $resE = $stmt->get_result(); $rowE = $resE ? $resE->fetch_assoc() : null;
            if (!empty($rowE['email'])) $meEmail = mb_strtolower($rowE['email']);
        }
        $stmt->close();
    }

    // Helper: push a shared row if table exists and is a quiz table
    $pushShared = function(string $t, string $owner) use (&$folders, $conn) {
        if (strpos($t, 'quiz_choices_') !== 0) return; // only quiz sets
        $exists = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
        if (!$exists || $exists->num_rows === 0) return;
        // Build display "owner_rest"
        if (stripos($t, $owner . '_') === 0) {
            $suffix  = substr($t, strlen($owner) + 1);
            $display = $owner . '_' . $suffix;
        } else {
            $display = $t;
        }
        $folders['Shared'][] = ['table_name'=>$t, 'display_name'=>$display];
    };

    // 2a) Public shares
    $shares = $conn->query("SELECT table_name, owner FROM shared_tables");
    while ($shares && ($s = $shares->fetch_assoc())) {
        $pushShared($s['table_name'], strtolower($s['owner']));
    }

    // 2b) Private shares for me
    if ($me || $meEmail) {
        if ($meEmail) {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_email IS NOT NULL AND LOWER(target_email)=?)
                    OR (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('ss', $meEmail, $me);
        } else {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('s', $me);
        }
        if ($stmt && $stmt->execute()) {
            $resP = $stmt->get_result();
            while ($resP && ($p = $resP->fetch_assoc())) {
                $pushShared($p['table_name'], strtolower($p['owner']));
            }
            $stmt->close();
        }
    }

    // Sort folders and files nicely
    if (!empty($folders)) {
        ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($folders as &$list) {
            usort($list, fn($a,$b)=>strnatcasecmp($a['display_name'], $b['display_name']));
        }
    }
    return $folders;
}


$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");

// Build folder structure for quiz tables only
$folders = getQuizFoldersAndFiles($conn, $username);

// Prepare folder data for file_explorer.php
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table'   => $entry['table_name'],   // full table name
            'display' => $entry['display_name']  // filename part only
        ];
    }
}

// Handle selection coming from the explorer
$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? ($_SESSION['table'] ?? '');
$column1 = '';
$column2 = '';

if (!empty($selectedFullTable)) {
    $res = $conn->query("SELECT * FROM `$selectedFullTable`");
    if ($res !== false) {
        $columns = $conn->query("SHOW COLUMNS FROM `$selectedFullTable`");
        if ($columns && $columns->num_rows >= 2) {
            $colData = $columns->fetch_all(MYSQLI_ASSOC);
            $column1 = $colData[0]['Field'];
            $column2 = $colData[1]['Field'];
        }
        // Make the app use this dataset
        $_SESSION['table'] = $selectedFullTable;
        $_SESSION['col1']  = $column1;
        $_SESSION['col2']  = $column2;

        // If it's a quiz set, also set it as the active quiz
        if (strpos($selectedFullTable, 'quiz_choices_') === 0) {
            $_SESSION['quiz_table'] = $selectedFullTable;
        }
    }
}

$selectedTable = $_SESSION['quiz_table'] ?? '';
$musicSrc = $_SESSION['bg_music'] ?? '';

// 🧹 Clean slate if button pressed
if (isset($_POST['clean_slate'])) {
    unset(
        $_SESSION['score'],
        $_SESSION['question_index'],
        $_SESSION['questions'],
        $_SESSION['quiz_table'],
        $_SESSION['bg_music'],
        $_SESSION['mistakes'],
        $_SESSION['feedback']
    );
    header("Location: play_quiz.php");
    exit;
}

// 🚀 Start Quiz (now relies on explorer selection)
if (isset($_POST['start_new'])) {
    $chosen = $_POST['quiz_table'] ?? ($_SESSION['quiz_table'] ?? '');

    if (!$chosen || strpos($chosen, 'quiz_choices_') !== 0) {
        die("⚠️ Please select a quiz set from the explorer first.");
    }

    $_SESSION['quiz_table'] = $chosen;
    $_SESSION['score'] = 0;
    $_SESSION['question_index'] = 0;
    $_SESSION['mistakes'] = [];

    // 🎵 Music choice (supports FreePD dropdown & custom URL)
    $musicChoice    = $_POST['bg_music_choice'] ?? '';
    $customURL      = trim($_POST['custom_music_url'] ?? '');
    $freepdURLSel   = trim($_POST['freepd_music_url'] ?? '');

    if ($musicChoice === 'freepd' && filter_var($freepdURLSel, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $freepdURLSel;
    } elseif ($musicChoice === 'custom' && filter_var($customURL, FILTER_VALIDATE_URL)) {
        $_SESSION['bg_music'] = $customURL;
    } elseif ($musicChoice !== '') { // builtin tracks like track1.mp3
        $_SESSION['bg_music'] = $musicChoice;
    } else {
        $_SESSION['bg_music'] = '';
    }

    // 📥 Load questions
    $selectedTable = $_SESSION['quiz_table'];
    $res = $conn->query("SELECT question, correct_answer, wrong1, wrong2, wrong3, image_url FROM `$selectedTable`");
    if (!$res) die("❌ Query failed: " . $conn->error);

    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $answers = [$row['correct_answer'], $row['wrong1'], $row['wrong2'], $row['wrong3']];
        shuffle($answers);
        $questions[] = [
            'question' => $row['question'],
            'correct'  => $row['correct_answer'],
            'answers'  => $answers,
            'image'    => $row['image_url'] ?? ''
        ];
    }

    if (empty($questions)) {
        die("⚠️ No questions found in '$selectedTable'.");
    }

    shuffle($questions);
    $_SESSION['questions'] = $questions;

    // Refresh to avoid form resubmission
    header("Location: play_quiz.php");
    exit;
}

// ====== PAGE OUTPUT (single valid HTML document) ======
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Play Quiz</title><meta name='viewport' content='width=device-width, initial-scale=1.0'>";

// Include site styling/header (your file outputs markup; we keep consistency with main.php)
include 'styling.php';

// Extra page-specific styles (kept minimal)
echo "<style>
    #quizBox { display:none; height:100vh; overflow-y:auto; box-sizing:border-box; padding:20px; }
    body { font-family:sans-serif; text-align:center; padding:0 0 80px 0; margin:0; }
    .question-box { font-size:clamp(1.2em,4vw,1.5em); margin-bottom:20px; }
    .answer-grid { display:flex; flex-wrap:wrap; justify-content:center; max-width:600px; margin:auto; }
    .answer-col { flex:0 0 50%; padding:10px; box-sizing:border-box; }
    .answer-btn { width:100%; padding:clamp(12px,3vw,20px); font-size:clamp(1em,3vw,1.1em); cursor:pointer; border:none; border-radius:10px; background:#eee; transition:0.3s; word-wrap:break-word; }
    .answer-btn:hover { background:#ddd; }
    .feedback { font-size:clamp(1em,3vw,1.2em); margin-top:20px; }
    .score { margin-bottom:10px; font-weight:bold; }
    .image-container { margin:20px auto; }
    img.question-image { width:100vw; height:auto; max-height:66vw; object-fit:contain; display:block; margin:0 auto; }
    @media (min-width:768px){ img.question-image { width:50vw; max-height:50vh; } }
    select, button, input[type=url] { padding:10px; font-size:clamp(0.9em,3vw,1em); max-width:90%; }
    #timer { font-size:clamp(1.1em,3.5vw,1.3em); color:darkred; margin:10px; }
    .quiz-buttons { text-align:center; margin-top:20px; }
    .quiz-buttons button { display:inline-flex; align-items:center; justify-content:center; gap:6px; background:#d3d3d3; color:#000; padding:10px 20px; border:none; border-radius:5px; font-size:clamp(0.9em,3vw,1em); cursor:pointer; margin:5px; white-space:nowrap; }
    .quiz-buttons button:hover { background:#bfbfbf; }
    @media (max-width:500px){ .answer-col { flex:0 0 100%; } }
</style>";

echo "</head><body>";
?>
<div id="quizBox"></div>
<hr style="margin:30px 0;">
<div class="content">
    👤 Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?> |
    <a href="logout.php">Logout</a>
    <h1>🎯 Quiz</h1>

    <audio id="bgMusic" loop preload="auto">
        <source id="bgMusicSource" src="<?php echo htmlspecialchars($musicSrc); ?>" type="audio/mpeg">
        Your browser does not support audio.
    </audio>

    <!-- Explorer block (NO form here) -->
    <div style="margin:10px 0 20px;">
        <h2 style="margin:10px 0;">Select quiz set</h2>
        <?php

            $EXPLORER_MODE = 'quiz'; // hide menus; no posting to main.php
            include 'file_explorer.php'; // has its own <form> that sets $_SESSION['quiz_table']
            $currentQuiz = $_SESSION['quiz_table'] ?? '';
            if ($currentQuiz) {
                echo "<div style='margin-top:8px;font-size:0.95em;'>✅ Selected: <code>"
                     . htmlspecialchars($currentQuiz)
                     . "</code></div>";
            } else {
                echo "<div style='margin-top:8px;color:#a00;font-size:0.95em;'>⚠️ Pick a quiz set from the list above.</div>";
            }
        ?>
    </div>

    <!-- Start form (single form; music controls + hidden quiz_table + Start) -->
    <form method="POST" style="display:block; margin-bottom:10px;">
        <label>Select background music:</label><br><br>
        <?php
            $currentMusic = $_SESSION['bg_music'] ?? '';
            $isFreePDSelected = in_array($currentMusic, array_values($freepdTracks), true);
        ?>
        <select name="bg_music_choice" onchange="toggleMusicSources(this.value)">
            <option value="" <?php echo $currentMusic === '' ? 'selected' : ''; ?>>🔇 OFF</option>
            <option value="track1.mp3" <?php echo $currentMusic === 'track1.mp3' ? 'selected' : ''; ?>>🎸 Track 1</option>
            <option value="track2.mp3" <?php echo $currentMusic === 'track2.mp3' ? 'selected' : ''; ?>>🎹 Track 2</option>
            <option value="track3.mp3" <?php echo $currentMusic === 'track3.mp3' ? 'selected' : ''; ?>>🥛 Track 3</option>
            <option value="freepd" <?php echo $isFreePDSelected ? 'selected' : ''; ?>>🎼 FreePD library</option>
            <option value="custom" <?php echo (!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'selected' : ''; ?>>🌐 Custom URL</option>
        </select>

        <div id="freepdSelectWrap" style="<?php echo $isFreePDSelected ? 'display:block;' : 'display:none;'; ?>">
            <?php if (!empty($freepdFetchError)) { ?>
                <div style="margin:8px 0;color:#a00;font-size:0.95em;">
                    ⚠️ <?php echo htmlspecialchars($freepdFetchError); ?> You can also open
                    <a href="https://freepd.com/music/" target="_blank" rel="noopener">freepd.com/music</a>
                    and paste an MP3 link below.
                </div>
            <?php } ?>
            <label for="freepdSelect" style="display:block;margin:8px 0 6px;">Choose a FreePD track:</label>
            <select id="freepdSelect" name="freepd_music_url" style="width:100%;max-width:600px;">
                <option value="">-- Select from FreePD --</option>
                <?php foreach ($freepdTracks as $label => $url): ?>
                    <option value="<?php echo htmlspecialchars($url); ?>" <?php echo ($currentMusic === $url) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="margin-top:6px;font-size:0.9em;">Tip: Preview below to make sure the track loads.</div>
        </div>

        <div id="customMusicInput" style="<?php echo (!$isFreePDSelected && filter_var($currentMusic, FILTER_VALIDATE_URL)) ? 'display:block;' : 'display:none;'; ?>">
            <input type="url" name="custom_music_url" placeholder="Paste full MP3 URL" style="width:100%;max-width:600px;" value="<?php echo htmlspecialchars($currentMusic); ?>">
        </div>

        <div style="margin:12px 0 20px;">
            <button type="button" onclick="previewMusic()">🎧 Preview</button>
            <button type="button" onclick="toggleMusic()">▶️/⏸️ Toggle Music</button>
            <audio id="previewPlayer" controls style="display:none; margin-top: 10px;"></audio>
        </div>

        <!-- Hidden field with the currently selected quiz set -->
        <input type="hidden" name="quiz_table" value="<?php echo htmlspecialchars($_SESSION['quiz_table'] ?? ''); ?>">

        <div class="quiz-buttons">
            <button type="submit" name="start_new" id="startQuizBtn">▶️ Start Quiz</button>
        </div>
    </form>

    <!-- Separate clean slate form -->
    <form method="POST" style="display:block;">
        <div class="quiz-buttons">
            <button type="submit" name="clean_slate">🧹 Clean Slate</button>
        </div>
    </form>
</div>

<hr>

<script>
let countdown = null;
let timeLeft = 15;

// MUSIC
function toggleMusicSources(value) {
    document.getElementById('customMusicInput').style.display = (value === 'custom') ? 'block' : 'none';
    document.getElementById('freepdSelectWrap').style.display = (value === 'freepd') ? 'block' : 'none';
}
function previewMusic() {
    const sourceSel  = document.querySelector('select[name="bg_music_choice"]');
    const customInput= document.querySelector('input[name="custom_music_url"]');
    const freepdSel  = document.getElementById('freepdSelect');
    const player     = document.getElementById('previewPlayer');
    let src = '';
    if (sourceSel.value === 'custom') src = (customInput?.value || '').trim();
    else if (sourceSel.value === 'freepd') src = (freepdSel?.value || '').trim();
    else src = sourceSel.value;
    if (!src) { alert('Please pick a track first.'); return; }
    player.src = src;
    player.style.display = 'block';
    player.play().catch(err => console.warn('Preview blocked:', err));
}
function toggleMusic() {
    const music = document.getElementById('bgMusic');
    const source = document.getElementById('bgMusicSource');
    if (!source.src || source.src.endsWith('/')) { alert('Please select a valid music track first.'); return; }
    if (music.paused) { music.volume = 0.3; music.play().catch(err => console.warn('Music play blocked:', err)); }
    else { music.pause(); }
}

// QUIZ
function revealAnswers() {
    const grid = document.querySelector('.answer-grid');
    if (grid) { grid.style.display = 'flex'; startTimer(); }
}
function startTimer() {
    clearInterval(countdown);
    timeLeft = 15;
    const timerDisplay = document.getElementById('timer');
    countdown = setInterval(() => {
        timeLeft--;
        if (timerDisplay) timerDisplay.textContent = '⏳ ' + timeLeft; // avoid PHP interpolating ${timeLeft}
        if (timeLeft <= 0) {
            clearInterval(countdown);
            document.querySelectorAll('.answer-btn').forEach(btn => btn.disabled = true);
            if (timerDisplay) timerDisplay.textContent = '⏰ Time\'s up!';
        }
    }, 1000);
}
function submitAnswer(btn) {
    const value = btn.getAttribute('data-value');
    const buttons = document.querySelectorAll('.answer-btn');
    buttons.forEach(b => b.disabled = true);
    clearInterval(countdown);
    fetch('submit_answer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ answer: value, time_taken: 15 - timeLeft })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        const correctAnswer = data.correctAnswer;
        const feedbackText  = data.feedback;
        buttons.forEach(b => {
            const btnText = b.textContent.trim();
            if (btnText === correctAnswer) { b.style.backgroundColor = '#4CAF50'; b.style.color = 'white'; }
            else if (b.getAttribute('data-value') === value) { b.style.backgroundColor = '#f44336'; b.style.color = 'white'; }
        });
        const feedbackBox = document.getElementById('feedbackBox');
        if (feedbackBox) { feedbackBox.innerHTML = feedbackText; feedbackBox.style.display = 'block'; }
        setTimeout(() => {
            fetch('load_question.php')
                .then(res => res.text())
                .then(html => {
                    const quizBox = document.getElementById('quizBox');
                    quizBox.style.display = 'block';
                    quizBox.innerHTML = html;
                    setTimeout(revealAnswers, 2000);
                });
        }, 2000);
    });
}
function loadNextQuestion() {
    fetch('load_question.php')
        .then(res => res.text())
        .then(html => {
            const qb = document.getElementById('quizBox');
            qb.style.display = 'block';
            qb.innerHTML = html;
            setTimeout(revealAnswers, 2000);
        });
}
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.querySelector('select[name="bg_music_choice"]');
    if (sel) toggleMusicSources(sel.value);
    const quizBox = document.getElementById('quizBox');
    <?php if (!empty($_SESSION['questions'])): ?>
        quizBox.style.display = 'block';
        loadNextQuestion();
        setTimeout(() => {
            const music = document.getElementById('bgMusic');
            const source = document.getElementById('bgMusicSource');
            if (music && source && source.src) {
                music.volume = 0.3;
                music.play().catch(err => console.warn('Autoplay blocked by browser:', err));
            }
        }, 500);
    <?php else: ?>
        quizBox.style.display = 'none';
    <?php endif; ?>
});
window.addEventListener('beforeunload', function () {
    let isReload = false;
    if (performance.getEntriesByType) {
        const nav = performance.getEntriesByType('navigation')[0];
        isReload = nav && nav.type === 'reload';
    } else if (performance.navigation) {
        isReload = performance.navigation.type === 1;
    }
    if (!isReload) {
        navigator.sendBeacon('reset_quiz_session.php');
        const quizBox = document.getElementById('quizBox');
        if (quizBox) { quizBox.style.display = 'none'; quizBox.innerHTML = ''; }
    }
});
</script>
<?php
echo "</body></html>";


