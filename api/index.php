<?php
// Main API Router
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Handle root /api request - return info without loading config
$uri_path = parse_url($request_uri, PHP_URL_PATH);
if ($uri_path === '/api' || $uri_path === '/api/') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'message' => 'API is running',
        'endpoints' => [
            'auth' => '/api/auth.php',
            'articles' => '/api/articles.php',
            'categories' => '/api/categories.php',
            'tags' => '/api/tags.php',
            'pages' => '/api/pages.php',
            'media' => '/api/media.php',
            'comments' => '/api/comments.php',
            'users' => '/api/users.php',
            'newsletter' => '/api/newsletter.php',
            'health' => '/api/health.php'
        ]
    ]);
    exit();
}

// For all other requests, endpoint files handle their own routing
// This file is mainly for root /api access
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found. Use specific endpoint files like /api/health.php']);
