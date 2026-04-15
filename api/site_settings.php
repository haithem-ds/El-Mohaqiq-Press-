<?php
// Site Settings API - Breaking News, Footer Content
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
    $action = $_GET['action'] ?? null;
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
            if ($action === 'breaking_news') {
                // Get all breaking news items
                $stmt = $db->prepare("
                    SELECT * FROM breaking_news 
                    WHERE is_active = 1 
                    ORDER BY display_order ASC, created_at DESC
                ");
                $stmt->execute();
                $items = $stmt->fetchAll();
                echo json_encode($items);
                
            } elseif ($action === 'footer_settings') {
                // Get all footer settings
                $stmt = $db->prepare("SELECT * FROM footer_settings");
                $stmt->execute();
                $settings = $stmt->fetchAll();
                
                // Convert to key-value pairs
                $result = [];
                foreach ($settings as $setting) {
                    $result[$setting['setting_key']] = [
                        'ar' => $setting['value_ar'],
                        'fr' => $setting['value_fr'],
                        'en' => $setting['value_en']
                    ];
                }
                echo json_encode($result);
                
            } elseif ($action === 'footer_sections') {
                // Get all footer sections with their links
                $stmt = $db->prepare("
                    SELECT * FROM footer_sections 
                    WHERE is_active = 1 
                    ORDER BY display_order ASC
                ");
                $stmt->execute();
                $sections = $stmt->fetchAll();
                
                // Get links for each section
                foreach ($sections as &$section) {
                    $linkStmt = $db->prepare("
                        SELECT * FROM footer_section_links 
                        WHERE section_id = ? AND is_active = 1 
                        ORDER BY display_order ASC
                    ");
                    $linkStmt->execute([$section['id']]);
                    $section['links'] = $linkStmt->fetchAll();
                }
                
                echo json_encode($sections);
                
            } else {
                // Get all settings (for admin)
                if (!$isAdmin) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    exit();
                }
                
                $result = [
                    'breaking_news' => [],
                    'footer_settings' => [],
                    'footer_sections' => []
                ];
                
                // Breaking news
                $stmt = $db->prepare("SELECT * FROM breaking_news ORDER BY display_order ASC, created_at DESC");
                $stmt->execute();
                $result['breaking_news'] = $stmt->fetchAll();
                
                // Footer settings
                $stmt = $db->prepare("SELECT * FROM footer_settings");
                $stmt->execute();
                $settings = $stmt->fetchAll();
                foreach ($settings as $setting) {
                    $result['footer_settings'][$setting['setting_key']] = $setting;
                }
                
                // Footer sections
                $stmt = $db->prepare("SELECT * FROM footer_sections ORDER BY display_order ASC");
                $stmt->execute();
                $sections = $stmt->fetchAll();
                foreach ($sections as &$section) {
                    $linkStmt = $db->prepare("SELECT * FROM footer_section_links WHERE section_id = ? ORDER BY display_order ASC");
                    $linkStmt->execute([$section['id']]);
                    $section['links'] = $linkStmt->fetchAll();
                }
                $result['footer_sections'] = $sections;
                
                echo json_encode($result);
            }
            break;
            
        case 'POST':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'breaking_news') {
                $textAr = $data['text_ar'] ?? '';
                $textFr = $data['text_fr'] ?? '';
                $textEn = $data['text_en'] ?? '';
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    INSERT INTO breaking_news (text_ar, text_fr, text_en, display_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$textAr, $textFr, $textEn, $displayOrder]);
                
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
                
            } elseif ($action === 'footer_setting') {
                $key = $data['setting_key'] ?? '';
                $valueAr = $data['value_ar'] ?? null;
                $valueFr = $data['value_fr'] ?? null;
                $valueEn = $data['value_en'] ?? null;
                
                $stmt = $db->prepare("
                    INSERT INTO footer_settings (setting_key, value_ar, value_fr, value_en) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        value_ar = VALUES(value_ar),
                        value_fr = VALUES(value_fr),
                        value_en = VALUES(value_en)
                ");
                $stmt->execute([$key, $valueAr, $valueFr, $valueEn]);
                
                echo json_encode(['success' => true]);
                
            } elseif ($action === 'footer_section') {
                $titleAr = $data['title_ar'] ?? '';
                $titleFr = $data['title_fr'] ?? '';
                $titleEn = $data['title_en'] ?? '';
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    INSERT INTO footer_sections (title_ar, title_fr, title_en, display_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$titleAr, $titleFr, $titleEn, $displayOrder]);
                
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
                
            } elseif ($action === 'footer_section_link') {
                $sectionId = $data['section_id'] ?? '';
                $labelAr = $data['label_ar'] ?? '';
                $labelFr = $data['label_fr'] ?? '';
                $labelEn = $data['label_en'] ?? '';
                $url = $data['url'] ?? '#';
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    INSERT INTO footer_section_links (section_id, label_ar, label_fr, label_en, url, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$sectionId, $labelAr, $labelFr, $labelEn, $url, $displayOrder]);
                
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            }
            break;
            
        case 'PUT':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'breaking_news' && $id) {
                $textAr = $data['text_ar'] ?? '';
                $textFr = $data['text_fr'] ?? '';
                $textEn = $data['text_en'] ?? '';
                $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    UPDATE breaking_news 
                    SET text_ar = ?, text_fr = ?, text_en = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$textAr, $textFr, $textEn, $isActive, $displayOrder, $id]);
                
                echo json_encode(['success' => true]);
                
            } elseif ($action === 'footer_setting' && $id) {
                $valueAr = $data['value_ar'] ?? null;
                $valueFr = $data['value_fr'] ?? null;
                $valueEn = $data['value_en'] ?? null;
                
                $stmt = $db->prepare("
                    UPDATE footer_settings 
                    SET value_ar = ?, value_fr = ?, value_en = ?
                    WHERE id = ?
                ");
                $stmt->execute([$valueAr, $valueFr, $valueEn, $id]);
                
                echo json_encode(['success' => true]);
                
            } elseif ($action === 'footer_section' && $id) {
                $titleAr = $data['title_ar'] ?? '';
                $titleFr = $data['title_fr'] ?? '';
                $titleEn = $data['title_en'] ?? '';
                $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    UPDATE footer_sections 
                    SET title_ar = ?, title_fr = ?, title_en = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titleAr, $titleFr, $titleEn, $isActive, $displayOrder, $id]);
                
                echo json_encode(['success' => true]);
                
            } elseif ($action === 'footer_section_link' && $id) {
                $labelAr = $data['label_ar'] ?? '';
                $labelFr = $data['label_fr'] ?? '';
                $labelEn = $data['label_en'] ?? '';
                $url = $data['url'] ?? '#';
                $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
                $displayOrder = intval($data['display_order'] ?? 0);
                
                $stmt = $db->prepare("
                    UPDATE footer_section_links 
                    SET label_ar = ?, label_fr = ?, label_en = ?, url = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$labelAr, $labelFr, $labelEn, $url, $isActive, $displayOrder, $id]);
                
                echo json_encode(['success' => true]);
            }
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
            
            if ($action === 'breaking_news') {
                $stmt = $db->prepare("DELETE FROM breaking_news WHERE id = ?");
                $stmt->execute([$id]);
                
            } elseif ($action === 'footer_section') {
                $stmt = $db->prepare("DELETE FROM footer_sections WHERE id = ?");
                $stmt->execute([$id]);
                
            } elseif ($action === 'footer_section_link') {
                $stmt = $db->prepare("DELETE FROM footer_section_links WHERE id = ?");
                $stmt->execute([$id]);
            }
            
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

