<?php

declare(strict_types=1);

use LeanPHP\Routing\Router;
use LeanPHP\Http\Response;
use LeanPHP\Validation\Validator;

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
