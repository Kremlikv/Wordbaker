<?php
// === Configuration ===
$host = 'sql113.byethost15.com';
$username = 'b15_39452825';
$password = '5761VkRpAk';
$database = 'b15_39452825_KremlikDatabase02';

// === Connect to MySQL ===
$conn = new mysqli($host, $username, $password, $database);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === Get all tables ===
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// === Start SQL output ===
$dump = "-- FULL Database Backup\n";
$dump .= "-- Database: `$database`\n";
$dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
$dump .= "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    // === Create table structure ===
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res && $row = $res->fetch_assoc()) {
        $dump .= "-- Structure for table `$table`\n";
        $dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $dump .= $row['Create Table'] . ";\n\n";
    }

    // === Dump table data ===
    $res = $conn->query("SELECT * FROM `$table`");
    if ($res && $res->num_rows > 0) {
        $columns = array_map(function($f) { return "`" . $f->name . "`"; }, $res->fetch_fields());

        $dump .= "-- Data for table `$table`\n";
        while ($row = $res->fetch_assoc()) {
            $values = array_map(function($val) use ($conn) {
                if ($val === null) return "NULL";
                return "'" . $conn->real_escape_string($val) . "'";
            }, array_values($row));

            $dump .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
        $dump .= "\n";
    }
}

$conn->close();

// === Output SQL as file ===
$filename = $database . '_full_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql');
header("Content-Disposition: attachment; filename=\"$filename\"");
echo $dump;
exit;
?>
