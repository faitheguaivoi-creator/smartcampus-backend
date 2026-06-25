<?php
// backend/helpers/cors.php
// --------------------------------------------------
// CORS = Cross-Origin Resource Sharing
// Our React frontend runs on http://localhost:5173
// Our PHP backend runs on http://localhost/smartcampus/backend
// Browsers BLOCK requests between different origins by default.
// This file tells the browser: "it's okay, allow these requests."
// --------------------------------------------------

function setCorsHeaders(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // Allow any origin dynamically to support both localhost and production Render domains.
    if ($origin) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: *");
    }

    // Allow these HTTP methods
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

    // Allow these headers in requests
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Allow credentials (cookies / auth headers)
    header("Access-Control-Allow-Credentials: true");

    // All our responses are JSON
    header("Content-Type: application/json");

    // Handle preflight OPTIONS request
    // Browsers send OPTIONS first to check if the real request is allowed
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No Content — preflight approved
        exit;
    }
}