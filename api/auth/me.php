<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
echo json_encode([
    "success" => true,
    "user" => [
        "id" => 5,
        "email" => "faith@student.com",
        "full_name" => "Faith Eguaivoi",
        "role" => "student",
        "student_id" => "STU12345",
        "department" => "Computer Science",
        "year_of_study" => 2,
        "profile_picture" => null
    ],
    "stats" => [
        "attendance_percentage" => 78,
        "total_posts" => 12
    ]
]);
?>