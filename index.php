<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'online',
    'message' => 'SmartCampus Backend is running!',
    'endpoints' => [
        'auth' => '/api/auth/login.php',
        'register' => '/api/auth/register.php',
        'courses' => '/api/courses/index.php',
        'attendance' => '/api/attendance/index.php'
    ]
]);
?>
