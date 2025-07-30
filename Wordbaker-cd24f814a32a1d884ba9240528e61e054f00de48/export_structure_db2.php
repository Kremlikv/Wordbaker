<?php
// === Configuration ===
$host = 'sql113.byethost15.com';
$username = 'b15_39452825';
$password = '5761VkRpAk';
$database = 'b15_39452825_KremlikDatabase02';

// === Connect to MySQL ===
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === Get all table names ===
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// === Get CREATE TABLE statements ===
$schema = "-- Database Structure Backup\n-- Database: `$database`\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $res->fetch_assoc();
    $schema .= "-- Structure for table `$table`\n";
    $schema .= $row['Create Table'] . ";\n\n";
}

// === Output as downloadable .sql file ===
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $database . '_structure_' . date('Ymd_His') . '.sql"');
echo $schema;

$conn->close();
exit;


/* Save this code as export_structure.php.

Upload it to your server (e.g., next to main.php).

Visit the page in your browser:https://kremlik.byethost15.com/export_structure.php

It will trigger a download like: your_database_structure_20250719_123456.sql

*/

?>
