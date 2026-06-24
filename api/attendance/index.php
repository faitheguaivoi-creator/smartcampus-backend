<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth   = requireAuth();

// ── GET: List all sessions for a course ───────────
if ($method === 'GET') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    if (!$courseId) sendError('course_id is required.');

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            ats.id,
            ats.session_code,
            ats.session_date,
            ats.start_time,
            ats.end_time,
            ats.is_active,
            ats.created_at,
            COUNT(ar.id) AS present_count
        FROM attendance_sessions ats
        LEFT JOIN attendance_records ar ON ar.session_id = ats.id
        WHERE ats.course_id = ?
        GROUP BY ats.id
        ORDER BY ats.session_date DESC, ats.start_time DESC
    ");
    $stmt->execute([$courseId]);
    $sessions = $stmt->fetchAll();

    foreach ($sessions as &$s) {
        $s['id']            = (int)  $s['id'];
        $s['is_active']     = (bool) $s['is_active'];
        $s['present_count'] = (int)  $s['present_count'];
    }

    sendSuccess(['sessions' => $sessions]);
}

// ── POST: Create a new session (lecturer only) ────
elseif ($method === 'POST') {
    if ($auth['role'] !== 'lecturer') {
        sendError('Only lecturers can create attendance sessions.', 403);
    }

    $body     = json_decode(file_get_contents('php://input'), true);
    $courseId = (int)($body['course_id'] ?? 0);

    if (!$courseId) sendError('course_id is required.');

    $db = getDB();

    // Verify lecturer owns this course
    $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND lecturer_id = ?');
    $stmt->execute([$courseId, $auth['user_id']]);   // FIXED: $auth['user_id']
    if (!$stmt->fetch()) sendError('Course not found or access denied.', 403);

    // Generate a unique 6-character session code
    $code = generateSessionCode($db);

    $stmt = $db->prepare("
        INSERT INTO attendance_sessions
            (course_id, session_code, session_date, start_time, is_active)
        VALUES
            (?, ?, CURDATE(), CURTIME(), 1)
    ");
    $stmt->execute([$courseId, $code]);
    $sessionId = (int) $db->lastInsertId();

    sendSuccess([
        'session' => [
            'id'           => $sessionId,
            'session_code' => $code,
            'session_date' => date('Y-m-d'),
            'start_time'   => date('H:i:s'),
            'is_active'    => true,
            'present_count'=> 0,
        ]
    ], 201);
}
else {
    sendError('Method not allowed.', 405);
}

// Helper: generate a unique 6-character alphanumeric code
function generateSessionCode(PDO $db): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare('SELECT id FROM attendance_sessions WHERE session_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}
?>