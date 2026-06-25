<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth = requireAuth();

if ($auth['role'] !== 'student') {
    sendError('Only students can mark attendance.', 403);
}

$body = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($body['session_code'] ?? ''));

if (!$code) sendError('Session code is required.');

$db = getDB();

// Find the session by code
$stmt = $db->prepare("
    SELECT ats.id, ats.course_id, ats.is_active, c.title AS course_title
    FROM attendance_sessions ats
    JOIN courses c ON c.id = ats.course_id
    WHERE ats.session_code = ?
");
$stmt->execute([$code]);
$session = $stmt->fetch();

if (!$session) sendError('Invalid session code. Please check and try again.');

if (!$session['is_active']) {
    sendError('This attendance session has been closed by the lecturer.');
}

// Check the student is enrolled in this course
$stmt = $db->prepare(
    'SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?'
);
$stmt->execute([$session['course_id'], $auth['user_id']]);   // FIXED: $auth['user_id']
if (!$stmt->fetch()) {
    sendError('You are not enrolled in this course.');
}

// Check if already marked
$stmt = $db->prepare(
    'SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?'
);
$stmt->execute([$session['id'], $auth['user_id']]);   // FIXED: $auth['user_id']
if ($stmt->fetch()) {
    sendError('You have already marked attendance for this session.');
}

// Mark as present
$stmt = $db->prepare(
    "INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, 'present')"
);
$stmt->execute([$session['id'], $auth['user_id']]);   // FIXED: $auth['user_id']

sendSuccess([
    'message'      => "Attendance marked for {$session['course_title']}.",
    'course_title' => $session['course_title'],
    'status'       => 'present',
]);
?>