<?php
// Health check endpoint - test if API is running
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'API is running',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
]);

