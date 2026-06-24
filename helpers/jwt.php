<?php
// backend/helpers/jwt.php
// --------------------------------------------------
// Lightweight JWT (JSON Web Token) implementation.
// No external library needed — pure PHP.
//
// A JWT has 3 parts separated by dots:
//   header.payload.signature
//
// We sign it with a secret key so only our server
// can produce valid tokens.
// --------------------------------------------------

define('JWT_SECRET', 'SmartCampus_Super_Secret_Key_2025_Change_In_Production');
define('JWT_EXPIRY', 60 * 60 * 24 * 7); // 7 days in seconds

/**
 * Generate a JWT token for a logged-in user.
 */
function generateJWT(array $payload): string {
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;
    $encodedPayload  = base64UrlEncode(json_encode($payload));
    $signature       = base64UrlEncode(
        hash_hmac('sha256', "$header.$encodedPayload", JWT_SECRET, true)
    );
    return "$header.$encodedPayload.$signature";
}

/**
 * Validate a JWT and return its payload, or false on failure.
 */
function validateJWT(string $token): array|false {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $signature] = $parts;
    $expectedSig = base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    if (!hash_equals($expectedSig, $signature)) return false;
    $data = json_decode(base64UrlDecode($payload), true);
    if (!$data || $data['exp'] < time()) return false;
    return $data;
}

function getBearerToken(): ?string {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    return str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : null;
}

function requireAuth(): array {
    require_once __DIR__ . '/response.php';
    $token = getBearerToken();
    if (!$token) sendError('Unauthorized: no token provided.', 401);
    $payload = validateJWT($token);
    if (!$payload) sendError('Unauthorized: invalid or expired token.', 401);
    return $payload;
}

function requireRole(string ...$roles): array {
    $payload = requireAuth();
    if (!in_array($payload['role'], $roles)) {
        require_once __DIR__ . '/../helpers/response.php';
        sendError('Forbidden: insufficient permissions.', 403);
    }
    return $payload;
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}