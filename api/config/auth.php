<?php
require_once __DIR__ . '/database.php';

// JWT token functions
function generate_token($user_id, $email) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'email' => $email,
        'exp' => time() + JWT_EXPIRY,
        'iat' => time()
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verify_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    
    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];
    
    $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], 
        base64_encode(hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true)));
    
    if ($signature !== $expectedSignature) {
        return null;
    }
    
    $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
    
    if ($payloadData && isset($payloadData['exp']) && $payloadData['exp'] > time()) {
        return $payloadData;
    }
    
    return null;
}

function get_current_auth_user() {
    // Try to get headers - handle different server configurations
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Fallback for servers where getallheaders() doesn't exist
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }
    
    $authHeader = null;
    if ($headers) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    $payload = verify_token($token);
    
    if (!$payload) {
        return null;
    }
    
    return $payload;
}

function require_auth() {
    $user = get_current_auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    return $user;
}

function require_role($role) {
    $user = require_auth();
    $db = Database::getInstance()->getConnection();
    
    if (!has_role($db, $user['user_id'], $role) && $user['user_id'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    return $user;
}

// Note: Headers should be set by the calling script
// This file only defines functions, not headers

