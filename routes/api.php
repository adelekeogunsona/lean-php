<?php

declare(strict_types=1);

use LeanPHP\Routing\Router;
use LeanPHP\Http\Response;

// Health check endpoint
$router->get('/health', function ($request) {
    return Response::json([
        'status' => 'ok',
        'timestamp' => date('c'),
    ]);
});

// Test endpoint for JSON BodyParser
$router->post('/test', function ($request) {
    return Response::json([
        'message' => 'JSON received successfully',
        'data' => $request->json(),
        'timestamp' => date('c'),
    ]);
});
