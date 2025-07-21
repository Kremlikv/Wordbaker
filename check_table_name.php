<?php
require_once 'db.php'; 
// check_table_name.php

header('Content-Type: application/json');

$table = $_GET['table'] ?? '';
if (!$table) {
    echo json_encode(['error' => 'No table name provided']);
    exit;
}

// Sanitize table name to prevent SQL injection or malformed queries
$safeTable = preg_replace('/[^a-zA-Z0-9_]/', '_', $table);

// Connect to your MySQL database

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$result = $conn->query("SHOW TABLES LIKE '$safeTable'");

if ($result) {
    $exists = $result->num_rows > 0;
    echo json_encode(['exists' => $exists]);
} else {
    echo json_encode(['error' => 'Query failed']);
}

$conn->close();
