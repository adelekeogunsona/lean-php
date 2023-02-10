<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\ResponseEmitter;
use LeanPHP\Http\MiddlewareRunner;
use LeanPHP\Routing\Router;
use App\Middleware\ErrorHandler;
use App\Middleware\RequestId;
use App\Middleware\Cors;
use App\Middleware\JsonBodyParser;

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

// Create router and register routes
$router = new Router();
require __DIR__ . '/../routes/api.php';

// Set up middleware pipeline
$middlewareRunner = new MiddlewareRunner();
$middlewareRunner->add(new ErrorHandler());
$middlewareRunner->add(new RequestId());
$middlewareRunner->add(new Cors());
$middlewareRunner->add(new JsonBodyParser());

// Handle the request through middleware and routing
$response = $middlewareRunner->handle($request, function (Request $request) use ($router) {
    return $router->dispatch($request);
});

// Emit the response
ResponseEmitter::emit($response);
