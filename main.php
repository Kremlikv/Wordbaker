<?php
require_once 'db.php';
require_once 'session.php';

// -------------------------------------------
// Virtual sharing table + helpers
// -------------------------------------------
$conn->query("
CREATE TABLE IF NOT EXISTS shared_tables (
  table_name VARCHAR(255) PRIMARY KEY,
  owner      VARCHAR(64)  NOT NULL,
  shared_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

function share_add(mysqli $conn, string $table, string $owner): void {
    $stmt = $conn->prepare("INSERT IGNORE INTO shared_tables (table_name, owner) VALUES (?, ?)");
    $stmt->bind_param('ss', $table, $owner);
    $stmt->execute(); $stmt->close();
}
function share_remove(mysqli $conn, string $table): void {
    $stmt = $conn->prepare("DELETE FROM shared_tables WHERE table_name=?");
    $stmt->bind_param('s', $table);
    $stmt->execute(); $stmt->close();
}
function share_owner(mysqli $conn, string $table): ?string {
    $stmt = $conn->prepare("SELECT owner FROM shared_tables WHERE table_name=?");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result(); $row = $res? $res->fetch_assoc(): null;
    $stmt->close();
    return $row['owner'] ?? null;
}

// -------------------------------------------
// NEW: Per-user private shares (parallel to shared_tables)
// -------------------------------------------
$conn->query("
CREATE TABLE IF NOT EXISTS shared_tables_private (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  table_name VARCHAR(255) NOT NULL,
  owner      VARCHAR(64)  NOT NULL,
  target_username VARCHAR(64)  DEFAULT NULL,
  target_email    VARCHAR(255) DEFAULT NULL,
  shared_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_share (table_name, owner, target_username, target_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// NEW: Add single private share row (idempotent via UNIQUE + INSERT IGNORE)
function share_private_add(mysqli $conn, string $table, string $owner, ?string $target_username, ?string $target_email): void {
    $stmt = $conn->prepare("INSERT IGNORE INTO shared_tables_private (table_name, owner, target_username, target_email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $table, $owner, $target_username, $target_email);
    $stmt->execute(); $stmt->close();
}
// NEW: Remove a specific private share row
function share_private_remove(mysqli $conn, string $table, string $owner, ?string $target_username, ?string $target_email): void {
    if ($target_username !== null) {
        $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=? AND owner=? AND target_username=? AND target_email IS NULL");
        $stmt->bind_param('sss', $table, $owner, $target_username);
        $stmt->execute(); $stmt->close();
    }
    if ($target_email !== null) {
        $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=? AND owner=? AND target_email=? AND target_username IS NULL");
        $stmt->bind_param('sss', $table, $owner, $target_email);
        $stmt->execute(); $stmt->close();
    }
}
// NEW: Remove all private share rows for a table (cleanup on delete)
function share_private_remove_all_for_table(mysqli $conn, string $table): void {
    $stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=?");
    $stmt->bind_param('s', $table);
    $stmt->execute(); $stmt->close();
}
// NEW: Resolve incoming target (username or email) from POST
function resolve_share_target(string $kind, string $value): array {
    $value = trim($value);
    if ($kind === 'email') {
        return ['username'=>null, 'email'=>mb_strtolower($value)];
    } else {
        return ['username'=>$value, 'email'=>null];
    }
}
// NEW: Get current user's email (from session or DB) to show private shares "for me"
function getCurrentUserEmail(mysqli $conn, string $username): ?string {
    if ($username === '') return null;
    if (!empty($_SESSION['email'])) return mb_strtolower($_SESSION['email']);
    if ($stmt = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1")) {
        $stmt->bind_param('s', $username);
        if ($stmt->execute()) {
            $res = $stmt->get_result(); $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if (!empty($row['email'])) return mb_strtolower($row['email']);
        } else { $stmt->close(); }
    }
    return null;
}

// -------------------------------------------
// Common helpers (declared early on purpose)
// -------------------------------------------
function safeTablePart(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9_]+/u', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}
function tableExists(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($table)."'");
    return $res && $res->num_rows > 0;
}
function cloneTableLike(mysqli $conn, string $src, string $dst, bool $overwrite): bool {
    $srcEsc = $conn->real_escape_string($src);
    $dstEsc = $conn->real_escape_string($dst);
    if ($overwrite && tableExists($conn, $dst)) {
        if (!$conn->query("DROP TABLE `{$dstEsc}`")) {
            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>".
                 "Drop failed for <code>".htmlspecialchars($dst)."</code>: ".htmlspecialchars($conn->error)."</div>";
            return false;
        }
    }
    if (tableExists($conn, $dst)) {
        echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>".
             "Table <code>".htmlspecialchars($dst)."</code> already exists. Enable <b>Overwrite</b> to replace it.</div>";
        return false;
    }
    if (!$conn->query("CREATE TABLE `{$dstEsc}` LIKE `{$srcEsc}`")) {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>".
             "Create LIKE failed: ".htmlspecialchars($conn->error)."</div>";
        return false;
    }
    return true;
}

// -------------------------------------------
// Table deletion (single table) BEFORE any output
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $conn2 = new mysqli($host, $user, $password, $database);
    $conn2->set_charset("utf8mb4");

    if ($conn2->connect_error) {
        die("Connection failed: " . $conn2->connect_error);
    }

    $tableToDelete = $conn2->real_escape_string($_POST['delete_table']);
    $tables = [];
    $result = $conn2->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    if (in_array($tableToDelete, $tables, true) && !in_array($tableToDelete, ['difficult_words', 'mastered_words'], true)) {
        $conn2->query("DROP TABLE `$tableToDelete`");
        // clean virtual share pointer (if any)
        share_remove($conn2, $tableToDelete);
        // NEW: clean private share pointers too
        share_private_remove_all_for_table($conn2, $tableToDelete);
        // remove cached audio
        $audioPath = "cache/$tableToDelete.mp3";
        if (file_exists($audioPath)) { @unlink($audioPath); }
    }

    $conn2->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------
// Audio deletion BEFORE any output
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_audio_file'])) {
    $tableForAudio = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['delete_audio_file']);
    foreach (['mp3','wav'] as $ext) {
        $path = "cache/$tableForAudio.$ext";
        if (file_exists($path)) { @unlink($path); }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($tableForAudio));
    exit;
}

// -------------------------------------------
// LEFT PANE: folder Share / Unshare (virtual)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['folder_action'] ?? '') === 'share_folder') {
    $username = strtolower($_SESSION['username'] ?? '');
    $folder   = safeTablePart($_POST['folder_old'] ?? '');
    if ($username !== '' && $folder !== '') {
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (stripos($t, $username . '_' . $folder . '_') === 0) {
                share_add($conn, $t, $username);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['folder_action'] ?? '') === 'unshare_folder') {
    $username = strtolower($_SESSION['username'] ?? '');
    $folder   = safeTablePart($_POST['folder_old'] ?? '');
    if ($username !== '' && $folder !== '') {
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (stripos($t, $username . '_' . $folder . '_') === 0) {
                if (share_owner($conn, $t) === $username) share_remove($conn, $t);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

// -------------------------------------------
// NEW: LEFT PANE: folder Share/Unshare with a specific user (private)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['folder_action'] ?? '') === 'share_folder_private') {
    $username = strtolower($_SESSION['username'] ?? '');
    $folder   = safeTablePart($_POST['folder_old'] ?? '');
    $kind     = $_POST['share_target_kind']  ?? '';
    $val      = $_POST['share_target_value'] ?? '';
    if ($username !== '' && $folder !== '' && $kind !== '' && $val !== '') {
        $target = resolve_share_target($kind, $val);
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (stripos($t, $username . '_' . $folder . '_') === 0) {
                share_private_add($conn, $t, $username, $target['username'], $target['email']);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['folder_action'] ?? '') === 'unshare_folder_private') {
    $username = strtolower($_SESSION['username'] ?? '');
    $folder   = safeTablePart($_POST['folder_old'] ?? '');
    $kind     = $_POST['share_target_kind']  ?? '';
    $val      = $_POST['share_target_value'] ?? '';
    if ($username !== '' && $folder !== '' && $kind !== '' && $val !== '') {
        $target = resolve_share_target($kind, $val);
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (stripos($t, $username . '_' . $folder . '_') === 0) {
                share_private_remove($conn, $t, $username, $target['username'], $target['email']);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

// -------------------------------------------
// LEFT PANE: Copy folder (same user)
//  Copies <user>_<srcFolder>_*  →  <user>_<destFolder>_<srcFolder>_*
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['folder_action'] ?? '') === 'copy_folder_local') {
    $conn->set_charset('utf8mb4');
    $username   = strtolower($_SESSION['username'] ?? '');
    $srcFolder  = safeTablePart($_POST['folder_old'] ?? '');
    $destFolder = safeTablePart($_POST['dest_folder'] ?? '');
    $overwrite  = !empty($_POST['overwrite']);

    if ($username === '' || $srcFolder === '' || $destFolder === '') {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Missing parameters.</div>";
    } else {
        $pairs = []; $collisions = [];
        $prefix = $username . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (stripos($t, $prefix) === 0) {
                $suffix = substr($t, strlen($prefix)); // folder_rest
                $parts  = explode('_', $suffix, 2);
                if (count($parts) === 2 && $parts[0] === $srcFolder) {
                    $rest    = $parts[1];                    // e.g. sub1_sub2_filename
                    $dstRest = "{$srcFolder}_{$rest}";       // keep the folder itself
                    $dst     = "{$username}_{$destFolder}_{$dstRest}";
                    if (!$overwrite && tableExists($conn, $dst)) { $collisions[] = $dst; }
                    else { $pairs[] = ['src'=>$t,'dst'=>$dst]; }
                }
            }
        }
        if (!empty($collisions)) {
            echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>
                    Cannot copy; destination exists:<br><code>".htmlspecialchars(implode(', ', $collisions))."</code>
                  </div>";
        } elseif (empty($pairs)) {
            echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>
                    No tables found in folder <code>".htmlspecialchars($srcFolder)."</code>.
                  </div>";
        } else {
            foreach ($pairs as $p) {
                $srcEsc = $conn->real_escape_string($p['src']);
                $dstEsc = $conn->real_escape_string($p['dst']);
                if ($overwrite && tableExists($conn, $p['dst'])) { $conn->query("DROP TABLE `{$dstEsc}`"); }
                if (!$conn->query("CREATE TABLE `{$dstEsc}` LIKE `{$srcEsc}`")) {
                    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>".
                         "CREATE LIKE failed for <code>".htmlspecialchars($p['dst'])."</code>: ".htmlspecialchars($conn->error)."</div>";
                    continue;
                }
                $conn->query("INSERT INTO `{$dstEsc}` SELECT * FROM `{$srcEsc}`");
            }
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    }
}

// -------------------------------------------
// RIGHT PANE: SUBFOLDER actions (virtual share/unshare)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'share_subfolder') {
    $username = strtolower($_SESSION['username'] ?? '');
    $root     = safeTablePart($_POST['root_folder'] ?? '');
    $subpath  = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    if ($username && $root && $subpath) {
        $prefix = $username . '_' . $root . '_' . $subpath . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) === 0) share_add($conn, $t, $username);
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'unshare_subfolder') {
    $username = strtolower($_SESSION['username'] ?? '');
    $root     = safeTablePart($_POST['root_folder'] ?? '');
    $subpath  = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    if ($username && $root && $subpath) {
        $prefix = $username . '_' . $root . '_' . $subpath . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) === 0 && share_owner($conn, $t) === $username) {
                share_remove($conn, $t);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

// -------------------------------------------
// NEW: RIGHT PANE: SUBFOLDER share/unshare with a specific user (private)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'share_subfolder_private') {
    $username = strtolower($_SESSION['username'] ?? '');
    $root     = safeTablePart($_POST['root_folder'] ?? '');
    $subpath  = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    $kind     = $_POST['share_target_kind']  ?? '';
    $val      = $_POST['share_target_value'] ?? '';
    if ($username && $root && $subpath && $kind && $val) {
        $target = resolve_share_target($kind, $val);
        $prefix = $username . '_' . $root . '_' . $subpath . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) === 0) {
                share_private_add($conn, $t, $username, $target['username'], $target['email']);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'unshare_subfolder_private') {
    $username = strtolower($_SESSION['username'] ?? '');
    $root     = safeTablePart($_POST['root_folder'] ?? '');
    $subpath  = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    $kind     = $_POST['share_target_kind']  ?? '';
    $val      = $_POST['share_target_value'] ?? '';
    if ($username && $root && $subpath && $kind && $val) {
        $target = resolve_share_target($kind, $val);
        $prefix = $username . '_' . $root . '_' . $subpath . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) === 0) {
                share_private_remove($conn, $t, $username, $target['username'], $target['email']);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

// -------------------------------------------
// RIGHT PANE: SUBFOLDER copy / rename / delete
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'copy_subfolder_local') {
    $conn->set_charset('utf8mb4');
    $me         = strtolower($_SESSION['username'] ?? '');
    $root       = safeTablePart($_POST['root_folder'] ?? '');
    $sub        = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    $destFolder = safeTablePart($_POST['dest_folder'] ?? '');
    $overwrite  = !empty($_POST['overwrite']);

    if (!$me || !$root || !$sub || !$destFolder) {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Missing parameters.</div>";
    } else {
        $srcPrefix = '';
        $dstPrefix = '';

        if ($root === 'Shared') {
            // sub is like: owner_root_sub1_sub2...
            $parts = array_values(array_filter(explode('_', $sub), fn($p) => $p !== ''));
            if (count($parts) < 2) {
                echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>
                        Please right-click a folder at least at <code>owner / root</code> depth inside Shared to copy.
                      </div>";
                exit;
            }
            $srcOwner = strtolower($parts[0]);
            $srcRoot  = $parts[1];
            $srcAfter = (count($parts) > 2) ? implode('_', array_slice($parts, 2)) : '';

            // Source prefix we will match tables against:
            //   owner_root_[after]_   (after may be empty)
            $srcPrefix = $srcOwner . '_' . $srcRoot . ($srcAfter !== '' ? '_' . $srcAfter : '') . '_';

            // Destination prefix we will create tables under:
            //   me_destFolder_[after]_   (we do NOT include source owner or root)
            $dstPrefix = $me . '_' . $destFolder . ($srcAfter !== '' ? '_' . $srcAfter : '') . '_';
        } else {
            // Copying my own subtree: me_root_sub_...
            $srcPrefix = $me . '_' . $root . '_' . $sub . '_';
            // Destination keeps the selected subpath under destFolder
            $dstPrefix = $me . '_' . $destFolder . '_' . $sub . '_';
        }

        $res = $conn->query("SHOW TABLES");
        $copied = 0; $errors = 0;

        while ($res && ($row = $res->fetch_array())) {
            $src = $row[0];
            if (strpos($src, $srcPrefix) !== 0) continue;

            $rest = substr($src, strlen($srcPrefix)); // file name (and any deeper sub-sub paths baked into file name)
            $dst  = $dstPrefix . $rest;

            $srcEsc = $conn->real_escape_string($src);
            $dstEsc = $conn->real_escape_string($dst);

            if ($overwrite && tableExists($conn, $dst)) {
                $conn->query("DROP TABLE `{$dstEsc}`");
            }
            if (tableExists($conn, $dst)) {
                // skip silently if not overwriting
                continue;
            }

            if ($conn->query("CREATE TABLE `{$dstEsc}` LIKE `{$srcEsc}`")) {
                if ($conn->query("INSERT INTO `{$dstEsc}` SELECT * FROM `{$srcEsc}`")) {
                    $copied++;
                } else {
                    $errors++;
                    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Copy rows failed for <code>"
                         .htmlspecialchars($dst)."</code>: ".htmlspecialchars($conn->error)."</div>";
                }
            } else {
                $errors++;
                echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>CREATE LIKE failed for <code>"
                     .htmlspecialchars($dst)."</code>: ".htmlspecialchars($conn->error)."</div>";
            }
        }

        // After attempting copies, go back to the page (even if some errors were displayed)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'rename_subfolder') {
    $conn->set_charset('utf8mb4');
    $me     = strtolower($_SESSION['username'] ?? '');
    $root   = safeTablePart($_POST['root_folder'] ?? '');
    $sub    = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    $newSeg = safeTablePart($_POST['new_name'] ?? '');
    if ($me && $root && $sub && $newSeg) {
        $prefix = $me . '_' . $root . '_' . $sub . '_';

        $parts = explode('_', $sub);
        $parts[count($parts)-1] = $newSeg;
        $newSub = implode('_', $parts);

        $pairs = []; $collisions = [];
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $src = $row[0];
            if (strpos($src, $prefix) !== 0) continue;
            $rest = substr($src, strlen($prefix));
            $dst  = "{$me}_{$root}_{$newSub}_{$rest}";
            if (tableExists($conn, $dst)) $collisions[] = $dst;
            else $pairs[] = ['src'=>$src,'dst'=>$dst];
        }
        if (!empty($collisions)) {
            echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>Cannot rename, destination exists:<br><code>"
                 .htmlspecialchars(implode(', ', $collisions))."</code></div>";
        } elseif (!empty($pairs)) {
            $chunks = [];
            foreach ($pairs as $p) $chunks[] = "`".$conn->real_escape_string($p['src'])."` TO `".$conn->real_escape_string($p['dst'])."`";
            if ($conn->query("RENAME TABLE ".implode(', ', $chunks))) {
                // update any virtual shares owned by me (so Shared view stays in sync)
                $stmt = $conn->prepare("UPDATE shared_tables SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $me); $stmt->execute(); }
                $stmt->close();
                // NEW: update private shares owned by me
                $stmt2 = $conn->prepare("UPDATE shared_tables_private SET table_name=? WHERE table_name=? AND owner=?");
                foreach ($pairs as $p) { $stmt2->bind_param('sss', $p['dst'], $p['src'], $me); $stmt2->execute(); }
                $stmt2->close();

                header("Location: " . $_SERVER['PHP_SELF']); exit;
            } else {
                echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Rename failed: "
                     .htmlspecialchars($conn->error)."</div>";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sub_action'] ?? '') === 'delete_subfolder') {
    $conn->set_charset('utf8mb4');
    $me      = strtolower($_SESSION['username'] ?? '');
    $root    = safeTablePart($_POST['root_folder'] ?? '');
    $sub     = preg_replace('/[^a-z0-9_]/i', '_', $_POST['subpath'] ?? '');
    $confirm = preg_replace('/[^a-z0-9_]/i', '_', $_POST['confirm_text'] ?? '');
    if ($me && $root && $sub && $confirm === $sub) {
        $prefix = $me . '_' . $root . '_' . $sub . '_';
        $res = $conn->query("SHOW TABLES");
        while ($res && ($row = $res->fetch_array())) {
            $t = $row[0];
            if (strpos($t, $prefix) !== 0) continue;
            $tEsc = $conn->real_escape_string($t);
            $conn->query("DROP TABLE `{$tEsc}`");
            share_remove($conn, $t); // clean any share pointer
            // NEW: clean private share pointers
            share_private_remove_all_for_table($conn, $t);
            $audio = "cache/$t.mp3"; if (file_exists($audio)) @unlink($audio);
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    } elseif ($confirm !== $sub) {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Type the subfolder path (<code>"
             .htmlspecialchars($sub)."</code>) to confirm.</div>";
    }
}

// -------------------------------------------
// FOLDER: rename / delete (left pane)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['folder_action'] ?? '';

    if ($action === 'rename_folder' || $action === 'delete_folder') {
        $conn->set_charset("utf8mb4");
        $username = strtolower($_SESSION['username'] ?? '');
        $old = safeTablePart($_POST['folder_old'] ?? '');
        if ($old === '' || $username === '') {
            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Invalid folder or user.</div>";
        } elseif ($old === 'Shared') {
            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Cannot modify Shared.</div>";
        } else {
            // Collect all tables in this user's folder
            $tables = [];
            $res = $conn->query("SHOW TABLES");
            while ($res && ($row = $res->fetch_array())) {
                $t = $row[0];
                if (stripos($t, $username . '_') === 0) {
                    $suffix = substr($t, strlen($username) + 1);               // folder_file
                    $parts  = explode('_', $suffix, 2);                         // [folder, file...]
                    if (count($parts) === 2 && $parts[0] === $old) {
                        $tables[] = $t;
                    }
                }
            }

            if ($action === 'rename_folder') {
                $new = safeTablePart($_POST['folder_new'] ?? '');
                if ($new !== '' && $new !== $old) {
                    $collisions = [];
                    $pairs = [];
                    foreach ($tables as $src) {
                        $suffix = substr($src, strlen($username) + 1); // folder_file
                        [$folder, $rest] = explode('_', $suffix, 2);
                        $dst = "{$username}_{$new}_{$rest}";
                        if (tableExists($conn, $dst)) {
                            $collisions[] = $dst;
                        } else {
                            $pairs[] = ["src" => $src, "dst" => $dst];
                        }
                    }
                    if (!empty($collisions)) {
                        echo "<div class='content' style='color:#92400e;background:#fef3c7;border:1px solid #fde68a;padding:10px;border-radius:8px;margin:10px 0;'>
                                Cannot rename: the following tables already exist:<br><code>".htmlspecialchars(implode(', ', $collisions))."</code>
                              </div>";
                    } elseif (!empty($pairs)) {
                        $chunks = [];
                        foreach ($pairs as $p) {
                            $chunks[] = "`".$conn->real_escape_string($p['src'])."` TO `".$conn->real_escape_string($p['dst'])."`";
                        }
                        $sql = "RENAME TABLE ".implode(', ', $chunks);
                        if ($conn->query($sql)) {
                            // update virtual shares you own
                            $stmt = $conn->prepare("UPDATE shared_tables SET table_name=? WHERE table_name=? AND owner=?");
                            foreach ($pairs as $p) { $stmt->bind_param('sss', $p['dst'], $p['src'], $username); $stmt->execute(); }
                            $stmt->close();
                            // NEW: update private shares you own
                            $stmt2 = $conn->prepare("UPDATE shared_tables_private SET table_name=? WHERE table_name=? AND owner=?");
                            foreach ($pairs as $p) { $stmt2->bind_param('sss', $p['dst'], $p['src'], $username); $stmt2->execute(); }
                            $stmt2->close();

                            header("Location: " . $_SERVER['PHP_SELF']); exit;
                        } else {
                            echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>".
                                 "Rename failed: ".htmlspecialchars($conn->error)."</div>";
                        }
                    }
                }
            }

            if ($action === 'delete_folder') {
                $confirm = safeTablePart($_POST['confirm_text'] ?? '');
                if ($confirm !== $old) {
                    echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>
                            Type the folder name (<code>".htmlspecialchars($old)."</code>) to confirm deletion.
                          </div>";
                } else {
                    foreach ($tables as $t) {
                        $conn->query("DROP TABLE `".$conn->real_escape_string($t)."`");
                        share_remove($conn, $t); // clean any share pointer
                        // NEW: clean private share pointers
                        share_private_remove_all_for_table($conn, $t);
                        $audioPath = "cache/$t.mp3";
                        if (file_exists($audioPath)) @unlink($audioPath);
                    }
                    header("Location: " . $_SERVER['PHP_SELF']); exit;
                }
            }
        }
    }
}

// -------------------------------------------
// SAVE AS (runs before any HTML output)
// -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_as') {
    $conn->set_charset("utf8mb4");

    $username = strtolower($_SESSION['username'] ?? 'user');
    $srcTable = $_POST['table'] ?? '';
    $col1     = $_POST['col1'] ?? '';
    $col2     = $_POST['col2'] ?? '';
    $folder   = safeTablePart($_POST['saveas_folder'] ?? '');
    $name     = safeTablePart($_POST['saveas_name'] ?? '');
    $overwrite = !empty($_POST['saveas_overwrite']);

    if ($srcTable === '' || $col1 === '' || $col2 === '') {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Missing table/column info.</div>";
    } elseif ($folder === '' || $name === '') {
        echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Please enter both Folder and New name.</div>";
    } else {
        $newTable = "{$username}_{$folder}_{$name}";
        if (cloneTableLike($conn, $srcTable, $newTable, $overwrite)) {
            // Insert current edited rows into the first two columns
            $rows = $_POST['rows'] ?? [];
            $sql = "INSERT INTO `".$conn->real_escape_string($newTable)."`
                    (`".$conn->real_escape_string($col1)."`, `".$conn->real_escape_string($col2)."`) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo "<div class='content' style='color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin:10px 0;'>Prepare failed: ".htmlspecialchars($conn->error)."</div>";
            } else {
                foreach ($rows as $r) {
                    if (!empty($r['delete'])) continue;
                    $v1 = trim($r['col1'] ?? ''); $v2 = trim($r['col2'] ?? '');
                    if ($v1 === '' || $v2 === '') continue;
                    $stmt->bind_param('ss', $v1, $v2);
                    $stmt->execute();
                }
                if (!empty($_POST['new_row']['col1']) || !empty($_POST['new_row']['col2'])) {
                    $nv1 = trim($_POST['new_row']['col1'] ?? '');
                    $nv2 = trim($_POST['new_row']['col2'] ?? '');
                    if ($nv1 !== '' && $nv2 !== '') { $stmt->bind_param('ss', $nv1, $nv2); $stmt->execute(); }
                }
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($newTable));
                exit;
            }
        }
    }
}

// -------------------------------------------
// Build folder list for explorer
// -------------------------------------------
// -------------------------------------------
// Build folder list for explorer (MAIN UI)
// - Shows ONLY normal (non-quiz) tables in Shared
// -------------------------------------------
function getUserFoldersAndTables(mysqli $conn, string $username): array {
    $all = [];

    // 1) User's own tables (normal namespace: <username>_folder_file...)
    $res = $conn->query("SHOW TABLES");
    while ($res && ($row = $res->fetch_array())) {
        $t = $row[0];
        if (stripos($t, $username . '_') === 0) {
            $suffix = substr($t, strlen($username) + 1);
            $suffix = preg_replace('/_+/', '_', $suffix);
            $parts  = explode('_', $suffix, 2);
            $folder = (count($parts) === 2 && trim($parts[0]) !== '') ? $parts[0] : 'Uncategorized';
            $file   = (count($parts) === 2) ? $parts[1] : $suffix;
            $all[$folder][] = ['table_name'=>$t, 'display_name'=>$file];
        }
    }

    // 2) Built-ins in Shared
    $all['Shared'][] = ['table_name'=>'difficult_words', 'display_name'=>'Difficult Words'];
    $all['Shared'][] = ['table_name'=>'mastered_words',  'display_name'=>'Mastered Words'];

    // Helper to add a non-quiz shared table into Shared (owner_prefix formatting)
    $addShared = function(string $t, string $owner) use (&$all, $conn) {
        // Only NON-quiz tables here
        if (strpos($t, 'quiz_choices_') === 0) return;

        // ensure table exists; auto-clean dead pointers
        $exists = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
        if (!$exists || $exists->num_rows === 0) {
            // clean both public & private pointers for good measure
            if ($stmt = $conn->prepare("DELETE FROM shared_tables WHERE table_name=?")) {
                $stmt->bind_param('s', $t); $stmt->execute(); $stmt->close();
            }
            if ($conn->query("SHOW TABLES LIKE 'shared_tables_private'") && $conn->affected_rows > -INF) {
                if ($stmt = $conn->prepare("DELETE FROM shared_tables_private WHERE table_name=?")) {
                    $stmt->bind_param('s', $t); $stmt->execute(); $stmt->close();
                }
            }
            return;
        }

        // display as "owner_suffix"
        if (stripos($t, $owner . '_') === 0) {
            $suffix  = substr($t, strlen($owner) + 1);   // folder_sub_file
            $display = $owner . '_' . $suffix;
        } else {
            $display = $t;
        }
        $all['Shared'][] = ['table_name'=>$t, 'display_name'=>$display];
    };

    // 3) Public shares (non-quiz only)
    $seen = [];
    $shares = $conn->query("SELECT table_name, owner FROM shared_tables");
    while ($shares && ($s = $shares->fetch_assoc())) {
        $t = $s['table_name']; $owner = strtolower($s['owner']);
        if (isset($seen[$t])) continue;
        $seen[$t] = true;
        $addShared($t, $owner);
    }

    // 4) Private shares for me (non-quiz only), if table exists and we know my identity
    $me = strtolower($username);
    $myEmail = null;
    if (!empty($_SESSION['email'])) {
        $myEmail = strtolower($_SESSION['email']);
    } else {
        if ($stmt = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1")) {
            $stmt->bind_param('s', $me);
            if ($stmt->execute()) {
                $r = $stmt->get_result(); $row = $r ? $r->fetch_assoc() : null;
                if (!empty($row['email'])) $myEmail = strtolower($row['email']);
            }
            $stmt->close();
        }
    }

    // Only run if the private-share table exists
    $hasPrivate = false;
    $chk = $conn->query("SHOW TABLES LIKE 'shared_tables_private'");
    if ($chk && $chk->num_rows > 0) $hasPrivate = true;

    if ($hasPrivate && ($me || $myEmail)) {
        if ($myEmail) {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_email IS NOT NULL AND LOWER(target_email)=?)
                    OR (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('ss', $myEmail, $me);
        } else {
            $stmt = $conn->prepare("
                SELECT table_name, owner
                  FROM shared_tables_private
                 WHERE (target_username IS NOT NULL AND target_username=?)
            ");
            $stmt->bind_param('s', $me);
        }
        if ($stmt && $stmt->execute()) {
            $rp = $stmt->get_result();
            while ($rp && ($p = $rp->fetch_assoc())) {
                $t = $p['table_name']; $owner = strtolower($p['owner']);
                if (isset($seen[$t])) continue;
                $seen[$t] = true;
                $addShared($t, $owner);
            }
            $stmt->close();
        }
    }

    return $all;
}


// -------------------------------------------
// Page state & render
// -------------------------------------------
$username = strtolower($_SESSION['username'] ?? '');
$conn->set_charset("utf8mb4");
// NEW: find user's email for private share resolution
$userEmail = getCurrentUserEmail($conn, $username);

// Build folder structure (no extra duplicate built-ins here)
$folders = getUserFoldersAndTables($conn, $username, $userEmail);

// Prepare folder data for JS in file_explorer.php
$folderData = [];
foreach ($folders as $folder => $tableList) {
    foreach ($tableList as $entry) {
        $folderData[$folder][] = [
            'table' => $entry['table_name'],
            'display' => $entry['display_name']
        ];
    }
}

// Selected table logic
$selectedFullTable = $_POST['table'] ?? $_GET['table'] ?? '';
$column1 = '';
$column2 = '';
$heading1 = '';
$heading2 = '';
$res = false;
if (!empty($selectedFullTable)) {
    $res = $conn->query("SELECT * FROM `$selectedFullTable`");
    if ($res !== false) {
        $columns = $conn->query("SHOW COLUMNS FROM `$selectedFullTable`");
        if ($columns && $columns->num_rows >= 2) {
            $colData = $columns->fetch_all(MYSQLI_ASSOC);
            $column1 = $colData[0]['Field'];
            $column2 = $colData[1]['Field'];
            $heading1 = $column1;
            $heading2 = $column2;
        }
        $_SESSION['table'] = $selectedFullTable;
        $_SESSION['col1'] = $column1;
        $_SESSION['col2'] = $column2;
    }
}

// Output page
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Manage Tables</title>";
include 'styling.php';
echo "</head><body>";

echo "<div class='content'>";
echo "👤 Přihlášený uživatel " . htmlspecialchars($_SESSION['username'] ?? '') . " | <a href='logout.php'>Odhlásit</a><br><br>";
echo "<h2> Čtení a editace slovníčků </h2>";

// Include the reusable file explorer
include 'file_explorer.php';

echo "<br><br>";

// Table editing logic
if (!empty($selectedFullTable) && $res !== false) {
    echo "<h3>Selected Table: " . htmlspecialchars($selectedFullTable) . "</h3>";

    $builtInReadOnly = in_array($selectedFullTable, ['difficult_words', 'mastered_words'], true);
    $ownerOfShare    = share_owner($conn, $selectedFullTable);
    $readonlyShared  = $ownerOfShare && (strtolower($ownerOfShare) !== strtolower($_SESSION['username'] ?? ''));
    $isSharedTable   = $builtInReadOnly || $readonlyShared;

    $audioMp3 = "cache/$selectedFullTable.mp3";
    $audioWav = "cache/$selectedFullTable.wav";
    $buttonStyle = "style=\"border:2px solid black; background:none; color:black; font-size:0.8em; padding:8px 14px; border-radius:4px; cursor:pointer;\"";

    if (file_exists($audioMp3) || file_exists($audioWav)) {
        // prefer MP3 if it exists, otherwise use WAV
        $src = file_exists($audioMp3) ? $audioMp3 : $audioWav;

        echo '<audio controls src="' . htmlspecialchars($src, ENT_QUOTES) . '" style="max-width:100%;"></audio><br>';

        echo "<a href='" . htmlspecialchars($src, ENT_QUOTES) . "' download><button $buttonStyle>⇩ Stáhnout audio " .
            (substr($src, -4) === '.mp3' ? 'MP3' : 'WAV') . "</button></a> | ";

        echo "<form method='POST' action='' style='display:inline;' onsubmit=\"return confirm('Really delete the audio file for this table?');\">";
        echo "  <input type='hidden' name='delete_audio_file' value='" . htmlspecialchars($selectedFullTable) . "'>";
        echo "  <button type='submit' $buttonStyle>🗑️ Smazat audio</button>";
        echo "</form><br><br>";

    } else {
        echo "<em>Pro tento slovníček zatím není audio nahrávka.</em><br><br>";
        echo "<a href='generate_mp3_google_ssml.php'><button $buttonStyle>🎧 Vytvořit MP3</button></a> ";
        // echo "<a href='generate_wav_batched.php'><button $buttonStyle>🎧 Vytvořit MP3 (vyber hlasy)</button></a> ";
    }

    if (!$isSharedTable) {
        echo "<form method='POST' action='update_table.php'>";
        echo "<input type='hidden' name='table' value='" . htmlspecialchars($selectedFullTable) . "'>";
        echo "<input type='hidden' name='col1' value='" . htmlspecialchars($column1) . "'>";
        echo "<input type='hidden' name='col2' value='" . htmlspecialchars($column2) . "'>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th><th>Action</th></tr>";
        $res->data_seek(0);
        $i = 0;
        while ($res && ($row = $res->fetch_assoc())) {
            echo "<tr>";
            echo "<td><textarea name='rows[$i][col1]' oninput='autoResize(this)'>" . htmlspecialchars($row[$column1]) . "</textarea></td>";
            echo "<td><textarea name='rows[$i][col2]' oninput='autoResize(this)'>" . htmlspecialchars($row[$column2]) . "</textarea></td>";
            echo "<td><input type='checkbox' name='rows[$i][delete]'> Delete</td>";
            echo "<input type='hidden' name='rows[$i][orig_col1]' value='" . htmlspecialchars($row[$column1]) . "'>";
            echo "<input type='hidden' name='rows[$i][orig_col2]' value='" . htmlspecialchars($row[$column2]) . "'>";
            echo "</tr>";
            $i++;
        }
        echo "<tr><td><textarea name='new_row[col1]' placeholder='New " . htmlspecialchars($heading1) . "' oninput='autoResize(this)'></textarea></td>";
        echo "<td><textarea name='new_row[col2]' placeholder='New " . htmlspecialchars($heading2) . "' oninput='autoResize(this)'></textarea></td><td></td></tr>";
        echo "</table><br>";
        echo "<button type='submit'>💾 Uložit změny</button>";

        // Save As UI
        echo "<div style='margin-top:12px; padding:10px; border:1px solid #e2e8f0; border-radius:8px;'>";
        echo "<strong>Save As…</strong>";
        echo "<div style='display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:6px;'>";
        echo "  <label>Folder: <input type='text' name='saveas_folder' placeholder='e.g., animals' style='padding:6px;'></label>";
        echo "  <label>New name: <input type='text' name='saveas_name' placeholder='e.g., de_en_small' style='padding:6px;'></label>";
        echo "  <label title='If checked and table exists, it will be replaced'><input type='checkbox' name='saveas_overwrite' value='1'> Overwrite if exists</label>";
        echo "  <button type='submit' name='action' value='save_as' formaction='main.php' formmethod='post' ".
             "style='padding:8px 12px; background:#2563eb; color:#fff; border:none; border-radius:6px;'>Uložit jako</button>";
        echo "</div>";
        echo "<div style='font-size:12px; color:#475569; margin-top:6px;'>";
        echo "  Will create <code>".htmlspecialchars(strtolower($_SESSION['username'] ?? 'user'))."_ADRESÁŘ_SOUBOR</code>";
        echo "</div>";
        echo "</div>";

        echo "</form><br>";

        if (!in_array($selectedFullTable, ['difficult_words', 'mastered_words', 'users'], true)) {
            echo "<form method='POST' action='' onsubmit=\"return confirm('Opravdu smazat tabulku: ".htmlspecialchars($selectedFullTable)." ?');\">";
            echo "<input type='hidden' name='delete_table' value='" . htmlspecialchars($selectedFullTable) . "'>";
            echo "<button type='submit' class='delete-button'>🗑️ Smazat tabulku</button>";
            echo "</form><br>";
        }
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>" . htmlspecialchars($heading1) . "</th><th>" . htmlspecialchars($heading2) . "</th></tr>";
        $res->data_seek(0);
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row[$column1]) . "</td>";
            echo "<td>" . htmlspecialchars($row[$column2]) . "</td>";
            echo "</tr>";
        }
        echo "</table><br><em>Tato tabulku je jen ke čtení.</em><br><br>";
    }
}

echo "</div>";

?>
<script>
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.overflow = 'hidden';
    textarea.style.height = textarea.scrollHeight + 'px';
}
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach(autoResize);
});
</script>
</body></html>
