<?php
// debug_db.php - temp script to debug the remote DB schema and connection
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $userId = 8; // faith eguaivoi
    $code = '73CGDQ';

    // Let's run the exact queries in mark.php to see where the 500 comes from
    $result = [];

    // Query 1: Find session
    $stmt = $db->prepare("
        SELECT ats.id, ats.course_id, ats.is_active, c.title AS course_title
        FROM attendance_sessions ats
        JOIN courses c ON c.id = ats.course_id
        WHERE ats.session_code = ?
    ");
    $stmt->execute([$code]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['session_query'] = $session;

    if (!$session) {
        throw new Exception("Session not found for code $code");
    }

    // Query 2: Check enrollment
    $stmt = $db->prepare(
        'SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?'
    );
    $stmt->execute([$session['course_id'], $userId]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['enrollment_query'] = $enrollment;

    // Query 3: Check if already marked
    $stmt = $db->prepare(
        'SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?'
    );
    $stmt->execute([$session['id'], $userId]);
    $already_marked = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['already_marked_query'] = $already_marked;

    // Query 4: Insert (we do it in a transaction so we don't actually modify state, or we can just try to execute and rollback!)
    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, "present")'
    );
    $stmt->execute([$session['id'], $userId]);
    $result['insert_success'] = true;
    $db->rollBack(); // roll back so we don't actually insert

    echo json_encode([
        'success' => true,
        'result' => $result
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
