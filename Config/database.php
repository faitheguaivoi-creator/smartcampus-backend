<?php
error_reporting(0);
ini_set('display_errors', 0);

$host = getenv('DB_HOST') ?: 'sql210.infinityfree.com';
$name = getenv('DB_NAME') ?: 'if0_42247296_smartcampus_db';
$user = getenv('DB_USER') ?: 'if0_42247296';
$pass = getenv('DB_PASS') ?: 'ASSURANCE8710';

define('DB_HOST', $host);
define('DB_NAME', $name);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
?>
