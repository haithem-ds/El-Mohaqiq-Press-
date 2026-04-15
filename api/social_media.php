<?php
// Social Media Links API
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
            // Get all active social media links (public)
            $stmt = $db->prepare("
                SELECT * FROM social_media_links 
                WHERE is_active = 1 
                ORDER BY display_order ASC
            ");
            $stmt->execute();
            $links = $stmt->fetchAll();
            echo json_encode($links);
            break;
            
        case 'POST':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $platform = $data['platform'] ?? '';
            $url = $data['url'] ?? '#';
            $icon = $data['icon'] ?? $platform;
            $iconScale = isset($data['icon_scale']) ? floatval($data['icon_scale']) : 3.0;
            $displayOrder = intval($data['display_order'] ?? 0);
            
            $stmt = $db->prepare("
                INSERT INTO social_media_links (platform, url, icon, icon_scale, display_order) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$platform, $url, $icon, $iconScale, $displayOrder]);
            
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
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
            $platform = $data['platform'] ?? '';
            $url = $data['url'] ?? '#';
            $icon = $data['icon'] ?? $platform;
            $iconScale = isset($data['icon_scale']) ? floatval($data['icon_scale']) : 3.0;
            $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
            $displayOrder = intval($data['display_order'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE social_media_links 
                SET platform = ?, url = ?, icon = ?, icon_scale = ?, is_active = ?, display_order = ?
                WHERE id = ?
            ");
            $stmt->execute([$platform, $url, $icon, $iconScale, $isActive, $displayOrder, $id]);
            
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
            
            $stmt = $db->prepare("DELETE FROM social_media_links WHERE id = ?");
            $stmt->execute([$id]);
            
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

