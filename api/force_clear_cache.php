<?php
// Force clear cache and restart
header('Content-Type: text/plain');

echo "=== PHP Cache Clearing Tool ===\n\n";

// Try to clear opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPCache reset attempted\n";
} else {
    echo "ℹ️  OPCache not available\n";
}

// Touch the auth.php file to update its modification time (forces cache refresh)
$authFile = __DIR__ . '/config/auth.php';
if (file_exists($authFile)) {
    touch($authFile);
    clearstatcache(true, $authFile);
    echo "✅ Touched auth.php file to force refresh\n";
    echo "New modification time: " . date('Y-m-d H:i:s', filemtime($authFile)) . "\n";
}

// Clear PHP stat cache
clearstatcache();
echo "✅ PHP stat cache cleared\n\n";

echo "=== Testing if function conflicts ===\n\n";

// Check if PHP's built-in get_current_user exists
if (function_exists('get_current_user')) {
    echo "✅ PHP's built-in get_current_user() exists\n";
    try {
        $result = get_current_user();
        echo "   Returns: $result\n";
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Testing if our function can be declared ===\n\n";

// Try to include auth.php and see if it works
try {
    require_once __DIR__ . '/config/auth.php';
    echo "✅ auth.php loaded successfully\n";
    
    if (function_exists('get_current_auth_user')) {
        echo "✅ get_current_auth_user() function exists\n";
    } else {
        echo "❌ get_current_auth_user() function NOT found!\n";
    }
    
    if (function_exists('get_current_user')) {
        // Check if it's our custom one or PHP's built-in
        $reflection = new ReflectionFunction('get_current_user');
        $filename = $reflection->getFileName();
        echo "ℹ️  get_current_user() is defined in: $filename\n";
    }
    
} catch (Error $e) {
    echo "❌ ERROR loading auth.php: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ EXCEPTION loading auth.php: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
echo "\nIf you see errors above, the cache might need manual clearing.\n";
echo "Try: \n";
echo "1. Wait 1-2 minutes\n";
echo "2. Contact your hosting provider to clear PHP opcache\n";
echo "3. Or restart PHP-FPM if you have access\n";

