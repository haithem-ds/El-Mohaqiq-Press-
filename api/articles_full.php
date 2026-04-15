<?php
// Full Articles API endpoint with all CRUD operations
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/config/database.php';
    
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['id'] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($path) {
                // Get single article
                require_once __DIR__ . '/config/auth.php';
                
                $stmt = $db->prepare("
                    SELECT a.*, 
                           c.name as category_name, c.slug as category_slug,
                           u.username as author_username,
                           GROUP_CONCAT(t.id) as tag_ids,
                           GROUP_CONCAT(t.name) as tag_names
                    FROM articles a
                    LEFT JOIN categories c ON a.category_id = c.id
                    LEFT JOIN users u ON a.author_id = u.id
                    LEFT JOIN article_tags at ON a.id = at.article_id
                    LEFT JOIN tags t ON at.tag_id = t.id
                    WHERE a.id = ? OR a.slug = ?
                    GROUP BY a.id
                ");
                $stmt->execute([$path, $path]);
                $article = $stmt->fetch();
                
                if (!$article) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Article not found']);
                    exit();
                }
                
                // Parse tags
                if ($article['tag_ids']) {
                    $tagIds = explode(',', $article['tag_ids']);
                    $tagNames = explode(',', $article['tag_names']);
                    $article['tags'] = array_map(function($id, $name) {
                        return ['id' => $id, 'name' => $name];
                    }, $tagIds, $tagNames);
                } else {
                    $article['tags'] = [];
                }
                
                // Check permissions
                $currentUser = null;
                try {
                    $currentUser = get_current_auth_user();
                } catch (Exception $e) {
                    $currentUser = null;
                }
                
                $canView = $article['status'] === 'published';
                if (!$canView && $currentUser && isset($currentUser['user_id'])) {
                    try {
                        $canView = $currentUser['user_id'] === $article['author_id'] || 
                                  has_role($db, $currentUser['user_id'], 'editor') || 
                                  has_role($db, $currentUser['user_id'], 'admin');
                    } catch (Exception $e) {
                        $canView = false;
                    }
                }
                
                if (!$canView) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    exit();
                }
                
                // Increment view count
                if ($article['status'] === 'published') {
                    $stmt = $db->prepare("UPDATE articles SET views_count = views_count + 1 WHERE id = ?");
                    $stmt->execute([$article['id']]);
                }
                
                echo json_encode($article);
                
            } else {
                // Get all articles
                require_once __DIR__ . '/config/auth.php';
                
                $status = $_GET['status'] ?? null;
                $category = $_GET['category'] ?? null;
                $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
                $offset = max(0, intval($_GET['offset'] ?? 0));
                
                // Get current user for permissions
                $currentUser = null;
                try {
                    $currentUser = get_current_auth_user();
                } catch (Exception $e) {
                    $currentUser = null;
                }
                
                $isAdminOrEditor = false;
                if ($currentUser && isset($currentUser['user_id'])) {
                    try {
                        $isAdminOrEditor = has_role($db, $currentUser['user_id'], 'editor') || 
                                         has_role($db, $currentUser['user_id'], 'admin');
                    } catch (Exception $e) {
                        $isAdminOrEditor = false;
                    }
                }
                
                $sql = "SELECT a.*, c.name as category_name, c.slug as category_slug 
                        FROM articles a
                        LEFT JOIN categories c ON a.category_id = c.id";
                $params = [];
                $conditions = [];
                
                if (!$isAdminOrEditor) {
                    $conditions[] = "a.status = 'published'";
                } elseif ($status) {
                    $conditions[] = "a.status = ?";
                    $params[] = $status;
                }
                
                if ($category) {
                    $conditions[] = "c.slug = ?";
                    $params[] = $category;
                }
                
                if (!empty($conditions)) {
                    $sql .= " WHERE " . implode(" AND ", $conditions);
                }
                
                $sql .= " ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.created_at DESC";
                $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $articles = $stmt->fetchAll();
                
                // Add tags and format
                $result = [];
                foreach ($articles as $article) {
                    // Get tags for this article
                    $tagStmt = $db->prepare("
                        SELECT t.id, t.name 
                        FROM tags t
                        INNER JOIN article_tags at ON t.id = at.tag_id
                        WHERE at.article_id = ?
                    ");
                    $tagStmt->execute([$article['id']]);
                    $article['tags'] = $tagStmt->fetchAll();
                    $article['views_count'] = intval($article['views_count'] ?? 0);
                    $result[] = $article;
                }
                
                echo json_encode($result);
            }
            break;
            
        case 'POST':
            require_once __DIR__ . '/config/auth.php';
            $user = require_role('author');
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $title = $data['title'] ?? '';
            $slug = $data['slug'] ?? '';
            $content = $data['content'] ?? '';
            $excerpt = $data['excerpt'] ?? '';
            $status = $data['status'] ?? 'draft';
            $category_id = $data['category_id'] ?? null;
            $featured_image = $data['featured_image'] ?? null;
            $meta_title = $data['meta_title'] ?? null;
            $meta_description = $data['meta_description'] ?? null;
            $tag_ids = $data['tag_ids'] ?? [];
            
            if (empty($title) || empty($content)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and content are required']);
                exit();
            }
            
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            }
            
            // Check if slug exists
            $stmt = $db->prepare("SELECT id FROM articles WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Slug already exists']);
                exit();
            }
            
            // Generate UUID
            $articleId = bin2hex(random_bytes(16));
            $articleId = substr($articleId, 0, 8) . '-' . substr($articleId, 8, 4) . '-' . 
                         substr($articleId, 12, 4) . '-' . substr($articleId, 16, 4) . '-' . 
                         substr($articleId, 20, 12);
            
            $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    INSERT INTO articles (id, title, slug, excerpt, content, featured_image, 
                                         author_id, category_id, status, published_at, 
                                         meta_title, meta_description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $articleId, $title, $slug, $excerpt, $content, $featured_image,
                    $user['user_id'], $category_id, $status, $published_at,
                    $meta_title, $meta_description
                ]);
                
                // Add tags
                if (!empty($tag_ids)) {
                    $stmt = $db->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
                    foreach ($tag_ids as $tag_id) {
                        $stmt->execute([$articleId, $tag_id]);
                    }
                }
                
                $db->commit();
                
                // Fetch created article with relations
                $stmt = $db->prepare("
                    SELECT a.*, c.name as category_name, c.slug as category_slug
                    FROM articles a
                    LEFT JOIN categories c ON a.category_id = c.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$articleId]);
                $article = $stmt->fetch();
                
                // Get tags
                $tagStmt = $db->prepare("
                    SELECT t.id, t.name 
                    FROM tags t
                    INNER JOIN article_tags at ON t.id = at.tag_id
                    WHERE at.article_id = ?
                ");
                $tagStmt->execute([$articleId]);
                $article['tags'] = $tagStmt->fetchAll();
                
                http_response_code(201);
                echo json_encode($article);
                
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                error_log("Create article error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to create article: ' . $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            if (!$path) {
                http_response_code(400);
                echo json_encode(['error' => 'Article ID is required']);
                exit();
            }
            
            require_once __DIR__ . '/config/auth.php';
            $user = require_auth();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check permissions
            $stmt = $db->prepare("SELECT author_id FROM articles WHERE id = ?");
            $stmt->execute([$path]);
            $article = $stmt->fetch();
            
            if (!$article) {
                http_response_code(404);
                echo json_encode(['error' => 'Article not found']);
                exit();
            }
            
            $canUpdate = $article['author_id'] === $user['user_id'] || 
                        has_role($db, $user['user_id'], 'editor') || 
                        has_role($db, $user['user_id'], 'admin');
            
            if (!$canUpdate) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit();
            }
            
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
            if (isset($data['excerpt'])) {
                $fields[] = "excerpt = ?";
                $params[] = $data['excerpt'];
            }
            if (isset($data['content'])) {
                $fields[] = "content = ?";
                $params[] = $data['content'];
            }
            if (isset($data['featured_image'])) {
                $fields[] = "featured_image = ?";
                $params[] = $data['featured_image'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
                if ($data['status'] === 'published' && empty($article['published_at'])) {
                    $fields[] = "published_at = NOW()";
                }
            }
            if (isset($data['category_id'])) {
                $fields[] = "category_id = ?";
                $params[] = $data['category_id'];
            }
            if (isset($data['meta_title'])) {
                $fields[] = "meta_title = ?";
                $params[] = $data['meta_title'];
            }
            if (isset($data['meta_description'])) {
                $fields[] = "meta_description = ?";
                $params[] = $data['meta_description'];
            }
            
            if (!empty($fields)) {
                $fields[] = "updated_at = NOW()";
                $params[] = $path;
                
                $sql = "UPDATE articles SET " . implode(", ", $fields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
            
            // Update tags if provided
            if (isset($data['tag_ids'])) {
                $db->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$path]);
                if (!empty($data['tag_ids'])) {
                    $stmt = $db->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
                    foreach ($data['tag_ids'] as $tag_id) {
                        $stmt->execute([$path, $tag_id]);
                    }
                }
            }
            
            // Fetch updated article
            $stmt = $db->prepare("
                SELECT a.*, c.name as category_name, c.slug as category_slug
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                WHERE a.id = ?
            ");
            $stmt->execute([$path]);
            $article = $stmt->fetch();
            
            // Get tags
            $tagStmt = $db->prepare("
                SELECT t.id, t.name 
                FROM tags t
                INNER JOIN article_tags at ON t.id = at.tag_id
                WHERE at.article_id = ?
            ");
            $tagStmt->execute([$path]);
            $article['tags'] = $tagStmt->fetchAll();
            
            echo json_encode($article);
            break;
            
        case 'DELETE':
            if (!$path) {
                http_response_code(400);
                echo json_encode(['error' => 'Article ID is required']);
                exit();
            }
            
            require_once __DIR__ . '/config/auth.php';
            $user = require_role('editor');
            
            $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
            $stmt->execute([$path]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Article not found']);
                exit();
            }
            
            echo json_encode(['message' => 'Article deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }
    
    error_log("Articles endpoint fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

