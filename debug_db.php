<?php
// debug_db.php - temp script to debug the remote DB schema and connection
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $schema = [];
    if (in_array('attendance_records', $tables)) {
        $stmt = $db->query("DESCRIBE attendance_records");
        $schema['attendance_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (in_array('attendance_sessions', $tables)) {
        $stmt = $db->query("DESCRIBE attendance_sessions");
        $schema['attendance_sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (in_array('course_enrollments', $tables)) {
        $stmt = $db->query("DESCRIBE course_enrollments");
        $schema['course_enrollments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'schema' => $schema,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
