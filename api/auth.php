<?php
// Set headers FIRST before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Prevent caching of API responses
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Wrap everything in try-catch to catch all errors
try {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    require_once __DIR__ . '/config/database.php';

    // Load auth functions, but don't let it set headers again
    require_once __DIR__ . '/config/auth.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $db = Database::getInstance()->getConnection();

    switch ($method) {
        case 'POST':
            $action = $_GET['action'] ?? '';
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'signin') {
                // Sign In
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                
                if (empty($email) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email and password are required']);
                    exit();
                }
                
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid email or password']);
                    exit();
                }
                
                // Get user roles
                $roles = get_user_roles($db, $user['id']);
                
                // Get profile
                $stmt = $db->prepare("SELECT * FROM profiles WHERE id = ?");
                $stmt->execute([$user['id']]);
                $profile = $stmt->fetch();
                
                $token = generate_token($user['id'], $user['email']);
                
                echo json_encode([
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'username' => $user['username'],
                        'full_name' => $profile['full_name'] ?? null,
                        'avatar_url' => $profile['avatar_url'] ?? null,
                        'roles' => $roles
                    ]
                ]);
                
            } elseif ($action === 'signup') {
                // Sign Up
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                $username = $data['username'] ?? '';
                
                if (empty($email) || empty($password) || empty($username)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email, password, and username are required']);
                    exit();
                }
                
                // Check if email or username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email or username already exists']);
                    exit();
                }
                
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate UUID
                $userId = bin2hex(random_bytes(16));
                $userId = substr($userId, 0, 8) . '-' . substr($userId, 8, 4) . '-' . 
                         substr($userId, 12, 4) . '-' . substr($userId, 16, 4) . '-' . 
                         substr($userId, 20, 12);
                
                try {
                    // Start transaction
                    $db->beginTransaction();
                    
                    // Insert user - trigger will automatically create profile and assign author role
                    $stmt = $db->prepare("INSERT INTO users (id, email, password_hash, username) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $email, $password_hash, $username]);
                    
                    // Trigger should have created profile and role automatically
                    // But create manually as backup (trigger might not exist or failed)
                    $stmt = $db->prepare("INSERT INTO profiles (id, username, full_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE username = ?");
                    $stmt->execute([$userId, $username, $username, $username]);
                    
                    // Insert role - generate UUID for id in case trigger didn't work or UUID() function doesn't exist
                    $roleId = bin2hex(random_bytes(16));
                    $roleId = substr($roleId, 0, 8) . '-' . substr($roleId, 8, 4) . '-' . 
                             substr($roleId, 12, 4) . '-' . substr($roleId, 16, 4) . '-' . 
                             substr($roleId, 20, 12);
                    $stmt = $db->prepare("INSERT INTO user_roles (id, user_id, role) VALUES (?, ?, 'author') ON DUPLICATE KEY UPDATE role = 'author'");
                    $stmt->execute([$roleId, $userId]);
                    
                    $db->commit();
                    
                    // Get user roles
                    $roles = get_user_roles($db, $userId);
                    
                    // Get profile
                    $stmt = $db->prepare("SELECT * FROM profiles WHERE id = ?");
                    $stmt->execute([$userId]);
                    $profile = $stmt->fetch();
                    
                    $token = generate_token($userId, $email);
                    
                    echo json_encode([
                        'token' => $token,
                        'user' => [
                            'id' => $userId,
                            'email' => $email,
                            'username' => $username,
                            'full_name' => $profile['full_name'] ?? $username,
                            'roles' => $roles
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    http_response_code(500);
                    error_log("Signup error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                    echo json_encode([
                        'error' => 'Failed to create user',
                        'message' => $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ]);
                }
                
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action. Use ?action=signup or ?action=signin']);
            }
            break;
            
        case 'GET':
            // Get current user (me)
            $user = get_current_auth_user();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user['user_id']]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit();
            }
            
            // Get profile
            $stmt = $db->prepare("SELECT * FROM profiles WHERE id = ?");
            $stmt->execute([$user['user_id']]);
            $profile = $stmt->fetch();
            
            // Get roles
            $roles = get_user_roles($db, $user['user_id']);
            
            echo json_encode([
                'id' => $userData['id'],
                'email' => $userData['email'],
                'username' => $userData['username'],
                'full_name' => $profile['full_name'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
                'bio' => $profile['bio'] ?? null,
                'roles' => $roles
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Throwable $e) {
    // Catch ALL errors including fatal errors
    http_response_code(500);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }
    
    error_log("Auth endpoint fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
