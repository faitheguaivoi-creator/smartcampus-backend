<?php
/**
 * One-time admin seed script.
 * Visit this URL ONCE, then DELETE or rename the file for security.
 * URL: https://smartcampus-backend-469a.onrender.com/create_admin.php
 */

// Simple secret key guard – change if you want extra protection
$secret = $_GET['secret'] ?? '';
if ($secret !== 'smartcampus_setup_2024') {
    http_response_code(403);
    die(json_encode(['error' => 'Forbidden. Pass ?secret=smartcampus_setup_2024']));
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$name     = 'Admin';
$email    = 'admin@smartcampus.com';
$password = 'Admin@1234';
$role     = 'admin';

try {
    $db = getDB();

    // Check if admin already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'status'  => 'already_exists',
            'message' => 'Admin account already exists.',
            'email'   => $email,
            'note'    => 'DELETE this file after use!',
        ]);
        exit;
    }

    // Insert admin user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = (int) $db->lastInsertId();

    // Create empty profile
    $stmt = $db->prepare('INSERT INTO profiles (user_id) VALUES (?)');
    $stmt->execute([$userId]);

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Admin account created!',
        'id'       => $userId,
        'name'     => $name,
        'email'    => $email,
        'password' => $password,
        'role'     => $role,
        'warning'  => '⚠️  DELETE this file from your server immediately!',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
