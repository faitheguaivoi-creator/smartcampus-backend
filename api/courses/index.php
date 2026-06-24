<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth   = requireAuth();

// ── GET ───────────────────────────────────────────
if ($method === 'GET') {
    $db = getDB();

    if ($auth['role'] === 'lecturer') {
        // Lecturer sees only their own courses
        $stmt = $db->prepare("
            SELECT
                c.id,
                c.title,
                c.code,
                c.description,
                c.created_at,
                COUNT(DISTINCT ce.student_id) AS student_count,
                COUNT(DISTINCT ats.id)        AS session_count
            FROM courses c
            LEFT JOIN course_enrollments ce ON ce.course_id = c.id
            LEFT JOIN attendance_sessions ats ON ats.course_id = c.id
            WHERE c.lecturer_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$auth['user_id']]);

    } else {
        // Student sees courses they are enrolled in
        $stmt = $db->prepare("
            SELECT
                c.id,
                c.title,
                c.code,
                c.description,
                c.created_at,
                u.name AS lecturer_name
            FROM courses c
            JOIN course_enrollments ce ON ce.course_id = c.id
            JOIN users u ON u.id = c.lecturer_id
            WHERE ce.student_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$auth['user_id']]);
    }

    $courses = $stmt->fetchAll();

    foreach ($courses as &$course) {
        $course['id']            = (int) $course['id'];
        $course['student_count'] = (int) ($course['student_count'] ?? 0);
        $course['session_count'] = (int) ($course['session_count'] ?? 0);
    }

    sendSuccess(['courses' => $courses]);
}

// ── POST: Create a new course (lecturer only) ─────
elseif ($method === 'POST') {
    if ($auth['role'] !== 'lecturer') {
        sendError('Only lecturers can create courses.', 403);
    }

    $body        = json_decode(file_get_contents('php://input'), true);
    $title       = trim($body['title']       ?? '');
    $code        = strtoupper(trim($body['code'] ?? ''));
    $description = trim($body['description'] ?? '');

    if (!$title) sendError('Course title is required.');
    if (!$code)  sendError('Course code is required.');

    $db = getDB();

    // Check if course code already exists
    $stmt = $db->prepare('SELECT id FROM courses WHERE code = ?');
    $stmt->execute([$code]);
    if ($stmt->fetch()) sendError('A course with this code already exists.');

    $stmt = $db->prepare(
        'INSERT INTO courses (lecturer_id, title, code, description)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$auth['user_id'], $title, $code, $description]);
    $courseId = (int) $db->lastInsertId();

    sendSuccess([
        'course' => [
            'id'            => $courseId,
            'title'         => $title,
            'code'          => $code,
            'description'   => $description,
            'student_count' => 0,
            'session_count' => 0,
        ]
    ], 201);
}

else {
    sendError('Method not allowed.', 405);
}
?>