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
            
            // Check if requesting navbar categories
            if (isset($_GET['navbar']) && $_GET['navbar'] === 'true') {
                $hasNavbarColumns = false;
                try {
                    $checkStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'show_in_navbar'");
                    $hasNavbarColumns = $checkStmt->rowCount() > 0;
                } catch (Exception $e) {
                    $hasNavbarColumns = false;
                }
                
                if ($hasNavbarColumns) {
                    $stmt = $db->prepare("
                        SELECT * FROM categories 
                        WHERE show_in_navbar = 1 AND (is_active = 1 OR is_active IS NULL)
                        ORDER BY navbar_order ASC, name ASC
                    ");
                } else {
                    // Fallback: return all categories if columns don't exist
                    $stmt = $db->prepare("SELECT * FROM categories ORDER BY name ASC");
                }
                $stmt->execute();
                $categories = $stmt->fetchAll();
                echo json_encode($categories);
                exit();
            }
            
            if ($path) {
                $stmt = $db->prepare("SELECT * FROM categories WHERE id = ? OR slug = ?");
                $stmt->execute([$path, $path]);
                $category = $stmt->fetch();
                
                if (!$category) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Category not found']);
                    exit();
                }
                
                echo json_encode($category);
            } else {
                // Check if new columns exist, if not use basic query
                $hasNavbarColumns = false;
                try {
                    $checkStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'show_in_navbar'");
                    $hasNavbarColumns = $checkStmt->rowCount() > 0;
                } catch (Exception $e) {
                    $hasNavbarColumns = false;
                }
                
                if ($hasNavbarColumns) {
                    // Order by navbar_order if available, then by name
                    $stmt = $db->prepare("SELECT * FROM categories ORDER BY navbar_order ASC, name ASC LIMIT 500");
                } else {
                    // Fallback to basic query
                    $stmt = $db->prepare("SELECT * FROM categories ORDER BY name LIMIT 500");
                }
                $stmt->execute();
                $categories = $stmt->fetchAll();
                echo json_encode($categories);
            }
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Categories GET error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch categories: ' . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        require_once __DIR__ . '/config/auth.php';
        // Only admin or editor can create categories
        $user = require_auth();
        $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
        if (!$isAdminOrEditor) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admins and editors can create categories']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        
        $name = $data['name'] ?? '';
        $slug = $data['slug'] ?? '';
        $description = $data['description'] ?? null;
        $showInNavbar = isset($data['show_in_navbar']) ? ($data['show_in_navbar'] ? 1 : 0) : 0;
        $navbarOrder = isset($data['navbar_order']) ? intval($data['navbar_order']) : 999;
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        
        if (empty($name) || empty($slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and slug are required']);
            exit();
        }
        
        // Generate slug if not provided
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }
        
        // Check if slug exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug already exists']);
            exit();
        }
        
        $categoryId = bin2hex(random_bytes(16));
        $categoryId = substr($categoryId, 0, 8) . '-' . substr($categoryId, 8, 4) . '-' . 
                     substr($categoryId, 12, 4) . '-' . substr($categoryId, 16, 4) . '-' . 
                     substr($categoryId, 20, 12);
        
        // Check if new columns exist
        $hasNavbarColumns = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'show_in_navbar'");
            $hasNavbarColumns = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            $hasNavbarColumns = false;
        }
        
        if ($hasNavbarColumns) {
            $stmt = $db->prepare("INSERT INTO categories (id, name, slug, description, show_in_navbar, navbar_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $name, $slug, $description, $showInNavbar, $navbarOrder, $isActive]);
        } else {
            $stmt = $db->prepare("INSERT INTO categories (id, name, slug, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$categoryId, $name, $slug, $description]);
        }
        
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($category);
        break;
        
    case 'PUT':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Category ID is required']);
            exit();
        }
        
        require_once __DIR__ . '/config/auth.php';
        // Only admin or editor can update categories
        $user = require_auth();
        $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
        if (!$isAdminOrEditor) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admins and editors can update categories']);
            exit();
        }
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if new columns exist
        $hasNavbarColumns = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM categories LIKE 'show_in_navbar'");
            $hasNavbarColumns = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            $hasNavbarColumns = false;
        }
        
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
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if ($hasNavbarColumns) {
            if (isset($data['show_in_navbar'])) {
                $fields[] = "show_in_navbar = ?";
                $params[] = $data['show_in_navbar'] ? 1 : 0;
            }
            if (isset($data['navbar_order'])) {
                $fields[] = "navbar_order = ?";
                $params[] = intval($data['navbar_order']);
            }
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = $data['is_active'] ? 1 : 0;
            }
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit();
        }
        
        $fields[] = "updated_at = NOW()";
        $params[] = $path;
        
        $sql = "UPDATE categories SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$path]);
        $category = $stmt->fetch();
        
        echo json_encode($category);
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Category ID is required']);
            exit();
        }
        
        require_once __DIR__ . '/config/auth.php';
        // Only admin or editor can delete categories
        $user = require_auth();
        $isAdminOrEditor = has_role($db, $user['user_id'], 'admin') || has_role($db, $user['user_id'], 'editor');
        if (!$isAdminOrEditor) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admins and editors can delete categories']);
            exit();
        }
        
        // Check if category exists
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$path]);
        $category = $stmt->fetch();
        
        if (!$category) {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found']);
            exit();
        }
        
        // Delete the category
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$path]);
        
        echo json_encode(['message' => 'Category deleted successfully']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

