<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') sendError('Method not allowed.', 405);

$auth   = requireAuth();
$userId = $auth['user_id'];    // fixed: was $auth['id']
$postId = (int)($_GET['id'] ?? 0);

if (!$postId) sendError('Post ID is required.');

$db = getDB();

// Check post exists and get owner
$stmt = $db->prepare('SELECT user_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) sendError('Post not found.', 404);
if ($post['user_id'] !== $userId && $auth['role'] !== 'admin') {
    sendError('You can only delete your own posts.', 403);
}

// Delete post (likes and comments will cascade if foreign keys are set)
$db->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);

sendSuccess(['message' => 'Post deleted successfully.']);
?>