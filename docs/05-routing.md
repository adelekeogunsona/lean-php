# Routing System

LeanPHP's routing system provides a fast, flexible, and feature-rich way to map HTTP requests to handlers. It supports parameterized routes, middleware, route groups, automatic HEAD/OPTIONS handling, and production route caching for optimal performance.

## Basic Routing

### Route Registration

Routes are registered using HTTP method-specific methods on the Router instance:

```php
use LeanPHP\Routing\Router;
use LeanPHP\Http\Response;

$router = new Router();

// Basic routes
$router->get('/users', function($request) {
    return Response::json(['users' => []]);
});

$router->post('/users', function($request) {
    $data = $request->json();
    return Response::json(['created' => $data], 201);
});

$router->put('/users/{id}', function($request) {
    $id = $request->params()['id'];
    return Response::json(['updated' => $id]);
});

$router->patch('/users/{id}', function($request) {
    // Partial update logic
});

$router->delete('/users/{id}', function($request) {
    return Response::noContent();
});

$router->options('/api/status', function($request) {
    return Response::noContent()
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
});

$router->head('/users', function($request) {
    // Explicit HEAD handler (optional)
});
```

### Route Handlers

Routes support multiple handler formats:

```php
// Closure handlers
$router->get('/simple', function($request) {
    return Response::json(['message' => 'Hello']);
});

// Controller class and method (array format)
$router->get('/users', [UserController::class, 'index']);

// Any callable
$router->get('/status', [$statusService, 'getStatus']);
```

## Route Parameters

### Basic Parameters

Route parameters are defined with curly braces and automatically extracted:

```php
// Route definition
$router->get('/users/{id}', function($request) {
    $params = $request->params();
    $userId = $params['id'];

    return Response::json(['user_id' => $userId]);
});

// Matches: /users/123, /users/abc, /users/user-name
// Does not match: /users (missing parameter), /users/123/posts (extra segments)
```

### Parameter Constraints

Add regex constraints to validate parameter formats:

```php
// Numeric ID constraint
$router->get('/users/{id:\d+}', function($request) {
    $id = (int) $request->params()['id'];
    return Response::json(['user_id' => $id]);
});

// Multiple constraints
$router->get('/posts/{year:\d{4}}/{month:\d{2}}/{slug:[a-z0-9-]+}', function($request) {
    $params = $request->params();
    return Response::json([
        'year' => (int) $params['year'],
        'month' => (int) $params['month'],
        'slug' => $params['slug']
    ]);
});

// Custom patterns
$router->get('/files/{filename:.+\.(jpg|png|gif)}', function($request) {
    // Matches image files with extensions
});
```

### Parameter Access

Parameters are available through the Request object:

```php
$router->get('/users/{id}/posts/{postId}', function($request) {
    $params = $request->params();

    $userId = $params['id'];
    $postId = $params['postId'];

    // Parameters are always strings - cast as needed
    $userIdInt = (int) $userId;

    return Response::json([
        'user_id' => $userIdInt,
        'post_id' => $postId
    ]);
});
```

## Route Compilation

### Regex Generation

The router compiles routes into regex patterns for efficient matching:

```php
// Route: /users/{id:\d+}
// Compiled regex: #^/users/(\d+)$#

// Route: /posts/{slug}
// Compiled regex: #^/posts/([^/]+)$#
```

The compilation process:

1. **Parameter Detection**: Find `{parameter}` and `{parameter:constraint}` patterns
2. **Constraint Application**: Replace with appropriate regex groups
3. **Default Constraints**: Use `([^/]+)` for parameters without explicit constraints
4. **Regex Wrapping**: Wrap in delimiters with start/end anchors

### Parameter Extraction

During dispatch, the router:

1. **Matches Routes**: Tests the request path against compiled regex patterns
2. **Extracts Values**: Uses regex capture groups to extract parameter values
3. **Maps Parameters**: Associates captured values with parameter names
4. **Sets Request Params**: Calls `$request->setParams($params)`

## Route Groups

Groups allow you to apply common prefixes and middleware to multiple routes:

