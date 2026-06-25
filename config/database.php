<?php
error_reporting(0);
ini_set('display_errors', 0);

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'smartcampus';
$dbUser = 'root';
$dbPass = '';

// Parse database URL if provided by hosting environment
$dbUrl = getenv('DATABASE_URL') ?: (getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL'));
if ($dbUrl) {
    $urlParts = parse_url($dbUrl);
    if ($urlParts) {
        $dbHost = $urlParts['host'] ?? $dbHost;
        $dbPort = (string)($urlParts['port'] ?? $dbPort);
        $dbUser = $urlParts['user'] ?? $dbUser;
        $dbPass = $urlParts['pass'] ?? $dbPass;
        $dbName = ltrim($urlParts['path'] ?? '', '/') ?: $dbName;
    }
} else {
    // Fallback to individual env variables
    $dbHost = getenv('DB_HOST') ?: $dbHost;
    $dbPort = getenv('DB_PORT') ?: $dbPort;
    $dbName = getenv('DB_NAME') ?: $dbName;
    $dbUser = getenv('DB_USER') ?: $dbUser;
    $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $dbPass;
}

define('DB_HOST',    $dbHost);
define('DB_PORT',    $dbPort);
define('DB_NAME',    $dbName);
define('DB_USER',    $dbUser);
define('DB_PASS',    $dbPass);
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL',     getenv('DB_SSL') === 'true');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];

            // Aiven (and most cloud DBs) require SSL
            if (DB_SSL) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                // If a CA cert path is provided, use it
                $caCert = getenv('DB_CA_CERT_PATH');
                if ($caCert && file_exists($caCert)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caCert;
                }
            }

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
?>
