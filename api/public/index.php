<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Ensure server uses India Standard Time for all date/time functions
// This central setting makes PHP date(), strtotime(), DateTime, etc. use IST (Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');
ini_set('date.timezone', 'Asia/Kolkata');

use SchoolLive\Core\Router;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// Allow PATCH as well so API endpoints using HTTP PATCH (e.g., toggle status) pass CORS preflight
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle errors and exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
});

// Welcome message: show in CLI, or return a JSON welcome for GET / requests
if (php_sapi_name() === 'cli') {
    // Simple CLI banner when running via command line
    $env = getenv('APP_ENV') ?: 'production';
    echo "SchoolLive API starting (env={$env})\n";
} else {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
    // No redirect here — root `index.php` will include this file when needed.
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET' && ($path === '/' || $path === '/index.php')) {
        echo json_encode([
            'success' => true,
            'message' => 'Welcome to SchoolLive API',
            'dateandtime' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ]);
        exit(0);
    }
}

try {
    $router = new Router();
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
