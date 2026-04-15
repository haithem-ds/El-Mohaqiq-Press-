<?php
/**
 * Serve images with proper headers for social media crawlers
 * Usage: /api/serve_image.php?path=user_id/filename.jpg
 * Or: /api/serve_image.php?url=full_image_url
 */

$path = $_GET['path'] ?? '';
$url = $_GET['url'] ?? '';

// If URL is provided, extract path from it
if ($url && empty($path)) {
    // Extract path from full URL
    if (preg_match('#/api/uploads/(.+)$#', $url, $matches)) {
        $path = $matches[1];
    } elseif (preg_match('#/uploads/(.+)$#', $url, $matches)) {
        $path = $matches[1];
    }
}

if (empty($path)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Path parameter is required']);
    exit();
}

// Security: prevent directory traversal
if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid path']);
    exit();
}

require_once __DIR__ . '/config/database.php';

// Try multiple possible paths including old locations
// UPLOAD_DIR is defined in database.php as: /home/fkwrfaug/public_html/api/uploads/
$filename = basename($path);
$possiblePaths = [
    UPLOAD_DIR . $path,  // Standard path: /home/fkwrfaug/public_html/api/uploads/user_id/filename
    __DIR__ . '/uploads/' . $path,  // Relative to api directory
    dirname(__DIR__) . '/uploads/' . $path,  // One level up
    '/home/fkwrfaug/public_html/api/uploads/' . $path,  // Hardcoded absolute path
    '/home/fkwrfaug/public_html/uploads/' . $path,  // Alternative location
    // Old location: uploads/config/filename (for backwards compatibility)
    UPLOAD_DIR . 'config/' . $filename,
    __DIR__ . '/uploads/config/' . $filename,
    dirname(__DIR__) . '/uploads/config/' . $filename,
    '/home/fkwrfaug/public_html/api/uploads/config/' . $filename,
];

$filePath = null;
foreach ($possiblePaths as $testPath) {
    if (file_exists($testPath) && is_file($testPath)) {
        $filePath = $testPath;
        break;
    }
}

// If still not found, try to get from database
if (!$filePath) {
    try {
        $db = Database::getInstance()->getConnection();
        // Extract filename from path
        $filename = basename($path);
        
        // Try to find media record by filename
        $stmt = $db->prepare("SELECT storage_path, url, filename FROM media WHERE storage_path LIKE ? OR url LIKE ? OR filename = ? LIMIT 10");
        $searchTerm = '%' . $filename . '%';
        $stmt->execute([$searchTerm, $searchTerm, $filename]);
        $mediaRecords = $stmt->fetchAll();
        
        // Try each media record's storage_path and also old locations
        foreach ($mediaRecords as $media) {
            // Try storage_path locations
            if ($media['storage_path']) {
                $testPaths = [
                    UPLOAD_DIR . $media['storage_path'],
                    __DIR__ . '/uploads/' . $media['storage_path'],
                    dirname(__DIR__) . '/uploads/' . $media['storage_path'],
                ];
                foreach ($testPaths as $testPath) {
                    if (file_exists($testPath) && is_file($testPath)) {
                        $filePath = $testPath;
                        break 2;
                    }
                }
            }
            
            // Try old config location with filename
            if ($media['filename']) {
                $oldPaths = [
                    UPLOAD_DIR . 'config/' . $media['filename'],
                    __DIR__ . '/uploads/config/' . $media['filename'],
                    dirname(__DIR__) . '/uploads/config/' . $media['filename'],
                    '/home/fkwrfaug/public_html/api/uploads/config/' . $media['filename'],
                ];
                foreach ($oldPaths as $oldPath) {
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        $filePath = $oldPath;
                        break 2;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Log error for debugging
        error_log('serve_image.php database error: ' . $e->getMessage());
    }
}

// If file still not found, return 404 error - DO NOT use favicon fallback
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Image file not found on server',
        'path' => $path,
        'tried_paths' => $possiblePaths,
        'message' => 'The image file does not exist at the expected location. The file may have been deleted or moved. Please re-upload the image or check file permissions.'
    ]);
    exit();
}

// Get file info
$mimeType = mime_content_type($filePath);
$fileSize = filesize($filePath);

// If mime_content_type fails, detect from extension
if (!$mimeType || $mimeType === 'application/octet-stream') {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $mimeType = 'image/jpeg';
            break;
        case 'png':
            $mimeType = 'image/png';
            break;
        case 'gif':
            $mimeType = 'image/gif';
            break;
        case 'webp':
            $mimeType = 'image/webp';
            break;
        case 'svg':
            $mimeType = 'image/svg+xml';
            break;
        default:
            $mimeType = 'image/jpeg'; // default
    }
}

// Set proper headers for social media crawlers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

// Output file
readfile($filePath);
exit();
