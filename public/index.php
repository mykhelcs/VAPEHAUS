<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

// Security Headers (Audit Spec & Production Hardening)
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
