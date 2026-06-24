<?php
// backend/helpers/response.php
// --------------------------------------------------
// Centralised JSON response helper.
// Every API endpoint uses these two functions so our
// response format is always consistent.
// --------------------------------------------------

/**
 * Send a success JSON response and stop execution.
 *
 * @param mixed  $data    The payload to return (array, object, etc.)
 * @param int    $code    HTTP status code (default 200)
 */
function sendSuccess(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data'    => $data,
    ]);
    exit;
}

/**
 * Send an error JSON response and stop execution.
 *
 * @param string $message Human-readable error description
 * @param int    $code    HTTP status code (default 400)
 */
function sendError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
}