
<?php
$host = 'sql113.byethost15.com';
$user = 'b15_39452825';
$password = '5761VkRpAk';
$database = 'b15_39452825_KremlikDatabase01';

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//- db.php
//  database connection
