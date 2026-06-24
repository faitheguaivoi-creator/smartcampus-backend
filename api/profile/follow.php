<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth = requireAuth();
$followerId = $auth['user_id'];          // ✅ fixed: was $auth['id']
$body = json_decode(file_get_contents('php://input'), true);
$targetId = (int)($body['user_id'] ?? 0);

if (!$targetId) sendError('user_id is required.');
if ($targetId === $followerId) sendError('You cannot follow yourself.');

$db = getDB();

// Check if already following
$stmt = $db->prepare('SELECT id FROM follows WHERE follower_id = ? AND following_id = ?');
$stmt->execute([$followerId, $targetId]);
$exists = $stmt->fetch();

if ($exists) {
    // Unfollow
    $db->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?')
       ->execute([$followerId, $targetId]);
    $following = false;
} else {
    // Follow
    $db->prepare('INSERT INTO follows (follower_id, following_id) VALUES (?, ?)')
       ->execute([$followerId, $targetId]);
    $following = true;
}

// Get updated follower count for the target user
$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?');
$stmt->execute([$targetId]);
$count = (int) $stmt->fetch()['cnt'];

sendSuccess(['following' => $following, 'follower_count' => $count]);
?>