<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

use Database;

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();
$path = $_GET['id'] ?? null;
$article_id = $_GET['article_id'] ?? null;

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

switch ($method) {
    case 'GET':
        if ($path) {
            // Get single comment
            $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
            $stmt->execute([$path]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                http_response_code(404);
                echo json_encode(['error' => 'Comment not found']);
                exit();
            }
            
            // Check if user can view
            $currentUser = get_current_auth_user();
            $canView = $comment['approved'] || 
                      ($currentUser && ($currentUser['user_id'] === $comment['user_id'] || 
                       has_role($db, $currentUser['user_id'], 'editor') || 
                       has_role($db, $currentUser['user_id'], 'admin')));
            
            if (!$canView) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit();
            }
            
            echo json_encode($comment);
        } elseif ($article_id) {
            // Get comments for article
            $currentUser = get_current_auth_user();
            
            // If not admin/editor, only show approved comments
            if (!$currentUser || (!has_role($db, $currentUser['user_id'], 'editor') && 
                                 !has_role($db, $currentUser['user_id'], 'admin'))) {
                $stmt = $db->prepare("
                    SELECT c.*, u.username, p.full_name
                    FROM comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN profiles p ON c.user_id = p.id
                    WHERE c.article_id = ? AND c.approved = 1
                    ORDER BY c.created_at DESC
                ");
                $stmt->execute([$article_id]);
            } else {
                $stmt = $db->prepare("
                    SELECT c.*, u.username, p.full_name
                    FROM comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN profiles p ON c.user_id = p.id
                    WHERE c.article_id = ?
                    ORDER BY c.created_at DESC
                ");
                $stmt->execute([$article_id]);
            }
            
            $comments = $stmt->fetchAll();
            echo json_encode($comments);
        } else {
            // Get all comments - allow authors and above
            $user = require_auth();
            if (!has_role($db, $user['user_id'], 'author') && 
                !has_role($db, $user['user_id'], 'editor') && 
                !has_role($db, $user['user_id'], 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Author role or higher required.']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT c.*, a.title as article_title, u.username, p.full_name
                FROM comments c
                LEFT JOIN articles a ON c.article_id = a.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN profiles p ON c.user_id = p.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
            $comments = $stmt->fetchAll();
            echo json_encode($comments);
        }
        break;
        
    case 'POST':
        $user = require_auth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $article_id = $data['article_id'] ?? null;
        $content = $data['content'] ?? '';
        $author_name = $data['author_name'] ?? null;
        $author_email = $data['author_email'] ?? null;
        
        if (empty($article_id) || empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'Article ID and content are required']);
            exit();
        }
        
        // Verify article exists
        $stmt = $db->prepare("SELECT id FROM articles WHERE id = ?");
        $stmt->execute([$article_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found']);
            exit();
        }
        
        $commentId = bin2hex(random_bytes(16));
        $commentId = substr($commentId, 0, 8) . '-' . substr($commentId, 8, 4) . '-' . 
                    substr($commentId, 12, 4) . '-' . substr($commentId, 16, 4) . '-' . 
                    substr($commentId, 20, 12);
        
        $stmt = $db->prepare("
            INSERT INTO comments (id, article_id, user_id, author_name, author_email, content, approved)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $approved = has_role($db, $user['user_id'], 'editor') || has_role($db, $user['user_id'], 'admin') ? 1 : 0;
        $stmt->execute([
            $commentId, 
            $article_id, 
            $user['user_id'], 
            $author_name, 
            $author_email, 
            $content,
            $approved
        ]);
        
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode($comment);
        break;
        
    case 'PUT':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment ID is required']);
            exit();
        }
        
        $user = require_role('editor');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $fields = [];
        $params = [];
        
        if (isset($data['approved'])) {
            $fields[] = "approved = ?";
            $params[] = $data['approved'] ? 1 : 0;
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        if (isset($data['content'])) {
            $fields[] = "content = ?";
            $params[] = $data['content'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit();
        }
        
        $params[] = $path;
        
        $sql = "UPDATE comments SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$path]);
        $comment = $stmt->fetch();
        
        echo json_encode($comment);
        break;
        
    case 'DELETE':
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment ID is required']);
            exit();
        }
        
        $user = require_role('editor');
        
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$path]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
            exit();
        }
        
        echo json_encode(['message' => 'Comment deleted successfully']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

