<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendError('Method not allowed.', 405);

$auth = requireAuth();
$db   = getDB();

// ── STUDENT stats ─────────────────────────────────
if ($auth['role'] === 'student') {
    $userId = $auth['user_id'];

    // Enrolled courses
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM course_enrollments WHERE student_id = ?');
    $stmt->execute([$userId]);
    $enrolledCourses = (int) $stmt->fetch()['cnt'];

    // Overall attendance across ALL courses
    $stmt = $db->prepare("
        SELECT
            COUNT(ats.id)          AS total_sessions,
            SUM(CASE WHEN ar.status IN ('present','late') THEN 1 ELSE 0 END) AS attended
        FROM course_enrollments ce
        JOIN attendance_sessions ats ON ats.course_id = ce.course_id
        LEFT JOIN attendance_records ar
               ON ar.session_id = ats.id
              AND ar.student_id = ce.student_id
        WHERE ce.student_id = ?
    ");
    $stmt->execute([$userId]);
    $att = $stmt->fetch();

    $totalSessions = (int) $att['total_sessions'];
    $attended      = (int) $att['attended'];
    $attendancePct = $totalSessions > 0 ? round($attended / $totalSessions * 100, 1) : 0;
    $atRisk = $attendancePct < 80 && $totalSessions > 0;

    // Total posts
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE user_id = ?');
    $stmt->execute([$userId]);
    $totalPosts = (int) $stmt->fetch()['cnt'];

    // Followers
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?');
    $stmt->execute([$userId]);
    $followers = (int) $stmt->fetch()['cnt'];

    sendSuccess([
        'role'             => 'student',
        'enrolled_courses' => $enrolledCourses,
        'attendance_pct'   => $attendancePct,
        'total_posts'      => $totalPosts,
        'followers'        => $followers,
        'at_risk'          => $atRisk,
        'total_sessions'   => $totalSessions,
        'attended'         => $attended,
    ]);
}

// ── LECTURER stats ────────────────────────────────
elseif ($auth['role'] === 'lecturer') {
    $userId = $auth['user_id'];

    // Total courses
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM courses WHERE lecturer_id = ?');
    $stmt->execute([$userId]);
    $totalCourses = (int) $stmt->fetch()['cnt'];

    // Total unique students across all courses
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ce.student_id) AS cnt
        FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        WHERE c.lecturer_id = ?
    ");
    $stmt->execute([$userId]);
    $totalStudents = (int) $stmt->fetch()['cnt'];

    // Sessions opened today
    $stmt = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM attendance_sessions ats
        JOIN courses c ON c.id = ats.course_id
        WHERE c.lecturer_id = ? AND DATE(ats.created_at) = CURDATE()
    ");
    $stmt->execute([$userId]);
    $sessionsToday = (int) $stmt->fetch()['cnt'];

    // At-risk students (below 80% in any course)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT at_risk_data.student_id) AS cnt
        FROM (
            SELECT
                ce.student_id,
                COUNT(ats.id) AS total_sess,
                SUM(CASE WHEN ar.status IN ('present','late') THEN 1 ELSE 0 END) AS attended_sess
            FROM course_enrollments ce
            JOIN courses c ON c.id = ce.course_id
            JOIN attendance_sessions ats ON ats.course_id = c.id
            LEFT JOIN attendance_records ar
                   ON ar.session_id = ats.id
                  AND ar.student_id = ce.student_id
            WHERE c.lecturer_id = ?
            GROUP BY ce.student_id, ce.course_id
            HAVING total_sess > 0
               AND (attended_sess / total_sess * 100) < 80
        ) AS at_risk_data
    ");
    $stmt->execute([$userId]);
    $atRiskCount = (int) $stmt->fetch()['cnt'];

    sendSuccess([
        'role'           => 'lecturer',
        'total_courses'  => $totalCourses,
        'total_students' => $totalStudents,
        'sessions_today' => $sessionsToday,
        'at_risk_count'  => $atRiskCount,
    ]);
}
else {
    sendError('Unsupported role.', 403);
}
?>