<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed.', 405);

$auth = requireAuth();

if ($auth['role'] !== 'lecturer') {
    sendError('Only lecturers can close sessions.', 403);
}

$body      = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($body['session_id'] ?? 0);

if (!$sessionId) sendError('session_id is required.');

$db = getDB();

// Verify the session belongs to one of this lecturer's courses
$stmt = $db->prepare("
    SELECT ats.id
    FROM attendance_sessions ats
    JOIN courses c ON c.id = ats.course_id
    WHERE ats.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$sessionId, $auth['user_id']]);   // FIXED: $auth['user_id']
if (!$stmt->fetch()) sendError('Session not found or access denied.', 403);

$db->prepare(
    'UPDATE attendance_sessions SET is_active = 0, end_time = CURTIME() WHERE id = ?'
)->execute([$sessionId]);

sendSuccess(['message' => 'Session closed successfully.']);
?>