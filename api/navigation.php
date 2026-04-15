<?php
// Navigation Menu API
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
            // Get all active navigation items (public) or all (admin)
            if ($isAdmin) {
                $stmt = $db->prepare("
                    SELECT * FROM navigation_menu_items 
                    ORDER BY display_order ASC
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT * FROM navigation_menu_items 
                    WHERE is_active = 1 
                    ORDER BY display_order ASC
                ");
            }
            $stmt->execute();
            $items = $stmt->fetchAll();
            echo json_encode($items);
            break;
            
        case 'POST':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $labelAr = $data['label_ar'] ?? '';
            $labelFr = $data['label_fr'] ?? '';
            $labelEn = $data['label_en'] ?? '';
            $href = $data['href'] ?? '#';
            $icon = $data['icon'] ?? null;
            $displayOrder = intval($data['display_order'] ?? 0);
            $isExternal = isset($data['is_external']) ? ($data['is_external'] ? 1 : 0) : 0;
            
            $stmt = $db->prepare("
                INSERT INTO navigation_menu_items (label_ar, label_fr, label_en, href, icon, display_order, is_external) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$labelAr, $labelFr, $labelEn, $href, $icon, $displayOrder, $isExternal]);
            
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
            $labelAr = $data['label_ar'] ?? '';
            $labelFr = $data['label_fr'] ?? '';
            $labelEn = $data['label_en'] ?? '';
            $href = $data['href'] ?? '#';
            $icon = $data['icon'] ?? null;
            $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
            $displayOrder = intval($data['display_order'] ?? 0);
            $isExternal = isset($data['is_external']) ? ($data['is_external'] ? 1 : 0) : 0;
            
            $stmt = $db->prepare("
                UPDATE navigation_menu_items 
                SET label_ar = ?, label_fr = ?, label_en = ?, href = ?, icon = ?, 
                    is_active = ?, display_order = ?, is_external = ?
                WHERE id = ?
            ");
            $stmt->execute([$labelAr, $labelFr, $labelEn, $href, $icon, $isActive, $displayOrder, $isExternal, $id]);
            
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
            
            $stmt = $db->prepare("DELETE FROM navigation_menu_items WHERE id = ?");
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

