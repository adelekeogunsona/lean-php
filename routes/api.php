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

// Test route parameters with constraints
$router->get('/users/{id:\d+}', function ($request) {
    $params = $request->params();
    return Response::json([
        'message' => 'User retrieved successfully',
        'user_id' => (int) $params['id'],
        'params' => $params,
    ]);
});

// Test route parameter without constraint
$router->get('/posts/{slug}', function ($request) {
    $params = $request->params();
    return Response::json([
        'message' => 'Post retrieved successfully',
        'post_slug' => $params['slug'],
        'params' => $params,
    ]);
});

// Test individual route with middleware
$router->get('/counted', function ($request) {
    return Response::json([
        'message' => 'This route has counting middleware',
        'timestamp' => date('c'),
    ]);
}, [App\Middleware\TestCounter::class]);

// Test route group with prefix and middleware
$router->group('/v1', ['middleware' => [App\Middleware\TestCounter::class]], function ($router) {
    $router->get('/users', function ($request) {
        return Response::json([
            'message' => 'Users list from v1 API',
            'users' => [],
        ]);
    });

    $router->get('/users/{id:\d+}', function ($request) {
        $params = $request->params();
        return Response::json([
            'message' => 'User from v1 API',
            'user_id' => (int) $params['id'],
        ]);
    });
});
