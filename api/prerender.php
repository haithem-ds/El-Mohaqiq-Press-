<?php
/**
 * Server-side meta tag injection for social media crawlers
 * This file detects social media crawlers and serves HTML with article-specific meta tags
 */

// Function to check if the request is from a social media crawler
function isSocialMediaCrawler() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $crawlers = [
        'facebookexternalhit',
        'Facebot',
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
        'Slackbot',
        'Applebot',
        'Googlebot',
        'bingbot',
        'YandexBot',
        'Baiduspider',
        'ia_archiver',
        'facebookcatalog'
    ];
    
    foreach ($crawlers as $crawler) {
        if (stripos($userAgent, $crawler) !== false) {
            return true;
        }
    }
    return false;
}

// Function to get article data by slug
function getArticleBySlug($slug) {
    try {
        require_once __DIR__ . '/config/database.php';
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT a.*, 
                   c.name as category_name, c.slug as category_slug
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.slug = ? AND a.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching article: " . $e->getMessage());
        return null;
    }
}

// Get the current URL path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Check if it's an article URL
$isArticleUrl = preg_match('#^/article/([^/]+)#', $path, $matches);
$articleSlug = $isArticleUrl ? urldecode($matches[1]) : null;

// If it's a social media crawler and an article URL, serve custom HTML
if (isSocialMediaCrawler() && $articleSlug) {
    $article = getArticleBySlug($articleSlug);
    
    if ($article) {
        // Prepare meta data
        $title = $article['meta_title'] ?: $article['title'];
        $description = $article['meta_description'] ?: $article['excerpt'] ?: 'المحقق برس - منصة إخبارية شاملة تقدم آخر الأخبار من الجزائر والعالم';
        $image = $article['featured_image'] ?: 'https://elmohaqiqpress.com/favicon.png';
        $url = 'https://elmohaqiqpress.com' . $path;
        $siteName = 'المحقق برس | Elmohaqiq Press';
        
        // Ensure image URL is absolute
        if ($image && !preg_match('/^https?:\/\//', $image)) {
            $image = 'https://elmohaqiqpress.com' . $image;
        }
        
        // Output HTML with meta tags
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article" />
    <meta property="og:url" content="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:locale" content="ar_AR" />
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:url" content="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:site" content="@elmohaqiqpress" />
    
    <?php if ($article['published_at']): ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($article['published_at'])); ?>" />
    <?php endif; ?>
    <?php if ($article['category_name']): ?>
    <meta property="article:section" content="<?php echo htmlspecialchars($article['category_name'], ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endif; ?>
    
    <link rel="canonical" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
</head>
<body>
    <div id="root"></div>
    <script>
        // Redirect to the actual page for non-crawlers
        window.location.href = '<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>';
    </script>
</body>
</html>
        <?php
        exit;
    }
}

// For non-crawlers or non-article URLs, serve the normal index.html
// This should be handled by your web server configuration
// If this file is being used as index.php, you can include the index.html here
// Otherwise, let the request fall through to the normal routing






