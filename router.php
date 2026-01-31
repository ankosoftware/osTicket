<?php
/**
 * PHP Built-in Server Router for osTicket Development
 *
 * Usage: php -S localhost:8080 -t . router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Handle API requests - rewrite to http.php
if (preg_match('#^/api/(.+)$#', $uri, $matches)) {
    $_SERVER['PATH_INFO'] = '/' . $matches[1];
    require __DIR__ . '/api/http.php';
    return true;
}

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Check if it's a PHP file
    if (pathinfo($uri, PATHINFO_EXTENSION) === 'php') {
        require __DIR__ . $uri;
        return true;
    }
    // Let PHP serve static files
    return false;
}

// Default to index.php
if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

// Handle SCP (staff control panel) routes
if (preg_match('#^/scp/(.*)$#', $uri)) {
    if (file_exists(__DIR__ . $uri)) {
        require __DIR__ . $uri;
        return true;
    }
}

// 404 for everything else
http_response_code(404);
echo "404 Not Found: $uri";
return true;
