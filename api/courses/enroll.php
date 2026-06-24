<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth = requireAuth();
if ($auth['role'] !== 'student') sendError('Only students can enroll.', 403);

$body = json_decode(file_get_contents('php://input'), true);
$courseCode = strtoupper(trim($body['course_code'] ?? ''));

if (!$courseCode) sendError('Course code required.');

$db = getDB();
$stmt = $db->prepare('SELECT id, title FROM courses WHERE code = ?');
$stmt->execute([$courseCode]);
$course = $stmt->fetch();
if (!$course) sendError('Invalid course code.', 404);

$stmt = $db->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?');
$stmt->execute([$course['id'], $auth['user_id']]);
if ($stmt->fetch()) sendError('Already enrolled.');

$stmt = $db->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
$stmt->execute([$course['id'], $auth['user_id']]);

sendSuccess(['message' => "Enrolled in {$course['title']}"], 201);
?>