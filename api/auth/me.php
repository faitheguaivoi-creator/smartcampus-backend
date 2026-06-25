<?php
// backend/api/auth/me.php
// --------------------------------------------------
// GET /api/auth/me
//
// Returns the authenticated user's profile data.
// Requires a valid Bearer JWT in the Authorization header.
//
// Success response (200):
// { "success": true, "data": { "user": { ... }, "stats": { ... } } }
//
// Error response (401):
// { "success": false, "message": "..." }
// --------------------------------------------------

require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';
require_once __DIR__ . '/../../config/database.php';

// ── 1. Set CORS headers & handle OPTIONS preflight ──
setCorsHeaders();

// ── 2. Only accept GET requests ───────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

// ── 3. Validate JWT and get payload ───────────────
$payload = requireAuth();
$userId  = (int) ($payload['user_id'] ?? 0);

if (!$userId) {
    sendError('Unauthorized: invalid token payload.', 401);
}

// ── 4. Fetch user + profile from DB ───────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.role,
           p.avatar_url, p.department, p.level, p.matric_number, p.bio
    FROM   users    u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE  u.id = ?
    LIMIT  1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    sendError('User not found.', 404);
}

// ── 5. Fetch basic stats ───────────────────────────
$postStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM posts WHERE user_id = ?");
$postStmt->execute([$userId]);
$totalPosts = (int) ($postStmt->fetch()['cnt'] ?? 0);

$attendancePct = 0.0;
if ($user['role'] === 'student') {
    $attStmt = $db->prepare("
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
    $attStmt->execute([$userId]);
    $att = $attStmt->fetch();
    $totalSessions = (int) ($att['total_sessions'] ?? 0);
    $attended      = (int) ($att['attended'] ?? 0);
    $attendancePct = $totalSessions > 0 ? round($attended / $totalSessions * 100, 1) : 0.0;
}

// ── 6. Return success response ────────────────────
sendSuccess([
    'user' => [
        'id'             => (int) $user['id'],
        'name'           => $user['name'],
        'email'          => $user['email'],
        'role'           => $user['role'],
        'avatar_url'     => $user['avatar_url'],
        'department'     => $user['department'],
        'level'          => $user['level'],
        'matric_number'  => $user['matric_number'],
        'bio'            => $user['bio'],
    ],
    'stats' => [
        'total_posts'            => $totalPosts,
        'attendance_percentage'  => $attendancePct,
    ],
]);