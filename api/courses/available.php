<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendError('Method not allowed.', 405);

$auth = requireAuth();
if ($auth['role'] !== 'student') sendError('Only students can view available courses.', 403);

$db = getDB();
$stmt = $db->prepare("
    SELECT c.id, c.code, c.title, u.name AS lecturer_name
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE c.id NOT IN (
        SELECT course_id FROM course_enrollments WHERE student_id = ?
    )
    ORDER BY c.title
");
$stmt->execute([$auth['user_id']]);
$courses = $stmt->fetchAll();

sendSuccess(['courses' => $courses]);
?>