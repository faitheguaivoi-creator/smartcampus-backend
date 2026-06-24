<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth = requireAuth();
$userId = $auth['user_id'];

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    sendError('No image file uploaded.', 400);
}

$file = $_FILES['avatar'];
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed)) sendError('Only JPG, PNG, GIF, WEBP allowed.', 400);
if ($file['size'] > 2 * 1024 * 1024) sendError('Image must be < 2MB.', 400);

$uploadDir = __DIR__ . '/../../uploads/profiles/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'user_' . $userId . '_' . time() . '.' . $ext;
$target = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) sendError('Failed to save image.', 500);

$avatarUrl = '/SmartCampus/backend/uploads/profiles/' . $filename;
$db = getDB();
$stmt = $db->prepare("UPDATE profiles SET avatar_url = ? WHERE user_id = ?");
$stmt->execute([$avatarUrl, $userId]);

sendSuccess(['avatar_url' => $avatarUrl]);
?>