```php
// API v1 group with authentication
$router->group('/v1', ['middleware' => [AuthBearer::class]], function($router) {

    // Protected user routes - /v1/users, /v1/users/{id}
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/users/{id}', [UserController::class, 'show']);
    $router->post('/users', [UserController::class, 'store']);

    // Nested groups
    $router->group('/admin', ['middleware' => [RequireScopes::class]], function($router) {
        // /v1/admin/users - requires both AuthBearer and RequireScopes
        $router->get('/users', [AdminController::class, 'users']);
    });
});

// Public routes (no authentication)
$router->group('/public', [], function($router) {
    $router->get('/status', [StatusController::class, 'index']);
    $router->get('/health', [HealthController::class, 'index']);
});
```

### Group Stacking

Groups can be nested, and their attributes stack:

```php
$router->group('/api', ['middleware' => [Cors::class]], function($router) {
    // Base middleware: [Cors::class]

    $router->group('/v1', ['middleware' => [AuthBearer::class]], function($router) {
        // Combined middleware: [Cors::class, AuthBearer::class]

        $router->group('/admin', ['middleware' => [RequireScopes::class]], function($router) {
            // Final middleware: [Cors::class, AuthBearer::class, RequireScopes::class]

            $router->get('/users', [AdminController::class, 'users']);
            // Final path: /api/v1/admin/users
        });
    });
});
```

## Route Middleware

### Per-Route Middleware

Apply middleware to individual routes:

```php
// Single middleware
$router->get('/profile', [UserController::class, 'profile'], [AuthBearer::class]);

// Multiple middleware (execution order: RateLimiter -> AuthBearer -> handler)
$router->post('/upload', [FileController::class, 'upload'], [
    RateLimiter::class,
    AuthBearer::class
]);

// Mix classes and instances
$router->get('/cached', [DataController::class, 'get'], [
    new ETag(),
    RateLimiter::class
]);
```

### Global Middleware

Set middleware that runs for all routes:

```php
$router->setGlobalMiddleware([
    ErrorHandler::class,
    RequestId::class,
    Cors::class,
    JsonBodyParser::class
]);
```

### Middleware Execution Order

Middleware executes in a specific order:

1. **Global middleware** (in registration order)
2. **Group middleware** (from outermost to innermost group)
3. **Route-specific middleware** (in registration order)
4. **Route handler**

```php
// Example execution chain
$router->setGlobalMiddleware([ErrorHandler::class]);

$router->group('/api', ['middleware' => [Cors::class]], function($router) {
    $router->get('/users', [UserController::class, 'index'], [AuthBearer::class]);
});

// Execution order: ErrorHandler -> Cors -> AuthBearer -> UserController::index
```

## Request Dispatch

### Dispatch Process

The `dispatch()` method handles incoming requests:

```php
$response = $router->dispatch($request);
```

The dispatch algorithm:

1. **Route Matching**: Find routes where the regex matches the request path
2. **Method Filtering**: Filter matches by HTTP method
3. **Parameter Extraction**: Extract route parameters from matched routes
4. **Special Method Handling**: Handle HEAD and OPTIONS requests
5. **Error Responses**: Return 404 or 405 if no matches found
6. **Middleware Execution**: Run middleware chain with the matched route
7. **Handler Execution**: Call the route handler
8. **Response Processing**: Handle HEAD request body removal

### Method Handling

#### HEAD Request Support

HEAD requests are automatically supported for any GET route:

```php
$router->get('/users', function($request) {
    return Response::json(['users' => $data]);
});

// Both GET and HEAD requests to /users work
// HEAD returns same headers but empty body
```

#### OPTIONS Request Support

OPTIONS requests are handled specially for CORS preflight:

```php
// Only creates synthetic OPTIONS route for CORS preflight requests
// (when Access-Control-Request-Method header is present)

// For explicit OPTIONS handling:
$router->options('/api/status', function($request) {
    return Response::noContent()
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});
```

### Error Responses

#### 404 Not Found

When no routes match the request path:

```php
return Problem::make(
    404,
    'Not Found',
    "Route {$path} not found",
    '/problems/not-found',
    null,
    $path
);
```

#### 405 Method Not Allowed

When routes match the path but not the HTTP method:

```php
return Problem::make(
    405,
    'Method Not Allowed',
    'The HTTP method is not allowed for this resource',
    '/problems/method-not-allowed',
    null,
    $path
)->header('Allow', implode(', ', $uniqueMethods));
```

The Allow header includes:
- All methods that have routes for this path
- HEAD (if GET is available)

## Route Caching

### Production Caching

In production environments, routes are cached for performance:

