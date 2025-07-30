<?php
$host = 'sql113.byethost15.com';
$user = 'b15_39452825';
$password = '5761VkRpAk';
$database = 'b15_39452825_KremlikDatabase02';

$conn_users = new mysqli($host, $user, $password, $database);
$conn_users->set_charset("utf8mb4");

if ($conn_users->connect_error) {
    die("User DB connection failed: " . $conn_users->connect_error);
}
?>

<!-- db_users.php-->
<!-- database connection-->
