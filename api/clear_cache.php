<?php
// Clear PHP opcache if enabled
header('Content-Type: text/plain');

echo "Attempting to clear PHP opcache...\n\n";

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPCache cleared successfully!\n\n";
    } else {
        echo "⚠️  OPCache reset function exists but failed\n\n";
    }
} else {
    echo "ℹ️  OPCache is not enabled or function not available\n\n";
}

// Check current auth.php file
$authFile = __DIR__ . '/config/auth.php';
echo "Checking auth.php file:\n";
echo "Path: $authFile\n";

if (file_exists($authFile)) {
    echo "✅ File exists\n";
    echo "Size: " . filesize($authFile) . " bytes\n";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($authFile)) . "\n\n";
    
    $content = file_get_contents($authFile);
    
    if (strpos($content, 'function get_current_user()') !== false) {
        echo "❌ ERROR: File still contains 'function get_current_user()'\n";
        echo "You need to upload the NEW version!\n";
    } else {
        echo "✅ File does NOT contain 'function get_current_user()'\n";
    }
    
    if (strpos($content, 'function get_current_auth_user()') !== false) {
        echo "✅ File contains 'function get_current_auth_user()' (correct)\n";
    } else {
        echo "❌ ERROR: File does NOT contain 'function get_current_auth_user()'\n";
    }
} else {
    echo "❌ File does not exist!\n";
}