```php
// Environment check
$appEnv = $_ENV['APP_ENV'] ?? 'development';
$useCache = ($appEnv === 'production');
```

### Cache Implementation

```php
// Cache file location
$cacheFile = __DIR__ . '/../../storage/cache/routes.php';

// Cache loading
if ($this->shouldUseCache() && file_exists($cacheFile)) {
    $this->routes = require $cacheFile;
    $this->cacheLoaded = true;
}
```

### Cache Generation

Use the provided script to generate route cache:

```bash
php bin/route_cache.php
```

The cache script:

1. **Loads Routes**: Includes the routes file to register all routes
2. **Exports Routes**: Saves the compiled route array to cache file
3. **Optimizes Format**: Pre-compiles regex patterns and parameters

### Cache Benefits

- **Faster Startup**: No route registration overhead
- **Memory Efficiency**: Reduced object creation
- **Regex Pre-compilation**: Route patterns are already compiled
- **Production Optimization**: Only active in production environment

## Advanced Features

### Controller Resolution

Controllers can be specified as arrays with automatic instantiation:

```php
$router->get('/users', [UserController::class, 'index']);

// Router automatically:
// 1. Instantiates UserController
// 2. Calls the 'index' method
// 3. Passes the Request object as parameter
```

### Middleware Pipeline

The router implements a sophisticated middleware pipeline:

```php
private function runMiddleware(array $globalMiddleware, array $route, Request $request): Response
{
    // Combine global and route-specific middleware
    $allMiddleware = array_merge($globalMiddleware, $route['middleware']);

    // Build pipeline starting with the handler
    $next = fn(Request $req) => $this->callHandler($route['handler'], $req);

    // Add middleware in reverse order (so they execute forward)
    for ($i = count($allMiddleware) - 1; $i >= 0; $i--) {
        $middleware = $allMiddleware[$i];

        // Instantiate string class names
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }

        $currentNext = $next;
        $next = fn(Request $req) => $middleware->handle($req, $currentNext);
    }

    return $next($request);
}
```

### Handler Execution

Multiple handler formats are supported:

```php
private function callHandler(callable|array $handler, Request $request): Response
{
    if (is_array($handler)) {
        // [ControllerClass::class, 'method'] format
        [$controller, $method] = $handler;

        if (is_string($controller)) {
            $controller = new $controller();
        }

        return $controller->$method($request);
    }

    // Direct callable
    return $handler($request);
}
```

## Performance Considerations

### Route Ordering

Routes are matched in registration order, so place more specific routes first:

```php
// Good: specific route first
$router->get('/users/active', [UserController::class, 'active']);
$router->get('/users/{id}', [UserController::class, 'show']);

// Bad: parameter route catches everything
$router->get('/users/{id}', [UserController::class, 'show']);
$router->get('/users/active', [UserController::class, 'active']); // Never reached
```

### Regex Optimization

- Use specific constraints to reduce backtracking
- Avoid overly complex regex patterns
- Consider route order for common paths

### Memory Usage

- Route caching eliminates runtime registration overhead
- Compiled regex patterns are stored efficiently
- Parameter extraction uses minimal additional memory

## Testing Routes

### Route Testing

```php
class RoutingTest extends TestCase
{
    private Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/users/{id:\d+}', function($request) {
            return Response::json(['id' => $request->params()['id']]);
        });
    }

    public function test_route_matches_numeric_id(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/123';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_route_rejects_non_numeric_id(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/abc';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
```

## Best Practices

### Route Organization

1. **Group Related Routes**: Use route groups for common prefixes and middleware
2. **Consistent Naming**: Follow RESTful conventions where appropriate
3. **Parameter Validation**: Use constraints to validate parameter formats
4. **Middleware Ordering**: Apply global middleware first, then specific middleware

### Performance

1. **Use Route Caching**: Enable caching in production environments
2. **Optimize Route Order**: Place specific routes before parameterized ones
3. **Minimize Middleware**: Only apply necessary middleware to each route
4. **Efficient Constraints**: Use specific regex patterns for parameters

### Security

1. **Validate Parameters**: Always validate and sanitize route parameters
2. **Apply Authentication**: Use middleware for protected routes
3. **CORS Handling**: Implement proper CORS for cross-origin requests
4. **Rate Limiting**: Apply rate limiting to prevent abuse
