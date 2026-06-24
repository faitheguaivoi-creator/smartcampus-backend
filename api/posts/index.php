<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Fetch paginated feed ──────────────────────
if ($method === 'GET') {
    $auth   = requireAuth();
    $userId = $auth['user_id'];
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    $db  = getDB();
    $sql = "
        SELECT
            p.id,
            p.content,
            p.image_url,
            p.created_at,
            u.id        AS author_id,
            u.name      AS author_name,
            pr.avatar_url,
            pr.department,
            (SELECT COUNT(*) FROM likes    WHERE post_id = p.id)                    AS like_count,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id)                    AS comment_count,
            (SELECT COUNT(*) FROM likes    WHERE post_id = p.id AND user_id = :uid) AS liked_by_me
        FROM posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN profiles pr ON pr.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT :lim OFFSET :off
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as &$post) {
        $post['like_count']    = (int)  $post['like_count'];
        $post['comment_count'] = (int)  $post['comment_count'];
        $post['liked_by_me']   = (bool) $post['liked_by_me'];
        $post['author_id']     = (int)  $post['author_id'];
    }
    unset($post);

    sendSuccess(['posts' => array_values($posts), 'page' => $page]);
}

// ── POST: Create a new post ────────────────────────
elseif ($method === 'POST') {
    $auth    = requireAuth();
    $userId  = $auth['user_id'];
    $body    = json_decode(file_get_contents('php://input'), true);
    $content = trim($body['content'] ?? '');

    if (!$content)              sendError('Post content cannot be empty.');
    if (strlen($content) > 1000) sendError('Post cannot exceed 1000 characters.');

    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO posts (user_id, content) VALUES (?, ?)');
    $stmt->execute([$userId, $content]);
    $postId = (int) $db->lastInsertId();

    $stmt = $db->prepare("
        SELECT p.id, p.content, p.image_url, p.created_at,
               u.id AS author_id, u.name AS author_name,
               pr.avatar_url, pr.department
        FROM posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN profiles pr ON pr.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$postId]);   // FIXED: was `postId` missing `$`
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    $post['like_count']    = 0;
    $post['comment_count'] = 0;
    $post['liked_by_me']   = false;
    $post['author_id']     = (int) $post['author_id'];

    sendSuccess(['post' => $post], 201);
}

else {
    sendError('Method not allowed.', 405);
}
?>