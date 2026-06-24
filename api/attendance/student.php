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

$stmt = $db->prepare("
    SELECT
        ats.id          AS session_id,
        ats.session_date,
        ats.start_time,
        ats.is_active,
        ar.status,
        ar.marked_at
    FROM attendance_sessions ats
    LEFT JOIN attendance_records ar
           ON ar.session_id = ats.id
          AND ar.student_id = ?
    WHERE ats.course_id = ?
    ORDER BY ats.session_date DESC, ats.start_time DESC
");
$stmt->execute([$auth['user_id'], $courseId]);   // ✅ using 'user_id'
$records = $stmt->fetchAll();

$total    = count($records);
$attended = 0;
foreach ($records as &$r) {
    $r['session_id'] = (int)  $r['session_id'];
    $r['is_active']  = (bool) $r['is_active'];
    if (!$r['status']) $r['status'] = 'absent';
    if ($r['status'] === 'present' || $r['status'] === 'late') $attended++;
}

$percentage = $total > 0 ? round($attended / $total * 100, 1) : 0;
$atRisk = $percentage < 80 && $total > 0;

sendSuccess([
    'records'    => $records,
    'total'      => $total,
    'attended'   => $attended,
    'percentage' => $percentage,
    'at_risk'    => $atRisk,
]);
?>