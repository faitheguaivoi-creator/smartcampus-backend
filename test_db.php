<?php
$host = '127.0.0.1';
$port = '3306
';
$db   = 'smartcampus';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo json_encode([
        'success' => true,
        'message' => 'Connected on port ' . $port
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}