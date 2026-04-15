<?php
/**
 * Social Media Preview Handler
 * This endpoint serves HTML with proper meta tags for social media crawlers
 * 
 * Usage: https://elmohaqiqpress.com/api/social_preview.php?slug=article-slug
 */

header('Content-Type: text/html; charset=utf-8');
// Prevent caching of social preview pages - always fetch fresh data
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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

// Get article slug from query parameter or referer
$rawSlug = $_GET['slug'] ?? null;

// If no slug in query, try to extract from referer
if (!$rawSlug && isset($_SERVER['HTTP_REFERER'])) {
    if (preg_match('#/article/([^/?]+)#', $_SERVER['HTTP_REFERER'], $matches)) {
        $rawSlug = $matches[1];
    }
}

// If still no slug, return error
if (!$rawSlug) {
    http_response_code(400);
    echo "Article slug is required. Usage: ?slug=article-slug";
    exit;
}

// Clean and decode the slug
$rawSlug = urldecode($rawSlug);
// Try decoding again in case of double encoding
$decoded = urldecode($rawSlug);
if ($decoded !== $rawSlug) {
    $rawSlug = $decoded;
}

// Get article data - try multiple slug variations
try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    // Try different slug variations
    $slugVariations = [
        trim($rawSlug),                    // Original cleaned
        ltrim(trim($rawSlug), ':'),         // Remove leading :
        ltrim(trim($rawSlug), ':-'),        // Remove leading :-
        preg_replace('/^:-?/', '', trim($rawSlug)), // Remove : or :-
    ];
    
    // Remove duplicates and empty values
    $slugVariations = array_unique(array_filter($slugVariations));
    
    $article = null;
    $foundSlug = null;
    
    // Try each variation until we find the article
    foreach ($slugVariations as $slug) {
        if (empty($slug)) continue;
        
    $stmt = $db->prepare("
        SELECT a.*, 
               c.name as category_name, c.slug as category_slug,
               m.url as media_url, m.storage_path, m.filename, m.id as media_id
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN media m ON (
            a.featured_image = m.id 
            OR a.featured_image = m.url 
            OR (a.featured_image LIKE CONCAT('%', m.filename, '%') AND m.filename IS NOT NULL)
            OR (a.featured_image LIKE CONCAT('%', m.storage_path, '%') AND m.storage_path IS NOT NULL)
        )
        WHERE a.slug = ? AND a.status = 'published'
        ORDER BY a.updated_at DESC
        LIMIT 1
    ");
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        
        if ($result) {
            $article = $result;
            $foundSlug = $slug;
            break;
        }
    }
    
    // If still not found, try a LIKE search as last resort
    if (!$article) {
        $stmt = $db->prepare("
            SELECT a.*, 
                   c.name as category_name, c.slug as category_slug
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.slug LIKE ? AND a.status = 'published'
            ORDER BY LENGTH(a.slug) ASC
            LIMIT 1
        ");
        $searchSlug = '%' . preg_replace('/^:-?/', '', trim($rawSlug)) . '%';
        $stmt->execute([$searchSlug]);
        $article = $stmt->fetch();
        if ($article) {
            $foundSlug = $article['slug'];
        }
    }
    
    if (!$article) {
        http_response_code(404);
        // For debugging - show what we tried (remove in production if needed)
        $debugInfo = "Article not found.\n";
        $debugInfo .= "Original slug: " . htmlspecialchars($rawSlug) . "\n";
        $debugInfo .= "Tried variations: " . implode(', ', array_map(function($s) { return htmlspecialchars($s); }, $slugVariations));
        error_log("Social preview: Article not found. Original: $rawSlug, Variations: " . implode(', ', $slugVariations));
        echo $debugInfo;
        exit;
    }
    
    // DEBUG: Log that article was found
    error_log("Social preview: Article found! ID: " . ($article['id'] ?? 'N/A') . ", Title: " . ($article['title'] ?? 'N/A'));
    
    // Prepare meta data - ALWAYS use article data, never homepage
    $title = !empty($article['meta_title']) ? $article['meta_title'] : $article['title'];
    // Remove leading colon if present (from slug issues)
    $title = ltrim($title, ': ');
    $title = trim($title);
    
    // Ensure title is not empty and not homepage title
    if (empty($title) || $title === 'المحقق برس - أخبار الجزائر والعالم | Elmohaqiq Press') {
        $title = trim($article['title'] ?? 'Article');
        $title = ltrim($title, ': ');
    }
    
    // Use article-specific description, fallback to excerpt, then generic
    $description = !empty($article['meta_description']) ? $article['meta_description'] : $article['excerpt'];
    if (empty($description) || strlen($description) < 20) {
        // If no good description, create one from title
        $description = $title . ' - المحقق برس - منصة إخبارية شاملة تقدم آخر الأخبار من الجزائر والعالم';
    }
    
    // DEBUG: Log what we're using
    error_log("Social preview - Title: " . $title);
    error_log("Social preview - Description: " . substr($description, 0, 100));
    // Get image URL - prefer media_url from join, fallback to featured_image
    // The media_url should already be a full URL (either local or cloud storage)
    $image = null;
    
    // DEBUG: Log what we have
    error_log("Social preview - Article ID: " . ($article['id'] ?? 'N/A'));
    error_log("Social preview - Featured image field: " . ($article['featured_image'] ?? 'NULL'));
    error_log("Social preview - Media URL from join: " . ($article['media_url'] ?? 'NULL'));
    
    // First, try media_url from the join (this is the url field from media table)
    if (!empty($article['media_url'])) {
        $image = $article['media_url'];
        error_log("Social preview - Using media_url: " . $image);
    } 
    // If no media_url, try featured_image from article
    elseif (!empty($article['featured_image'])) {
        $image = $article['featured_image'];
        error_log("Social preview - Using featured_image: " . $image);
    } else {
        error_log("Social preview - No image found, will use favicon");
    }
    
    // Process image URL - ensure it's absolute
    if ($image) {
        // If image doesn't start with http/https, make it absolute
        if (!preg_match('/^https?:\/\//', $image)) {
            // Remove leading slash if present
            $image = ltrim($image, '/');
            
            // Construct full URL
            if (strpos($image, 'api/uploads/') === 0) {
                $image = 'https://elmohaqiqpress.com/' . $image;
            } elseif (strpos($image, 'uploads/') === 0) {
                $image = 'https://elmohaqiqpress.com/api/' . $image;
            } else {
                // Assume it's a relative path, prepend with api/uploads
                $image = 'https://elmohaqiqpress.com/api/uploads/' . $image;
            }
        }
        
        // Validate URL format
        if (!filter_var($image, FILTER_VALIDATE_URL)) {
            $image = null;
        }
    }
    
    // Use the found slug (the one that matched in database) for the URL
    $url = 'https://elmohaqiqpress.com/article/' . urlencode($foundSlug ?: $article['slug']);
    $siteName = 'المحقق برس | Elmohaqiq Press';
    
    // Fallback to default image if no valid image
    // BUT: Always use article-specific title and description, never homepage content
    if (!$image) {
        // Use favicon as fallback image, but keep article-specific meta tags
        $image = 'https://elmohaqiqpress.com/favicon.png';
    }
    
    // CRITICAL: Ensure we always have article-specific data
    // The title and description should already be set from article data above
    // If they're empty or match homepage, something went wrong - use article data directly
    if (empty($title) || trim($title) === '' || $title === 'المحقق برس - أخبار الجزائر والعالم | Elmohaqiq Press') {
        // Force use article title directly
        $title = trim($article['title'] ?? 'Article');
        // Remove leading colon if present
        $title = ltrim($title, ': ');
    }
    
    if (empty($description) || trim($description) === '' || $description === 'المحقق برس - منصة إخبارية شاملة تقدم آخر الأخبار من الجزائر والعالم في السياسة، الاقتصاد، الرياضة والتكنولوجيا') {
        // Force use article data directly
        $description = trim($article['excerpt'] ?? $article['meta_description'] ?? '');
        if (empty($description) || strlen($description) < 20) {
            $description = $title . ' - المحقق برس - منصة إخبارية شاملة تقدم آخر الأخبار من الجزائر والعالم';
        }
    }
    
    // Detect image type from URL
    $imageType = 'image/jpeg'; // default
    if (preg_match('/\.(jpg|jpeg)$/i', $image)) {
        $imageType = 'image/jpeg';
    } elseif (preg_match('/\.png$/i', $image)) {
        $imageType = 'image/png';
    } elseif (preg_match('/\.gif$/i', $image)) {
        $imageType = 'image/gif';
    } elseif (preg_match('/\.webp$/i', $image)) {
        $imageType = 'image/webp';
    }
    
    // Output HTML with meta tags
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
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:image:type" content="<?php echo htmlspecialchars($imageType, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta property="og:locale" content="ar_AR" />
    
    <!-- Facebook App ID (optional but recommended) -->
    <!-- <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID" /> -->
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:url" content="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:image:src" content="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" />
    <meta name="twitter:site" content="@elmohaqiqpress" />
    
    <?php if ($article['published_at']): ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($article['published_at'])); ?>" />
    <?php endif; ?>
    <?php if ($article['category_name']): ?>
    <meta property="article:section" content="<?php echo htmlspecialchars($article['category_name'], ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endif; ?>
    
    <link rel="canonical" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
    
    <!-- Redirect to actual page for non-crawlers -->
    <script>
        if (window.location.search.indexOf('slug=') === -1) {
            window.location.href = '<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>';
        }
    </script>
</head>
<body>
    <div id="root"></div>
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($image): ?>
    <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 100%;" />
    <?php endif; ?>
    <p><a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">Read full article</a></p>
</body>
</html>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . htmlspecialchars($e->getMessage());
    error_log("Social preview error: " . $e->getMessage());
}

