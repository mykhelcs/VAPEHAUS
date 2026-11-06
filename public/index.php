<?php

declare(strict_types=1);

// Locate request path
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = urldecode(parse_url($rawUri, PHP_URL_PATH) ?: '/');

// 1. Direct Static HTML Serving for Storefront and Admin Web Routes
if (!str_starts_with($uri, '/api/')) {
    if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin.html') {
        if (file_exists(__DIR__ . '/admin.html')) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            readfile(__DIR__ . '/admin.html');
            exit;
        }
    }

    if ($uri === '/' || $uri === '' || $uri === '/index.html') {
        if (file_exists(__DIR__ . '/index.html')) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            readfile(__DIR__ . '/index.html');
            exit;
        }
    }
}

require_once __DIR__ . '/../src/bootstrap.php';

// ── Global JSON error handler ──────────────────────────────────────────────
// Ensures ALL unhandled exceptions and fatal errors return a JSON envelope
// instead of an HTML error page (which causes "non-JSON response" in the client).
set_exception_handler(function (Throwable $e): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error'   => [
            'code'    => 'SERVER_ERROR',
            'message' => $e->getMessage(),
            'details' => [],
        ],
    ]);
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Security Headers (Audit Spec & Production Hardening)
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Enable CORS for API clients & frontend apps
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Token, X-Cart-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use Vapehaus\App;
use Vapehaus\Presentation\Http\Request;

$app = new App();
$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();
