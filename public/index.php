<?php

use Phroute\Phroute\Dispatcher;
use App\Utils\UrlEncryption;

require __DIR__.'/../vendor/autoload.php';
$bootstrap = require __DIR__.'/../app/bootstrap.php';

$router = $bootstrap['router'];

// Custom error handler class
class ErrorHandler {
    public static function handleException($e) {
        $errorResponse = [
            'status' => 'error',
            'timestamp' => date('Y-m-d H:i:s'),
            'path' => $_SERVER['REQUEST_URI'] ?? ''
        ];

        // Log the error
        error_log("Exception occurred: " . $e->getMessage());
        error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());

        // Handle different types of exceptions
        switch (true) {
            case $e instanceof PDOException:
                $errorResponse['code'] = 500;
                $errorResponse['message'] = 'Database error occurred';
                // Only show detailed SQL error in development
                if ($_ENV['APP_ENV'] === 'development') {
                    $errorResponse['debug'] = $e->getMessage();
                }
                http_response_code(500);
                break;

            case $e instanceof InvalidArgumentException:
                $errorResponse['code'] = 400;
                $errorResponse['message'] = 'Invalid input data';
                $errorResponse['errors'] = $e->getMessage();
                http_response_code(400);
                break;

            case $e instanceof \Phroute\Phroute\Exception\HttpRouteNotFoundException:
                $errorResponse['code'] = 404;
                $errorResponse['message'] = 'Route not found';
                http_response_code(404);
                break;

            case $e instanceof \Phroute\Phroute\Exception\HttpMethodNotAllowedException:
                $errorResponse['code'] = 405;
                $errorResponse['message'] = 'Method not allowed';
                http_response_code(405);
                break;

            default:
                $errorResponse['code'] = 500;
                $errorResponse['message'] = 'An unexpected error occurred';
                //if ($_ENV['APP_ENV'] === 'development') {
                //    $errorResponse['debug'] = $e->getMessage();
                //}
                http_response_code(500);
        }

        header('Content-Type: application/json');
        echo json_encode($errorResponse);
        exit;
    }
}

// Set error handling
set_exception_handler([ErrorHandler::class, 'handleException']);

$dispatcher = new Dispatcher($router->getData());

$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Add detailed logging
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $url);
error_log("Route data: " . print_r($router->getData(), true));

// Initialize UrlEncryption
$urlEncryption = new UrlEncryption($_ENV['URL_ENCRYPTION_KEY']);

$encryptedPart = ltrim($url, '/');

// Check if the URL is actually encrypted
if (strpos($encryptedPart, 'api/v1') === 0) {
    // URL is not encrypted, use it as is
    $decryptedPath = $encryptedPart;
} else {
    // URL is encrypted, try to decrypt
    $decryptedPath = $urlEncryption->decrypt($encryptedPart);
}

// Log the decrypted path
error_log("Decrypted path: " . $decryptedPath);

if ($decryptedPath === false) {
    throw new InvalidArgumentException('Invalid URL encryption');
}

// Update REQUEST_URI with decrypted path
$_SERVER['REQUEST_URI'] = '/' . $decryptedPath;

try {
    $response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

    // Check if response is an array
    if (is_array($response)) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        echo $response;
    }
} catch (Exception $e) {
    // Let the error handler deal with it
    ErrorHandler::handleException($e);
}