<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

use Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();
$path = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

switch ($method) {
    case 'GET':
        if ($action === 'subscribers') {
            // Get subscribers (author/admin/editor allowed in CMS)
            $user = require_auth();
            if (!has_role($db, $user['user_id'], 'author') && 
                !has_role($db, $user['user_id'], 'editor') && 
                !has_role($db, $user['user_id'], 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Author role or higher required.']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT * FROM newsletter_subscribers 
                WHERE subscribed = 1
                ORDER BY subscribed_at DESC
            ");
            $stmt->execute();
            $subscribers = $stmt->fetchAll();
            echo json_encode($subscribers);
            
        } elseif ($action === 'campaigns') {
            // Get campaigns (author/admin/editor allowed in CMS)
            $user = require_auth();
            if (!has_role($db, $user['user_id'], 'author') && 
                !has_role($db, $user['user_id'], 'editor') && 
                !has_role($db, $user['user_id'], 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Author role or higher required.']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT c.*, u.username as created_by_username
                FROM newsletter_campaigns c
                LEFT JOIN users u ON c.created_by = u.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
            $campaigns = $stmt->fetchAll();
            echo json_encode($campaigns);
            
        } elseif ($path && $action === 'campaign') {
            // Get single campaign (author/admin/editor allowed)
            $user = require_auth();
            if (!has_role($db, $user['user_id'], 'author') && 
                !has_role($db, $user['user_id'], 'editor') && 
                !has_role($db, $user['user_id'], 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Author role or higher required.']);
                exit();
            }
            
            $stmt = $db->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
            $stmt->execute([$path]);
            $campaign = $stmt->fetch();
            
            if (!$campaign) {
                http_response_code(404);
                echo json_encode(['error' => 'Campaign not found']);
                exit();
            }
            
            echo json_encode($campaign);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    case 'POST':
        if ($action === 'subscribe') {
            // Subscribe to newsletter (public)
            $data = json_decode(file_get_contents('php://input'), true);
            
            $email = $data['email'] ?? '';
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid email is required']);
                exit();
            }
            
            // Check if already subscribed
            $stmt = $db->prepare("SELECT id, subscribed FROM newsletter_subscribers WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                if ($existing['subscribed']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email already subscribed']);
                    exit();
                } else {
                    // Resubscribe
                    $stmt = $db->prepare("
                        UPDATE newsletter_subscribers 
                        SET subscribed = 1, subscribed_at = NOW(), unsubscribed_at = NULL 
                        WHERE id = ?
                    ");
                    $stmt->execute([$existing['id']]);
                }
            } else {
                // New subscription
                $subscriberId = bin2hex(random_bytes(16));
                $subscriberId = substr($subscriberId, 0, 8) . '-' . substr($subscriberId, 8, 4) . '-' . 
                               substr($subscriberId, 12, 4) . '-' . substr($subscriberId, 16, 4) . '-' . 
                               substr($subscriberId, 20, 12);
                
                $stmt = $db->prepare("
                    INSERT INTO newsletter_subscribers (id, email, subscribed, subscribed_at)
                    VALUES (?, ?, 1, NOW())
                ");
                $stmt->execute([$subscriberId, $email]);
            }
            
            echo json_encode(['message' => 'Successfully subscribed to newsletter']);
            
        } elseif ($action === 'campaign') {
            // Create campaign (editor/admin only)
            $user = require_role('editor');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $title = $data['title'] ?? '';
            $subject = $data['subject'] ?? '';
            $content = $data['content'] ?? '';
            $status = $data['status'] ?? 'draft';
            
            if (empty($title) || empty($subject) || empty($content)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title, subject, and content are required']);
                exit();
            }
            
            $campaignId = bin2hex(random_bytes(16));
            $campaignId = substr($campaignId, 0, 8) . '-' . substr($campaignId, 8, 4) . '-' . 
                         substr($campaignId, 12, 4) . '-' . substr($campaignId, 16, 4) . '-' . 
                         substr($campaignId, 20, 12);
            
            $stmt = $db->prepare("
                INSERT INTO newsletter_campaigns (id, title, subject, content, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$campaignId, $title, $subject, $content, $status, $user['user_id']]);
            
            $stmt = $db->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch();
            
            http_response_code(201);
            echo json_encode($campaign);
            
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    case 'PUT':
        if ($path && $action === 'campaign') {
            // Update campaign
            $user = require_role('editor');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $fields = [];
            $params = [];
            
            if (isset($data['title'])) {
                $fields[] = "title = ?";
                $params[] = $data['title'];
            }
            if (isset($data['subject'])) {
                $fields[] = "subject = ?";
                $params[] = $data['subject'];
            }
            if (isset($data['content'])) {
                $fields[] = "content = ?";
                $params[] = $data['content'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
                if ($data['status'] === 'sent') {
                    $fields[] = "sent_at = NOW()";
                }
            }
            
            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit();
            }
            
            $params[] = $path;
            
            $sql = "UPDATE newsletter_campaigns SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $stmt = $db->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
            $stmt->execute([$path]);
            $campaign = $stmt->fetch();
            
            echo json_encode($campaign);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action or missing ID']);
        }
        break;
        
    case 'DELETE':
        if ($path) {
            if ($action === 'subscriber') {
                // Unsubscribe (public endpoint)
                $data = json_decode(file_get_contents('php://input'), true);
                $email = $data['email'] ?? '';
                
                if (empty($email)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Email is required']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE newsletter_subscribers 
                    SET subscribed = 0, unsubscribed_at = NOW() 
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
                
                echo json_encode(['message' => 'Successfully unsubscribed']);
                
            } elseif ($action === 'campaign') {
                // Delete campaign (editor/admin only)
                $user = require_role('editor');
                
                $stmt = $db->prepare("DELETE FROM newsletter_campaigns WHERE id = ?");
                $stmt->execute([$path]);
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Campaign not found']);
                    exit();
                }
                
                echo json_encode(['message' => 'Campaign deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

