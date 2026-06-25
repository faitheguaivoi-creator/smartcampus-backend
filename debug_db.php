<?php
// debug_db.php - temp script to debug the remote DB schema and connection
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt.php';

try {
    $db = getDB();
    
    // Fetch users
    $usersStmt = $db->query("SELECT id, name, email, role FROM users LIMIT 10");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch some attendance sessions
    $sessionsStmt = $db->query("SELECT id, course_id, session_code, is_active FROM attendance_sessions LIMIT 5");
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch some enrollments
    $enrollmentsStmt = $db->query("SELECT id, course_id, student_id FROM course_enrollments LIMIT 5");
    $enrollments = $enrollmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Let's find a student to generate a token for
    $student = null;
    foreach ($users as $u) {
        if ($u['role'] === 'student') {
            $student = $u;
            break;
        }
    }

    $token = null;
    if ($student) {
        $token = generateJWT([
            'user_id' => $student['id'],
            'name'    => $student['name'],
            'email'   => $student['email'],
            'role'    => $student['role'],
        ]);
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'sessions' => $sessions,
        'enrollments' => $enrollments,
        'test_student' => $student,
        'test_token' => $token,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
