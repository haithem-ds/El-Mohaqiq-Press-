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
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($path) {
            // Get single user
            $user = require_auth();
            
            // Users can only view their own profile unless admin/editor
            if ($path !== $user['user_id'] && 
                !has_role($db, $user['user_id'], 'admin') && 
                !has_role($db, $user['user_id'], 'editor')) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT u.*, p.username, p.full_name, p.avatar_url, p.bio
                FROM users u
                LEFT JOIN profiles p ON u.id = p.id
                WHERE u.id = ?
            ");
            $stmt->execute([$path]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit();
            }
            
            // Get roles
            $roles = get_user_roles($db, $path);
            $userData['roles'] = $roles;
            
            // Remove sensitive data
            unset($userData['password_hash']);
            
            echo json_encode($userData);
        } else {
            // Get all users (admin/editor only)
            $user = require_role('admin');
            
            $stmt = $db->prepare("
                SELECT u.id, u.email, u.username, u.created_at, 
                       p.full_name, p.avatar_url, p.bio
                FROM users u
                LEFT JOIN profiles p ON u.id = p.id
                ORDER BY u.created_at DESC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Get roles for each user
            foreach ($users as &$userData) {
                $userData['roles'] = get_user_roles($db, $userData['id']);
            }
            
            echo json_encode($users);
        }
        break;
        
    case 'PUT':
        if ($action === 'role' && $path) {
            // Update user role
            $user = require_role('admin');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $role = $data['role'] ?? null;
            
            // Allow 'none' to remove all roles
            if ($role !== 'none' && !in_array($role, ['admin', 'editor', 'author'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role. Must be: admin, editor, author, or none']);
                exit();
            }
            
            // Delete existing roles
            $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt->execute([$path]);
            
            // Add new role if not 'none'
            if ($role !== 'none') {
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?)");
                $stmt->execute([$path, $role]);
            }
            
            $roles = get_user_roles($db, $path);
            
            echo json_encode(['message' => 'Role updated', 'roles' => $roles]);
        } elseif ($path) {
            // Update user profile
            $user = require_auth();
            
            // Users can only update their own profile unless admin/editor
            if ($path !== $user['user_id'] && 
                !has_role($db, $user['user_id'], 'admin') && 
                !has_role($db, $user['user_id'], 'editor')) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $fields = [];
            $params = [];
            
            if (isset($data['username'])) {
                // Check if username is available
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$data['username'], $path]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Username already taken']);
                    exit();
                }
                
                $fields[] = "username = ?";
                $params[] = $data['username'];
            }
            
            if (isset($data['full_name'])) {
                $stmt = $db->prepare("UPDATE profiles SET full_name = ? WHERE id = ?");
                $stmt->execute([$data['full_name'], $path]);
            }
            if (isset($data['bio'])) {
                $stmt = $db->prepare("UPDATE profiles SET bio = ? WHERE id = ?");
                $stmt->execute([$data['bio'], $path]);
            }
            if (isset($data['avatar_url'])) {
                $stmt = $db->prepare("UPDATE profiles SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$data['avatar_url'], $path]);
            }
            
            if (!empty($fields)) {
                $fields[] = "updated_at = NOW()";
                $params[] = $path;
                $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
            
            // Fetch updated user
            $stmt = $db->prepare("
                SELECT u.*, p.username, p.full_name, p.avatar_url, p.bio
                FROM users u
                LEFT JOIN profiles p ON u.id = p.id
                WHERE u.id = ?
            ");
            $stmt->execute([$path]);
            $userData = $stmt->fetch();
            
            $roles = get_user_roles($db, $path);
            $userData['roles'] = $roles;
            unset($userData['password_hash']);
            
            echo json_encode($userData);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
        }
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit();
        }
        
        // Only admin can delete users
        $user = require_role('admin');
        
        // Prevent deleting yourself
        if ($path === $user['user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'You cannot delete your own account']);
            exit();
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$path]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        
        // Delete user (cascade will delete roles and profile)
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$path]);
        
        echo json_encode(['message' => 'User deleted successfully']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

