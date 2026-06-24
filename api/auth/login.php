<?php
// backend/api/auth/login.php
// --------------------------------------------------
// POST /api/auth/login
//
// Expected JSON body:
// {
//   "email":    "emeka@smartcampus.edu",
//   "password": "SecurePass123"
// }
//
// Success response (200):
// { "success": true, "data": { "token": "...", "user": { ... } } }
//
// Error response (400/401):
// { "success": false, "message": "..." }
// --------------------------------------------------

require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../config/database.php';

setCorsHeaders();

// ── 1. Only accept POST requests ──────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

// ── 2. Read and decode the JSON body ──────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    sendError('Invalid JSON body.');
}

// ── 3. Validate required fields ───────────────────
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (!$email || !$password) {
    sendError('Email and password are required.');
}

// ── 4. Look up user by email ──────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.password_hash, u.role,
           p.avatar_url, p.department, p.level, p.matric_number
    FROM   users    u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE  u.email = ?
    LIMIT  1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

// ── 5. Verify password ────────────────────────────
// password_verify() checks the plain password against the bcrypt hash.
// We give the same vague error for both "user not found" and "wrong password"
// so attackers can't enumerate valid email addresses.
if (!$user || !password_verify($password, $user['password_hash'])) {
    sendError('Invalid email or password.', 401);
}

// ── 6. Generate JWT token ──────────────────────────
$token = generateJWT([
    'user_id' => $user['id'],
    'name'    => $user['name'],
    'email'   => $user['email'],
    'role'    => $user['role'],
]);

// ── 7. Return success response ────────────────────
// We never return password_hash to the client
sendSuccess([
    'token' => $token,
    'user'  => [
        'id'           => (int) $user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'role'         => $user['role'],
        'avatar_url'   => $user['avatar_url'],
        'department'   => $user['department'],
        'level'        => $user['level'],
        'matric_number'=> $user['matric_number'],
    ],
]);