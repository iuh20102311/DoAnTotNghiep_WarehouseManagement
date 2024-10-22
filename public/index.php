<?php

use Phroute\Phroute\Dispatcher;
use App\Utils\UrlEncryption;

require __DIR__.'/../vendor/autoload.php';
$bootstrap = require __DIR__.'/../app/bootstrap.php';

$router = $bootstrap['router'];


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
    http_response_code(400);
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Update REQUEST_URI with decrypted path
$_SERVER['REQUEST_URI'] = '/' . $decryptedPath;

try {
    $response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    // Kiểm tra nếu response là một mảng
    if (is_array($response)) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        echo $response;
    }
} catch (Exception $e) {
    // Log the error with more details
    error_log("Route error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Send a 404 response
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}