<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\ResponseEmitter;

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load application configuration
$config = require __DIR__ . '/../config/app.php';

// Set timezone
date_default_timezone_set($config['timezone']);

// Build request from globals
$request = Request::fromGlobals();

// Temporary: return Hello JSON (will be replaced with proper routing later)
$response = Response::json([
    'message' => 'Hello, LeanPHP!',
    'timestamp' => date('c'),
    'method' => $request->method(),
    'path' => $request->path(),
]);

// Emit the response
ResponseEmitter::emit($response);
