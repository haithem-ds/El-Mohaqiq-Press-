<?php
// Serve uploaded media files
// This file handles requests to /api/uploads/*

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Extract file path after /api/uploads/
if (preg_match('/\/api\/uploads\/(.+)$/', $path, $matches)) {
    $relative_path = $matches[1];
    
    // Security: prevent directory traversal
    if (strpos($relative_path, '..') !== false) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden';
        exit();
    }
    
    // Simple path: uploads/user_id/filename
    // Also check old location for backwards compatibility
    $file_path = __DIR__ . '/' . $relative_path;
    $filename = basename($relative_path);
    
    // If not found, check multiple old locations
    if (!file_exists($file_path) || !is_file($file_path)) {
        $old_paths = [
            __DIR__ . '/config/' . $filename,  // Old location: uploads/config/filename
            dirname(__DIR__) . '/config/' . $filename,  // Alternative old location
            '/home/fkwrfaug/public_html/api/uploads/config/' . $filename,  // Absolute old path
        ];
        
        foreach ($old_paths as $old_path) {
            if (file_exists($old_path) && is_file($old_path)) {
                $file_path = $old_path;
                break;
            }
        }
    }
    
    if ($file_path) {
        $mime_type = mime_content_type($file_path);
        $file_size = filesize($file_path);
        
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $file_size);
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($file_path);
        exit();
    }
}

// File not found
http_response_code(404);
header('Content-Type: text/plain');
echo 'File not found';
