<?php
// Simplified articles endpoint for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $status = $_GET['status'] ?? null;
    $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    
    // Build query
    $sql = "SELECT a.*, c.name as category_name, c.slug as category_slug 
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.status = 'published'
            ORDER BY COALESCE(a.published_at, a.created_at) DESC
            LIMIT " . $limit . " OFFSET " . $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $articles = $stmt->fetchAll();
    
    // Add empty tags array to each article
    $result = [];
    foreach ($articles as $article) {
        $article['tags'] = [];
        $article['views_count'] = intval($article['views_count'] ?? 0);
        $result[] = $article;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch articles',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

