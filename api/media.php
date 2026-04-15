<?php
// Set CORS headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Prevent caching of API responses
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/database.php';

use Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();
$path = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

switch ($method) {
    case 'GET':
        header('Content-Type: application/json');
        // Prevent caching
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        if ($path) {
            $stmt = $db->prepare("SELECT * FROM media WHERE id = ?");
            $stmt->execute([$path]);
            $media = $stmt->fetch();
            
            if (!$media) {
                http_response_code(404);
                echo json_encode(['error' => 'Media not found']);
                exit();
            }
            
            echo json_encode($media);
        } else {
            // Get media - users see only their own, editors/admins see all
            require_once __DIR__ . '/config/auth.php';
            $currentUser = null;
            $isEditorOrAdmin = false;
            
            try {
                $currentUser = get_current_auth_user();
                if ($currentUser) {
                    $isEditorOrAdmin = has_role($db, $currentUser['user_id'], 'editor') || 
                                      has_role($db, $currentUser['user_id'], 'admin');
                }
            } catch (Exception $e) {
                // Not authenticated - return empty array
                echo json_encode([]);
                exit();
            }
            
            if ($isEditorOrAdmin) {
                // Editors and admins see all media
                $stmt = $db->prepare("SELECT * FROM media ORDER BY created_at DESC");
                $stmt->execute();
            } else {
                // Regular users see only their own media
                $stmt = $db->prepare("SELECT * FROM media WHERE uploaded_by = ? ORDER BY created_at DESC");
                $stmt->execute([$currentUser['user_id']]);
            }
            
            $media = $stmt->fetchAll();
            
            // For old images, try to fix URLs if they're broken
            foreach ($media as &$item) {
                // If URL doesn't work, try to construct a working one
                if (!empty($item['storage_path'])) {
                    // Ensure URL matches storage_path
                    $baseUrl = str_replace('/api', '', API_BASE_URL);
                    $constructedUrl = $baseUrl . '/api/uploads/' . $item['storage_path'];
                    
                    // If current URL is different and looks old, add alternative_url
                    if ($item['url'] !== $constructedUrl) {
                        // Check if it's an old format
                        if (strpos($item['url'], '/config/') !== false || 
                            strpos($item['url'], 'config/') !== false ||
                            !strpos($item['url'], '/api/uploads/')) {
                            $item['alternative_url'] = $constructedUrl;
                            // Also try old location
                            $item['old_location_url'] = $baseUrl . '/api/serve_image.php?path=config/' . urlencode($item['filename']);
                        }
                    }
                } else if (!empty($item['filename'])) {
                    // Old images might not have storage_path, try old location
                    $baseUrl = str_replace('/api', '', API_BASE_URL);
                    $item['alternative_url'] = $baseUrl . '/api/serve_image.php?path=config/' . urlencode($item['filename']);
                }
            }
            unset($item);
            
            echo json_encode($media);
        }
        break;
        
    case 'POST':
        if ($action === 'upload') {
            // Upload file
            require_once __DIR__ . '/config/auth.php';
            $user = require_auth();
            
            header('Content-Type: application/json');
            
            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded']);
                exit();
            }
            
            $file = $_FILES['file'];
            $altText = $_POST['altText'] ?? $file['name'];
            
            // Validate file type - also check file extension for SVG (some browsers don't set MIME type correctly)
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/svg'];
            $fileName = strtolower($file['name']);
            $isSvg = substr($fileName, -4) === '.svg' || in_array($file['type'], ['image/svg+xml', 'image/svg']);
            
            if (!in_array($file['type'], $allowedTypes) && !$isSvg) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, WebP, and SVG are allowed']);
                exit();
            }
            
            // If it's an SVG but MIME type wasn't set correctly, set it
            if ($isSvg && !in_array($file['type'], ['image/svg+xml', 'image/svg'])) {
                $file['type'] = 'image/svg+xml';
            }
            
            // Validate file size (5MB)
            if ($file['size'] > 5242880) {
                http_response_code(400);
                echo json_encode(['error' => 'File size exceeds 5MB limit']);
                exit();
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = time() . '-' . uniqid() . '.' . $extension;
            
            // Simple path construction
            // UPLOAD_DIR = /home/fkwrfaug/public_html/api/uploads/
            // Full path = /home/fkwrfaug/public_html/api/uploads/user_id/filename
            $userUploadDir = UPLOAD_DIR . $user['user_id'] . DIRECTORY_SEPARATOR;
            $uploadPath = $userUploadDir . $filename;
            
            // Create user directory if it doesn't exist
            if (!file_exists($userUploadDir)) {
                mkdir($userUploadDir, 0755, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload file']);
                exit();
            }
            
            // Generate URL - simple and direct
            // File is at: public_html/api/uploads/user_id/filename
            // URL is: https://elmohaqiqpress.com/api/uploads/user_id/filename
            $baseUrl = str_replace('/api', '', API_BASE_URL);
            $url = $baseUrl . '/api/uploads/' . $user['user_id'] . '/' . $filename;
            
            // Save to database
            $mediaId = bin2hex(random_bytes(16));
            $mediaId = substr($mediaId, 0, 8) . '-' . substr($mediaId, 8, 4) . '-' . 
                      substr($mediaId, 12, 4) . '-' . substr($mediaId, 16, 4) . '-' . 
                      substr($mediaId, 20, 12);
            
            $storagePath = $user['user_id'] . '/' . $filename;
            
            // Use transaction to ensure atomicity
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO media (id, filename, url, mime_type, size, alt_text, storage_path, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $mediaId, 
                    $file['name'], 
                    $url, 
                    $file['type'], 
                    $file['size'], 
                    $altText, 
                    $storagePath,
                    $user['user_id']
                ]);
                
                // Commit transaction first
                $db->commit();
                
                // Then fetch the created record
                $stmt = $db->prepare("SELECT * FROM media WHERE id = ?");
                $stmt->execute([$mediaId]);
                $media = $stmt->fetch();
                
                if (!$media) {
                    throw new Exception("Failed to retrieve created media record");
                }
                
                http_response_code(201);
                echo json_encode($media);
                
            } catch (Exception $e) {
                $db->rollBack();
                // Clean up file if database insert failed
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                http_response_code(500);
                error_log("Media upload error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to save media: ' . $e->getMessage()]);
            }
            
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Media ID is required']);
            exit();
        }
        
        require_once __DIR__ . '/config/auth.php';
        $user = require_auth();
        
        header('Content-Type: application/json');
        
        // Get media info FIRST before deleting
        $stmt = $db->prepare("SELECT * FROM media WHERE id = ?");
        $stmt->execute([$path]);
        $media = $stmt->fetch();
        
        // If media doesn't exist, return success (idempotent - already deleted)
        if (!$media) {
            echo json_encode([
                'message' => 'Media already deleted',
                'id' => $path
            ]);
            exit();
        }
        
        // Check permissions BEFORE deleting
        // Allow deletion if:
        // 1. User uploaded the media themselves
        // 2. User is editor or admin (can delete any media)
        // 3. Media has no uploaded_by (legacy/media from migration - allow any authenticated user)
        $isOwner = !empty($media['uploaded_by']) && $media['uploaded_by'] === $user['user_id'];
        $isEditorOrAdmin = has_role($db, $user['user_id'], 'editor') || has_role($db, $user['user_id'], 'admin');
        $isLegacyMedia = empty($media['uploaded_by']); // Legacy media without uploaded_by
        
        $canDelete = $isOwner || $isEditorOrAdmin || $isLegacyMedia;
        
        if (!$canDelete) {
            http_response_code(403);
            error_log("Delete denied - User: {$user['user_id']}, Media ID: {$path}, Media uploaded_by: " . ($media['uploaded_by'] ?? 'NULL'));
            echo json_encode([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to delete this media. You can only delete media you uploaded or if you are an editor/admin.'
            ]);
            exit();
        }
        
        // Delete file first - try multiple possible locations
        $fileDeleted = false;
        $filePaths = [];
        
        if (!empty($media['storage_path'])) {
            // Primary location: uploads/user_id/filename
            $filePaths[] = UPLOAD_DIR . $media['storage_path'];
        }
        
        // Also try old locations for backwards compatibility
        if (!empty($media['filename'])) {
            $filePaths[] = UPLOAD_DIR . 'config/' . $media['filename'];
            $filePaths[] = UPLOAD_DIR . $media['filename'];
        }
        
        // Try to delete file from any location
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                if (unlink($filePath)) {
                    $fileDeleted = true;
                    break;
                }
            }
        }
        
        // Delete from database
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM media WHERE id = ?");
            $stmt->execute([$path]);
            $rowsAffected = $stmt->rowCount();
            $db->commit();
            
            if ($rowsAffected === 0) {
                // This shouldn't happen since we checked above, but handle it gracefully
                http_response_code(404);
                echo json_encode(['error' => 'Media not found in database']);
                exit();
            }
            
            // Verify deletion was successful
            $verifyStmt = $db->prepare("SELECT COUNT(*) as count FROM media WHERE id = ?");
            $verifyStmt->execute([$path]);
            $verifyResult = $verifyStmt->fetch();
            
            if ($verifyResult['count'] > 0) {
                // Still exists - this shouldn't happen, but log it
                error_log("Warning: Media still exists after delete attempt: " . $path);
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete media from database']);
                exit();
            }
            
            echo json_encode([
                'message' => 'Media deleted successfully',
                'file_deleted' => $fileDeleted,
                'id' => $path,
                'deleted' => true
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            error_log("Delete media error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to delete media: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

