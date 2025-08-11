<?php
require_once 'session.php'; // for $_SESSION['username']
require_once 'db.php';

header('Content-Type: application/json');

// ----- helper: mirror translator.php -----
function build_user_prefixed_name(string $name, string $username): string {
    $user = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$username));
    if ($user === '') $user = 'user';

    $name = trim($name);
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);

    $prefix = $user . '_';
    if (stripos($name, $prefix) !== 0) {
        $name = $prefix . $name;
    }

    return substr($name, 0, 64); // MySQL table name limit
}

// accept both ?name= and older ?table=
$rawName = $_GET['name'] ?? $_GET['table'] ?? '';
if ($rawName === '') {
    echo json_encode(['error' => 'No table name provided']);
    exit;
}

// resolve final username-prefixed name
$username = $_SESSION['username'] ?? 'user';
$finalTable = build_user_prefixed_name($rawName, $username);

// connect
$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// check existence
$esc = $conn->real_escape_string($finalTable);
$res = $conn->query("SHOW TABLES LIKE '$esc'");
if ($res === false) {
    echo json_encode(['error' => 'Query failed']);
} else {
    $exists = ($res->num_rows > 0);
    echo json_encode(['exists' => $exists, 'final' => $finalTable]);
}

$conn->close();
