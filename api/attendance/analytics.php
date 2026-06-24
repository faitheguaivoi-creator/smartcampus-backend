<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendError('Method not allowed.', 405);

$auth = requireAuth();
if ($auth['role'] !== 'lecturer') sendError('Only lecturers can view analytics.', 403);

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) sendError('course_id is required.');

$db = getDB();

// Verify lecturer owns this course
$stmt = $db->prepare('SELECT id, title FROM courses WHERE id = ? AND lecturer_id = ?');
$stmt->execute([$courseId, $auth['user_id']]);   // ✅ fixed: $auth['user_id']
$course = $stmt->fetch();
if (!$course) sendError('Course not found or access denied.', 403);

// Total enrolled students
$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM course_enrollments WHERE course_id = ?');
$stmt->execute([$courseId]);
$totalStudents = (int) $stmt->fetch()['cnt'];

// Total sessions
$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM attendance_sessions WHERE course_id = ?');
$stmt->execute([$courseId]);
$totalSessions = (int) $stmt->fetch()['cnt'];

// Per-session trend
$stmt = $db->prepare("
    SELECT
        ats.id,
        ats.session_date,
        ats.start_time,
        COUNT(ar.id) AS present_count,
        ROUND(COUNT(ar.id) / NULLIF(:enrolled, 0) * 100, 1) AS rate
    FROM attendance_sessions ats
    LEFT JOIN attendance_records ar ON ar.session_id = ats.id
    WHERE ats.course_id = :cid
    GROUP BY ats.id
    ORDER BY ats.session_date ASC, ats.start_time ASC
");
$stmt->bindValue(':enrolled', $totalStudents, PDO::PARAM_INT);
$stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
$stmt->execute();
$sessionTrend = $stmt->fetchAll();

foreach ($sessionTrend as &$s) {
    $s['id'] = (int) $s['id'];
    $s['present_count'] = (int) $s['present_count'];
    $s['rate'] = (float) $s['rate'];
}

// Class average
$classAverage = count($sessionTrend) > 0
    ? round(array_sum(array_column($sessionTrend, 'rate')) / count($sessionTrend), 1)
    : 0;

// At‑risk students (below 80%)
$stmt = $db->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        pr.matric_number,
        pr.level,
        COUNT(ar.id) AS attended,
        :total AS total_sessions,
        ROUND(COUNT(ar.id) / NULLIF(:total2, 0) * 100, 1) AS percentage
    FROM course_enrollments ce
    JOIN users u ON u.id = ce.student_id
    LEFT JOIN profiles pr ON pr.user_id = u.id
    LEFT JOIN attendance_records ar
        ON ar.student_id = u.id
        AND ar.session_id IN (SELECT id FROM attendance_sessions WHERE course_id = :cid)
    WHERE ce.course_id = :cid2
    GROUP BY u.id
    HAVING percentage < 80
    ORDER BY percentage ASC
");
$stmt->bindValue(':total', $totalSessions, PDO::PARAM_INT);
$stmt->bindValue(':total2', $totalSessions, PDO::PARAM_INT);
$stmt->bindValue(':cid', $courseId, PDO::PARAM_INT);
$stmt->bindValue(':cid2', $courseId, PDO::PARAM_INT);
$stmt->execute();
$atRiskStudents = $stmt->fetchAll();

foreach ($atRiskStudents as &$s) {
    $s['id'] = (int) $s['id'];
    $s['attended'] = (int) $s['attended'];
    $s['total_sessions'] = (int) $s['total_sessions'];
    $s['percentage'] = (float) $s['percentage'];
}

sendSuccess([
    'course' => $course,
    'total_students' => $totalStudents,
    'total_sessions' => $totalSessions,
    'class_average' => $classAverage,
    'session_trend' => $sessionTrend,
    'at_risk_students' => $atRiskStudents,
]);
?>