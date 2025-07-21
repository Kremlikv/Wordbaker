
<?php
$host = 'mysql-victork.alwaysdata.net';
$user = 'victork';
$password = 'gLWdK.Q.4xtHw.2';
$database = 'victork_database1';

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//- db.php
//  database connection
