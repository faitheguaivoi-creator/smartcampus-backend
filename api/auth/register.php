<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$body = json_decode(file_get_contents('php://input'), true);
$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$password = trim($body['password'] ?? '');
$role = trim($body['role'] ?? 'student');

if (!$name || !$email || !$password) sendError('Name, email and password are required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendError('Invalid email format.');
if (strlen($password) < 6) sendError('Password must be at least 6 characters.');
if (!in_array($role, ['student', 'lecturer'])) $role = 'student';

$db = getDB();
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) sendError('Email already exists.');

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
$stmt->execute([$name, $email, $hash, $role]);
$userId = (int) $db->lastInsertId();

// Create empty profile
$stmt = $db->prepare('INSERT INTO profiles (user_id) VALUES (?)');
$stmt->execute([$userId]);

$token = generateJWT([
    'user_id' => $userId,
    'name' => $name,
    'email' => $email,
    'role' => $role,
]);

sendSuccess([
    'token' => $token,
    'user' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ],
], 201);
?>