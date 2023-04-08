<?php

declare(strict_types=1);

use LeanPHP\Routing\Router;
use LeanPHP\Http\Response;
use LeanPHP\Validation\Validator;

// Health check endpoint with ETag support
$router->get('/health', function ($request) {
    return Response::json([
        'status' => 'ok',
        'timestamp' => date('c'),
    ]);
}, [App\Middleware\ETag::class]);

// Test endpoint for JSON BodyParser
$router->post('/test', function ($request) {
    return Response::json([
        'message' => 'JSON received successfully',
        'data' => $request->json(),
        'timestamp' => date('c'),
    ]);
});

// Test route parameters with constraints and ETag support
$router->get('/users/{id:\d+}', function ($request) {
    $params = $request->params();
    return Response::json([
        'message' => 'User retrieved successfully',
        'user_id' => (int) $params['id'],
        'params' => $params,
    ]);
}, [App\Middleware\ETag::class]);

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

// Test route group with prefix and middleware (including ETag for GET routes)
$router->group('/v1', ['middleware' => [App\Middleware\TestCounter::class, App\Middleware\ETag::class]], function ($router) {
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

// Test validation endpoints
$router->post('/validate/user', function ($request) {
    $data = $request->json() ?? [];

    $validator = Validator::make($data, [
        'name' => 'required|string|min:2|max:50',
        'email' => 'required|email',
        'age' => 'required|int|min:18|max:120',
        'website' => 'url',
        'bio' => 'string|max:500',
    ]);

    // This will throw ValidationException if validation fails
    $validator->validate();

    return Response::json([
        'message' => 'User data is valid!',
        'data' => $data,
    ], 201);
});

$router->post('/validate/advanced', function ($request) {
    $data = $request->json() ?? [];

    $validator = Validator::make($data, [
        'username' => 'required|string|regex:^[a-zA-Z0-9_]{3,20}$',
        'status' => 'required|in:active,inactive,pending',
        'tags' => 'array',
        'score' => 'numeric|between:0,100',
        'birthday' => 'date',
        'start_date' => 'date|after:2020-01-01',
        'end_date' => 'date|before:2030-12-31',
        'is_admin' => 'boolean',
        'excluded' => 'not_in:spam,test,invalid',
    ]);

    // This will throw ValidationException if validation fails
    $validator->validate();

    return Response::json([
        'message' => 'Advanced validation passed!',
        'data' => $data,
    ], 201);
});

// Test manual validation (without throwing exception)
$router->post('/validate/manual', function ($request) {
    $data = $request->json() ?? [];

    $validator = Validator::make($data, [
        'title' => 'required|string|min:5',
        'content' => 'required|string|min:10',
    ]);

    if ($validator->fails()) {
        return Response::json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    return Response::json([
        'message' => 'Manual validation passed!',
        'data' => $data,
    ]);
});

// ETag demonstration endpoint - truly static content for testing
$router->get('/etag-static', function ($request) {
    return Response::json([
        'message' => 'This endpoint has static content for ETag testing',
        'instructions' => [
            '1. First request will return an ETag header',
            '2. Copy the ETag value from the response',
            '3. Make a second request with If-None-Match header set to the ETag value',
            '4. The second request should return HTTP 304 Not Modified',
        ],
        'static_id' => 12345,
        'static_data' => 'This content never changes for consistent ETag generation',
    ]);
}, [App\Middleware\ETag::class]);

// ETag demonstration endpoint - dynamic content (timestamp changes)
$router->get('/etag-demo', function ($request) {
    return Response::json([
        'message' => 'This endpoint demonstrates ETag functionality with dynamic content',
        'instructions' => [
            'Note: This endpoint includes a timestamp, so ETag will be different on each request',
            'Use /etag-static for testing conditional GET (304) responses',
        ],
        'timestamp' => date('c'),
        'request_count' => rand(1, 1000),
    ]);
}, [App\Middleware\ETag::class]);
