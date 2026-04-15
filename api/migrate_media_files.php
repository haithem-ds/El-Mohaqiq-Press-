<?php
// Script to migrate media files from uploads/config/ to uploads/user_id/
// Run this once to fix existing uploads

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all media records
    $stmt = $db->prepare("SELECT * FROM media ORDER BY created_at DESC");
    $stmt->execute();
    $mediaItems = $stmt->fetchAll();
    
    $results = [];
    $migrated = 0;
    $failed = 0;
    $notFound = 0;
    
    foreach ($mediaItems as $item) {
        $result = [
            'id' => $item['id'],
            'filename' => $item['filename'],
            'storage_path' => $item['storage_path'],
            'url' => $item['url']
        ];
        
        // Check if file exists in old location (uploads/config/)
        $oldPath = dirname(__DIR__) . '/uploads/config/' . $item['filename'];
        
        // Also check if it's already in correct location
        $currentStoragePath = $item['storage_path'];
        $correctPath = dirname(__DIR__) . '/uploads/' . $currentStoragePath;
        
        if (file_exists($correctPath)) {
            // Already in correct location
            $result['status'] = 'already_correct';
            $result['path'] = $correctPath;
            $results[] = $result;
            continue;
        }
        
        if (!file_exists($oldPath)) {
            // Try to find the file in various locations
            $searchPaths = [
                dirname(__DIR__) . '/uploads/config/' . $item['filename'],
                dirname(__DIR__) . '/uploads/' . $item['filename'],
                UPLOAD_DIR . $item['filename'],
                UPLOAD_DIR . 'config/' . $item['filename'],
            ];
            
            $foundPath = null;
            foreach ($searchPaths as $searchPath) {
                if (file_exists($searchPath)) {
                    $foundPath = $searchPath;
                    break;
                }
            }
            
            if (!$foundPath) {
                $result['status'] = 'not_found';
                $result['searched_paths'] = $searchPaths;
                $results[] = $result;
                $notFound++;
                continue;
            }
            
            $oldPath = $foundPath;
        }
        
        // Determine correct new location based on uploaded_by
        if (!empty($item['uploaded_by'])) {
            $userId = $item['uploaded_by'];
        } else {
            // If no uploaded_by, use a default directory
            $userId = 'unknown';
        }
        
        // Create user directory if it doesn't exist
        $newDir = dirname(__DIR__) . '/uploads/' . $userId;
        if (!file_exists($newDir)) {
            mkdir($newDir, 0755, true);
        }
        
        // New filename (keep original or use the one from storage_path)
        $newFilename = basename($item['storage_path']) ?: $item['filename'];
        if (strpos($newFilename, '/') === false) {
            // If storage_path doesn't have user_id, construct the filename
            $newFilename = time() . '-' . uniqid() . '.' . pathinfo($item['filename'], PATHINFO_EXTENSION);
        } else {
            $newFilename = basename($newFilename);
        }
        
        $newPath = $newDir . '/' . $newFilename;
        
        // Copy file to new location
        if (copy($oldPath, $newPath)) {
            // Update database
            $newStoragePath = $userId . '/' . $newFilename;
            $baseUrl = str_replace('/api', '', API_BASE_URL);
            $newUrl = $baseUrl . '/api/uploads/' . $newStoragePath;
            
            $updateStmt = $db->prepare("
                UPDATE media 
                SET storage_path = ?, url = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$newStoragePath, $newUrl, $item['id']]);
            
            // Delete old file if it was in the wrong location
            if (strpos($oldPath, '/config/') !== false) {
                unlink($oldPath);
            }
            
            $result['status'] = 'migrated';
            $result['old_path'] = $oldPath;
            $result['new_path'] = $newPath;
            $result['new_url'] = $newUrl;
            $migrated++;
        } else {
            $result['status'] = 'failed';
            $result['error'] = 'Failed to copy file';
            $failed++;
        }
        
        $results[] = $result;
    }
    
    echo json_encode([
        'status' => 'completed',
        'total' => count($mediaItems),
        'migrated' => $migrated,
        'failed' => $failed,
        'not_found' => $notFound,
        'already_correct' => count($mediaItems) - $migrated - $failed - $notFound,
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

