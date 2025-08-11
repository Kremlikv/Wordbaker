<?php
// transform database 1 to utf8mb4_czech_ci'
// CONFIGURATION
$host = 'sql113.byethost15.com'; // Replace with your ByetHost MySQL host
$user = 'b15_39452825';         // Your DB username
$pass = '5761VkRpAk';        // Your DB password
$db   = 'b15_39452825_KremlikDatabase01';
$targetCollation = 'utf8mb4_czech_ci';
$targetCharset = 'utf8mb4';

// CONNECT
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connemct_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// SET NAMES
$conn->set_charset('utf8mb4');

// ALTER DATABASE
$conn->query("ALTER DATABASE `$db` CHARACTER SET $targetCharset COLLATE $targetCollation");
echo "✅ Database collation updated.\n";

// GET TABLES
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// CONVERT EACH TABLE
foreach ($tables as $table) {
    echo "🔄 Converting table `$table`...\n";
    $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET $targetCharset COLLATE $targetCollation";
    if ($conn->query($sql)) {
        echo "  ✅ Success\n";
    } else {
        echo "  ❌ Error: " . $conn->error . "\n";
    }
}

$conn->close();
echo "🎉 Done.\n";
?>
