<?php
// Serve uploaded media files
// Usage: /api/serve_media.php?path=user_id/filename.jpg

$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Path parameter is required']);
    exit();
}

// Security: prevent directory traversal
if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid path']);
    exit();
}

require_once __DIR__ . '/config/database.php';

$filePath = UPLOAD_DIR . $path;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit();
}

// Get file info
$mimeType = mime_content_type($filePath);
$fileSize = filesize($filePath);

// Set headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Output file
readfile($filePath);
exit();

