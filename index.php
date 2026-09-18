<?php
/**
 * Core PHP API Router
 * Handles CORS and basic routing to /api/ endpoints
 */

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Basic Routing
$request_uri = $_SERVER['REQUEST_URI'];

// Extract the endpoint from both direct and rewritten requests.
$path = parse_url($request_uri, PHP_URL_PATH);
$apiMarker = strpos($path, '/api/');
if ($apiMarker !== false) {
    $endpoint_full = substr($path, $apiMarker + 5);
    $parts = explode('/', trim($endpoint_full, '/'));
    $endpoint = $parts[0];
} else {
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base_path = rtrim($script_dir, '/') . '/api';
    $endpoint_full = strpos($path, $base_path) === 0
        ? substr($path, strlen($base_path))
        : '';
    $parts = explode('/', trim($endpoint_full, '/'));
    $endpoint = $parts[0];
}

// Database Connection
require_once __DIR__ . '/config/db.php';

// Route to appropriate file
switch ($endpoint) {
    case 'categories':
        require_once __DIR__ . '/api/categories.php';
        break;
    case 'calculators':
        require_once __DIR__ . '/api/calculators.php';
        break;
    case 'login':
    case 'register':
    case 'create-admin':
    case 'create-user':
    case 'logout':
    case 'me':
        require_once __DIR__ . '/api/auth.php';
        break;
    case 'search-logs':
        require_once __DIR__ . '/api/logs.php';
        break;
    case 'contact':
        require_once __DIR__ . '/api/contact.php';
        break;
    case 'admin':
        require_once __DIR__ . '/api/admin.php';
        break;
    case 'upload':
        require_once __DIR__ . '/api/upload.php';
        break;
    case 'settings':
        require_once __DIR__ . '/api/settings.php';
        break;
    case 'pages':
        require_once __DIR__ . '/api/pages.php';
        break;
    case 'sitemap':
        require_once __DIR__ . '/api/sitemap.php';
        break;
    case 'screenshot-calculator':
        require_once __DIR__ . '/api/screenshot-calculator.php';
        break;
    default:
        sendResponse(['error' => 'Endpoint not found', 'path' => $path], 404);
        break;
}