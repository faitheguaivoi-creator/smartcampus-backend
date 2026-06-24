<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendError('Method not allowed.', 405);

$auth     = requireAuth();
$courseId = (int)($_GET['course_id'] ?? 0);

if (!$courseId) sendError('course_id is required.');

$db = getDB();

// Verify the lecturer owns this course
if ($auth['role'] === 'lecturer') {
    $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND lecturer_id = ?');
    $stmt->execute([$courseId, $auth['user_id']]);   // ✅ fixed
    if (!$stmt->fetch()) sendError('Course not found or access denied.', 403);
}

// Total sessions for this course
$stmt = $db->prepare('SELECT COUNT(*) AS total FROM attendance_sessions WHERE course_id = ?');
$stmt->execute([$courseId]);
$totalSessions = (int) $stmt->fetch()['total'];

// All enrolled students + attendance
$stmt = $db->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        pr.department,
        pr.level,
        pr.matric_number,
        COUNT(ar.id)                                        AS attended,
        :total                                              AS total_sessions,
        CASE
            WHEN :total2 = 0 THEN 0
            ELSE ROUND(COUNT(ar.id) / :total3 * 100, 1)
        END                                                 AS percentage
    FROM course_enrollments ce
    JOIN users u    ON u.id  = ce.student_id
    LEFT JOIN profiles pr ON pr.user_id = u.id
    LEFT JOIN attendance_records ar
           ON ar.student_id = u.id
          AND ar.session_id IN (
                SELECT id FROM attendance_sessions WHERE course_id = :cid
              )
    WHERE ce.course_id = :cid2
    GROUP BY u.id
    ORDER BY percentage ASC
");
$stmt->bindValue(':total',  $totalSessions, PDO::PARAM_INT);
$stmt->bindValue(':total2', $totalSessions, PDO::PARAM_INT);
$stmt->bindValue(':total3', $totalSessions, PDO::PARAM_INT);
$stmt->bindValue(':cid',    $courseId,      PDO::PARAM_INT);
$stmt->bindValue(':cid2',   $courseId,      PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

foreach ($students as &$s) {
    $s['id']             = (int)   $s['id'];
    $s['attended']       = (int)   $s['attended'];
    $s['total_sessions'] = (int)   $s['total_sessions'];
    $s['percentage']     = (float) $s['percentage'];
    $s['at_risk']        = $s['percentage'] < 80 && $s['total_sessions'] > 0;
}

sendSuccess([
    'students'       => $students,
    'total_sessions' => $totalSessions,
]);
?>