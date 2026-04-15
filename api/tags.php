<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

switch ($method) {
    case 'GET':
        try {
            // Add cache headers for better performance
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            if ($path) {
                $stmt = $db->prepare("SELECT * FROM tags WHERE id = ? OR slug = ?");
                $stmt->execute([$path, $path]);
                $tag = $stmt->fetch();
                
                if (!$tag) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Tag not found']);
                    exit();
                }
                
                echo json_encode($tag);
            } else {
                // Optimize query - limit to 1000 tags
                $stmt = $db->prepare("SELECT * FROM tags ORDER BY name LIMIT 1000");
                $stmt->execute();
                $tags = $stmt->fetchAll();
                echo json_encode($tags);
            }
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Tags GET error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch tags: ' . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        try {
            require_once __DIR__ . '/config/auth.php';
            // Only admin or editor can create tags
            $user = require_auth();
            $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
            if (!$isAdminOrEditor) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Only admins and editors can create tags']);
                exit();
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON data']);
                exit();
            }
            
            $name = trim($data['name'] ?? '');
            $slug = trim($data['slug'] ?? '');
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Name is required']);
                exit();
            }
            
            // Generate slug if not provided
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                $slug = preg_replace('/-+/', '-', $slug); // Replace multiple dashes with single
                $slug = trim($slug, '-'); // Remove leading/trailing dashes
            }
            
            // Check if slug exists
            $stmt = $db->prepare("SELECT id FROM tags WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                // Generate unique slug by appending number
                $counter = 1;
                $originalSlug = $slug;
                do {
                    $slug = $originalSlug . '-' . $counter;
                    $stmt = $db->prepare("SELECT id FROM tags WHERE slug = ?");
                    $stmt->execute([$slug]);
                    $exists = $stmt->fetch();
                    $counter++;
                } while ($exists && $counter < 100);
            }
            
            $tagId = bin2hex(random_bytes(16));
            $tagId = substr($tagId, 0, 8) . '-' . substr($tagId, 8, 4) . '-' . 
                    substr($tagId, 12, 4) . '-' . substr($tagId, 16, 4) . '-' . 
                    substr($tagId, 20, 12);
            
            $stmt = $db->prepare("INSERT INTO tags (id, name, slug) VALUES (?, ?, ?)");
            $stmt->execute([$tagId, $name, $slug]);
            
            $stmt = $db->prepare("SELECT * FROM tags WHERE id = ?");
            $stmt->execute([$tagId]);
            $tag = $stmt->fetch();
            
            http_response_code(201);
            echo json_encode($tag);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Tags POST error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to create tag: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Tag ID is required']);
            exit();
        }
        
        require_once __DIR__ . '/config/auth.php';
        // Only admin or editor can update tags
        $user = require_auth();
        $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
        if (!$isAdminOrEditor) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admins and editors can update tags']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['slug'])) {
            $fields[] = "slug = ?";
            $params[] = $data['slug'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit();
        }
        
        $params[] = $path;
        
        $sql = "UPDATE tags SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM tags WHERE id = ?");
        $stmt->execute([$path]);
        $tag = $stmt->fetch();
        
        echo json_encode($tag);
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Tag ID is required']);
            exit();
        }
        
        require_once __DIR__ . '/config/auth.php';
        // Only admin or editor can delete tags
        $user = require_auth();
        $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
        if (!$isAdminOrEditor) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admins and editors can delete tags']);
            exit();
        }
        
        // Check if tag exists
        $stmt = $db->prepare("SELECT id FROM tags WHERE id = ?");
        $stmt->execute([$path]);
        $tag = $stmt->fetch();
        
        if (!$tag) {
            http_response_code(404);
            echo json_encode(['error' => 'Tag not found']);
            exit();
        }
        
        // Delete the tag
        $stmt = $db->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->execute([$path]);
        
        echo json_encode(['message' => 'Tag deleted successfully']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

