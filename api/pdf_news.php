<?php
// PDF News / Electronic News API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Prevent caching of API responses
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/auth.php';
    
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;
    
    // Get current user for admin checks
    $currentUser = null;
    try {
        $currentUser = get_current_auth_user();
    } catch (Exception $e) {
        $currentUser = null;
    }
    
    $isAdmin = false;
    if ($currentUser && isset($currentUser['user_id'])) {
        $isAdmin = has_role($db, $currentUser['user_id'], 'admin') || 
                   has_role($db, $currentUser['user_id'], 'editor');
    }
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single PDF news item
                $stmt = $db->prepare("SELECT * FROM pdf_news WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    http_response_code(404);
                    echo json_encode(['error' => 'PDF news not found']);
                    exit();
                }
                
                echo json_encode($item);
            } else {
                // Get all active PDF news (public) or all (admin)
                if ($isAdmin) {
                    $stmt = $db->prepare("
                        SELECT * FROM pdf_news 
                        ORDER BY display_order ASC, created_at DESC
                    ");
                } else {
                    $stmt = $db->prepare("
                        SELECT * FROM pdf_news 
                        WHERE is_active = 1 
                        ORDER BY display_order ASC, created_at DESC
                    ");
                }
                $stmt->execute();
                $items = $stmt->fetchAll();
                echo json_encode($items);
            }
            break;
            
        case 'POST':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            if ($action === 'upload') {
                // Handle PDF upload
                if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'PDF file is required']);
                    exit();
                }
                
                $file = $_FILES['pdf'];
                $allowedTypes = ['application/pdf'];
                $maxSize = 50 * 1024 * 1024; // 50MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type. Only PDF files are allowed.']);
                    exit();
                }
                
                if ($file['size'] > $maxSize) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File size exceeds 50MB limit']);
                    exit();
                }
                
                // Create upload directory if it doesn't exist
                $uploadDir = UPLOAD_DIR . 'pdf_news/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('pdf_', true) . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to upload file']);
                    exit();
                }
                
                // Generate URL
                $pdfUrl = API_BASE_URL . '/uploads/pdf_news/' . $filename;
                
                // Handle cover image if provided
                $coverImageUrl = null;
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $coverFile = $_FILES['cover_image'];
                    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/svg'];
                    $coverFileName = strtolower($coverFile['name']);
                    $isSvg = substr($coverFileName, -4) === '.svg' || in_array($coverFile['type'], ['image/svg+xml', 'image/svg']);
                    
                    if (in_array($coverFile['type'], $allowedImageTypes) || $isSvg) {
                        $coverExtension = pathinfo($coverFile['name'], PATHINFO_EXTENSION);
                        $coverFilename = uniqid('cover_', true) . '.' . $coverExtension;
                        $coverFilepath = $uploadDir . $coverFilename;
                        
                        if (move_uploaded_file($coverFile['tmp_name'], $coverFilepath)) {
                            $coverImageUrl = API_BASE_URL . '/uploads/pdf_news/' . $coverFilename;
                        }
                    }
                }
                
                // Get PDF metadata
                $data = json_decode($_POST['data'] ?? '{}', true);
                $titleAr = $data['title_ar'] ?? '';
                $titleFr = $data['title_fr'] ?? '';
                $titleEn = $data['title_en'] ?? '';
                $descriptionAr = $data['description_ar'] ?? '';
                $descriptionFr = $data['description_fr'] ?? '';
                $descriptionEn = $data['description_en'] ?? '';
                $displayOrder = intval($data['display_order'] ?? 0);
                
                // Get file size and page count (if possible)
                $fileSize = $file['size'];
                $pageCount = 0; // Could use a PDF library to get actual page count
                
                // Insert into database
                $stmt = $db->prepare("
                    INSERT INTO pdf_news (title_ar, title_fr, title_en, description_ar, description_fr, description_en, 
                                        pdf_url, cover_image_url, file_size, page_count, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titleAr, $titleFr, $titleEn, $descriptionAr, $descriptionFr, $descriptionEn, 
                               $pdfUrl, $coverImageUrl, $fileSize, $pageCount, $displayOrder]);
                
                echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'pdf_url' => $pdfUrl, 'cover_image_url' => $coverImageUrl]);
                
            } else {
                // Create PDF news entry (without file upload)
                $data = json_decode(file_get_contents('php://input'), true);
                $titleAr = $data['title_ar'] ?? '';
                $titleFr = $data['title_fr'] ?? '';
                $titleEn = $data['title_en'] ?? '';
                $descriptionAr = $data['description_ar'] ?? '';
                $descriptionFr = $data['description_fr'] ?? '';
                $descriptionEn = $data['description_en'] ?? '';
                $pdfUrl = $data['pdf_url'] ?? '';
                $coverImageUrl = $data['cover_image_url'] ?? null;
                $fileSize = intval($data['file_size'] ?? 0);
                $pageCount = intval($data['page_count'] ?? 0);
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    INSERT INTO pdf_news (title_ar, title_fr, title_en, description_ar, description_fr, description_en, 
                                        pdf_url, cover_image_url, file_size, page_count, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titleAr, $titleFr, $titleEn, $descriptionAr, $descriptionFr, $descriptionEn, 
                               $pdfUrl, $coverImageUrl, $fileSize, $pageCount, $displayOrder]);
                
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            }
            break;
            
        case 'PUT':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $titleAr = $data['title_ar'] ?? '';
            $titleFr = $data['title_fr'] ?? '';
            $titleEn = $data['title_en'] ?? '';
            $descriptionAr = $data['description_ar'] ?? '';
            $descriptionFr = $data['description_fr'] ?? '';
            $descriptionEn = $data['description_en'] ?? '';
            $pdfUrl = $data['pdf_url'] ?? '';
            $coverImageUrl = $data['cover_image_url'] ?? null;
            $fileSize = intval($data['file_size'] ?? 0);
            $pageCount = intval($data['page_count'] ?? 0);
            $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
            $displayOrder = intval($data['display_order'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE pdf_news 
                SET title_ar = ?, title_fr = ?, title_en = ?, 
                    description_ar = ?, description_fr = ?, description_en = ?,
                    pdf_url = ?, cover_image_url = ?, file_size = ?, page_count = ?,
                    is_active = ?, display_order = ?
                WHERE id = ?
            ");
            $stmt->execute([$titleAr, $titleFr, $titleEn, $descriptionAr, $descriptionFr, $descriptionEn, 
                           $pdfUrl, $coverImageUrl, $fileSize, $pageCount, $isActive, $displayOrder, $id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required']);
                exit();
            }
            
            // Get PDF info before deleting
            $stmt = $db->prepare("SELECT pdf_url, cover_image_url FROM pdf_news WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM pdf_news WHERE id = ?");
            $stmt->execute([$id]);
            
            // Optionally delete files (uncomment if you want to delete files)
            // if ($item && $item['pdf_url']) {
            //     $pdfPath = str_replace(API_BASE_URL . '/uploads/', UPLOAD_DIR, $item['pdf_url']);
            //     if (file_exists($pdfPath)) {
            //         unlink($pdfPath);
            //     }
            // }
            // if ($item && $item['cover_image_url']) {
            //     $coverPath = str_replace(API_BASE_URL . '/uploads/', UPLOAD_DIR, $item['cover_image_url']);
            //     if (file_exists($coverPath)) {
            //         unlink($coverPath);
            //     }
            // }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

