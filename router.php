<?php

declare(strict_types=1);

/**
 * VAPEHAUS Development Server Router
 * Handles both:
 *   php -S localhost:8000 router.php
 *   php -S localhost:8000 -t public router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Locate the public folder whether router is executed from root or public/
$publicDir = is_dir(__DIR__ . '/public') ? __DIR__ . '/public' : __DIR__;
$targetFile = $publicDir . $uri;

// 1. Direct Static Asset Handling (CSS, JS, Fonts, Images)
// We serve files directly instead of returning false because PHP built-in server's
// document root may be the root directory rather than /public.
if ($uri !== '/' && is_file($targetFile)) {
    $extension = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'html'  => 'text/html; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'map'   => 'application/json',
    ];

    $contentType = $mimes[$extension] ?? (mime_content_type($targetFile) ?: 'application/octet-stream');
    header("Content-Type: {$contentType}");
    header('Content-Length: ' . (string) filesize($targetFile));
    readfile($targetFile);
    return true;
}

// 2. Direct API Routes -> Forward to public/index.php
if (str_starts_with($uri, '/api/')) {
    require_once $publicDir . '/index.php';
    return true;
}

// 3. Admin Portal Route
if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin.html') {
    header('Content-Type: text/html; charset=UTF-8');
    require_once $publicDir . '/admin.html';
    return true;
}

// 4. Default: Serve Storefront index.html
if (is_file($publicDir . '/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    require_once $publicDir . '/index.html';
    return true;
}

http_response_code(404);
echo '404 Not Found';
return false;
