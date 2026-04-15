<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Prevent caching of API responses
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

use Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();
$path = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($path) {
            $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? OR slug = ?");
            $stmt->execute([$path, $path]);
            $page = $stmt->fetch();
            
            if (!$page) {
                http_response_code(404);
                echo json_encode(['error' => 'Page not found']);
                exit();
            }
            
            // Check if user can view (published or admin/editor)
            $currentUser = get_current_auth_user();
            $canView = $page['published'] || 
                      ($currentUser && (has_role($db, $currentUser['user_id'], 'editor') || 
                       has_role($db, $currentUser['user_id'], 'admin')));
            
            if (!$canView) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit();
            }
            
            echo json_encode($page);
        } else {
            $currentUser = get_current_auth_user();
            
            // If not admin/editor, only show published pages
            if (!$currentUser || (!has_role($db, $currentUser['user_id'], 'editor') && 
                                 !has_role($db, $currentUser['user_id'], 'admin'))) {
                $stmt = $db->prepare("SELECT * FROM pages WHERE published = 1 ORDER BY title");
                $stmt->execute();
            } else {
                $stmt = $db->prepare("SELECT * FROM pages ORDER BY title");
                $stmt->execute();
            }
            
            $pages = $stmt->fetchAll();
            echo json_encode($pages);
        }
        break;
        
    case 'POST':
        $user = require_role('author');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $title = $data['title'] ?? '';
        $slug = $data['slug'] ?? '';
        $content = $data['content'] ?? '';
        $published = $data['published'] ?? false;
        $meta_title = $data['meta_title'] ?? null;
        $meta_description = $data['meta_description'] ?? null;
        
        if (empty($title) || empty($slug) || empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title, slug, and content are required']);
            exit();
        }
        
        // Generate slug if not provided
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        }
        
        // Check if slug exists
        $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug already exists']);
            exit();
        }
        
        $pageId = bin2hex(random_bytes(16));
        $pageId = substr($pageId, 0, 8) . '-' . substr($pageId, 8, 4) . '-' . 
                  substr($pageId, 12, 4) . '-' . substr($pageId, 16, 4) . '-' . 
                  substr($pageId, 20, 12);
        
        $stmt = $db->prepare("
            INSERT INTO pages (id, title, slug, content, published, meta_title, meta_description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$pageId, $title, $slug, $content, $published ? 1 : 0, $meta_title, $meta_description]);
        
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$pageId]);
        $page = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($page);
        break;
        
    case 'PUT':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Page ID is required']);
            exit();
        }
        
        $user = require_role('author');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $fields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = $data['title'];
        }
        if (isset($data['slug'])) {
            $fields[] = "slug = ?";
            $params[] = $data['slug'];
        }
        if (isset($data['content'])) {
            $fields[] = "content = ?";
            $params[] = $data['content'];
        }
        if (isset($data['published'])) {
            $fields[] = "published = ?";
            $params[] = $data['published'] ? 1 : 0;
        }
        if (isset($data['meta_title'])) {
            $fields[] = "meta_title = ?";
            $params[] = $data['meta_title'];
        }
        if (isset($data['meta_description'])) {
            $fields[] = "meta_description = ?";
            $params[] = $data['meta_description'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit();
        }
        
        $fields[] = "updated_at = NOW()";
        $params[] = $path;
        
        $sql = "UPDATE pages SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$path]);
        $page = $stmt->fetch();
        
        echo json_encode($page);
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Page ID is required']);
            exit();
        }
        
        $user = require_role('editor');
        
        $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$path]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
            exit();
        }
        
        echo json_encode(['message' => 'Page deleted successfully']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

