<?php
// health.php — Lightweight keep-alive ping endpoint
// cron-job.org hits this every 14 minutes to prevent Render from sleeping
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'message' => 'SmartCampus backend is awake!',
]);
