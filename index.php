<?php
/**
 * Root index.php - Handles social media crawler requests for article pages
 * For regular users, this serves the normal index.html
 * 
 * This works with nginx by detecting crawlers and redirecting them to the API endpoint
 */

// Redirect www to non-www (301 permanent redirect)
// This ensures www.elmohaqiqpress.com redirects to elmohaqiqpress.com
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^www\.(.+)$/i', $host, $matches)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $redirectUrl = 'https://' . $matches[1] . $requestUri;
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

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

// Get the current URL path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove query string from path for matching
$path = strtok($path, '?');

// Check if it's an article URL
$isArticleUrl = preg_match('#^/article/(.+)$#', $path, $matches);

// If it's a social media crawler and an article URL, redirect to API endpoint
if (isSocialMediaCrawler() && $isArticleUrl && isset($matches[1])) {
    $slug = $matches[1];
    // URL encode the slug for the redirect
    $encodedSlug = urlencode($slug);
    // Redirect to API endpoint
    header('Location: /api/social_preview.php?slug=' . $encodedSlug, true, 302);
    exit;
}

// For regular users, non-article URLs, or when article not found, serve the normal index.html
// Set cache headers for HTML - allow short cache with revalidation for better performance
// but ensure updates are seen quickly
header('Cache-Control: public, max-age=300, must-revalidate'); // 5 minutes cache
header('Pragma: public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

$indexPath = __DIR__ . '/index.html';
if (file_exists($indexPath)) {
    readfile($indexPath);
} else {
    // Fallback: try dist/index.html (for production builds)
    $distPath = __DIR__ . '/dist/index.html';
    if (file_exists($distPath)) {
        readfile($distPath);
    } else {
        http_response_code(404);
        echo "Index file not found";
    }
}
exit;
