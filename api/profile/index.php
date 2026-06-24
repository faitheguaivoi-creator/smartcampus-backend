<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

$auth   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET /api/profile/index.php?user_id=5  (or own profile if no user_id)
if ($method === 'GET') {
    $userId = (int)($_GET['user_id'] ?? $auth['user_id']);

    // Fetch user + profile
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.role,
               p.bio, p.avatar_url, p.department, p.level, p.matric_number
        FROM users u
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) sendError('User not found', 404);

    // Get user's posts
    $stmt = $db->prepare("SELECT id, content, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $posts = $stmt->fetchAll();

    // Follow status and counts
    // After fetching user and posts, add:
$is_following = false;
if ($userId != $auth['user_id']) {
    $stmt = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$auth['user_id'], $userId]);
    $is_following = (bool)$stmt->fetch();
}
$stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$stmt->execute([$userId]);
$followers = (int)$stmt->fetch()['cnt'];

sendSuccess([
    'user' => $user,
    'posts' => $posts,
    'is_following' => $is_following,
    'followers' => $followers,
    'following' => $following, // if you also want following count
]);
}

// PUT /api/profile/index.php (update own profile)
elseif ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['name'] ?? '');
    $bio = trim($body['bio'] ?? '');
    $department = trim($body['department'] ?? '');
    $level = trim($body['level'] ?? '');
    $matric_number = trim($body['matric_number'] ?? '');

    if (!$name) sendError('Name is required');

    $db->beginTransaction();
    $db->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $auth['user_id']]);
    $db->prepare("INSERT INTO profiles (user_id, bio, department, level, matric_number)
                  VALUES (?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  bio = VALUES(bio), department = VALUES(department),
                  level = VALUES(level), matric_number = VALUES(matric_number)")
        ->execute([$auth['user_id'], $bio, $department, $level, $matric_number]);
    $db->commit();

    sendSuccess(['message' => 'Profile updated']);
}
else {
    sendError('Method not allowed', 405);
}
?>