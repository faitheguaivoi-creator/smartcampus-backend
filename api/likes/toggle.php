<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth   = requireAuth();
$userId = $auth['user_id'];    // fixed: was $auth['id']
$body   = json_decode(file_get_contents('php://input'), true);
$postId = (int)($body['post_id'] ?? 0);

if (!$postId) sendError('post_id is required.');

$db = getDB();

// Check if post exists
$stmt = $db->prepare('SELECT id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
if (!$stmt->fetch()) sendError('Post not found.', 404);

// Check if already liked
$stmt = $db->prepare('SELECT id FROM likes WHERE post_id = ? AND user_id = ?');
$stmt->execute([$postId, $userId]);
$existing = $stmt->fetch();

if ($existing) {
    // Unlike
    $db->prepare('DELETE FROM likes WHERE post_id = ? AND user_id = ?')
       ->execute([$postId, $userId]);
    $liked = false;
} else {
    // Like
    $db->prepare('INSERT INTO likes (post_id, user_id) VALUES (?, ?)')
       ->execute([$postId, $userId]);
    $liked = true;
}

// Get updated like count
$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE post_id = ?');
$stmt->execute([$postId]);
$likeCount = (int) $stmt->fetch()['cnt'];

sendSuccess(['liked' => $liked, 'like_count' => $likeCount]);
?>