<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\CorsMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------
// Load environment variables
// ---------------------------------------------------------------
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ---------------------------------------------------------------
// Error handling: never leak stack traces to API clients
// ---------------------------------------------------------------
$debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($debug) {
    error_log('[UNCAUGHT EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => $debug ? $e->getMessage() : 'Internal server error',
        'errors'  => $debug ? [$e->getTraceAsString()] : [],
    ]);
    exit;
});

// ---------------------------------------------------------------
// Global CORS (applies to every request, including OPTIONS preflight)
// ---------------------------------------------------------------
(new CorsMiddleware())->handle(new \App\Core\Request());

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------
// Route the request
// ---------------------------------------------------------------
$router = new Router();
require_once __DIR__ . '/../routes/api.php';

// ---------------------------------------------------------------
// Resolve the request path relative to this project's base path.
// Without a dedicated Virtual Host, XAMPP serves this app from a
// subdirectory (e.g. /flth-backend/public), so REQUEST_URI includes
// that prefix. Routes are registered starting at "/", so we strip
// the base path here to make routing work from any install location.
// ---------------------------------------------------------------
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

$path = '/' . ltrim($path, '/');

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
