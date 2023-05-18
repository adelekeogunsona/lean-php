# Middleware System

LeanPHP's middleware system provides a powerful, flexible way to filter and process HTTP requests and responses. Middleware functions as layers around your application logic, allowing you to add cross-cutting concerns like authentication, CORS, rate limiting, and error handling in a clean, reusable way.

## Middleware Concept

Middleware operates on the "onion" model - each middleware layer wraps around the next, creating a pipeline where requests pass through middleware in one direction and responses pass back through in reverse order.

```
Request → Middleware1 → Middleware2 → Middleware3 → Handler
Response ← Middleware1 ← Middleware2 ← Middleware3 ← Handler
```

## Middleware Interface

All middleware must implement the handle method with this signature:

```php
public function handle(Request $request, callable $next): Response
```

- **`$request`**: The incoming HTTP request
- **`$next`**: Callable that invokes the next middleware or handler
- **Returns**: HTTP response (either from `$next()` or created by the middleware)

## Basic Middleware Example

```php
<?php

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class ExampleMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Before processing - modify request or add logic
        $startTime = microtime(true);

        // Call next middleware/handler
        $response = $next($request);

        // After processing - modify response or add logic
        $duration = microtime(true) - $startTime;
        $response->header('X-Response-Time', $duration . 'ms');

        return $response;
    }
}
```

## Built-in Middleware

### ErrorHandler Middleware

Catches and handles all exceptions, converting them to appropriate HTTP responses:

```php
namespace App\Middleware;

class ErrorHandler
{
    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException $e) {
            // Convert validation errors to Problem+JSON response
            return Problem::validation($e->getErrors());
        } catch (\Throwable $e) {
            // Log error and return 500 response
            Logger::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->isProduction()
                ? Problem::make(500, 'Internal Server Error', 'An unexpected error occurred')
                : Problem::make(500, 'Internal Server Error', $e->getMessage());
        }
    }
}
```

**Features:**
- **Validation Exception Handling**: Converts `ValidationException` to 422 responses
- **General Exception Handling**: Catches all other exceptions
- **Production Safety**: Hides detailed error messages in production
- **Logging Integration**: Logs errors for debugging
- **Problem+JSON Format**: Returns RFC 7807 compliant error responses

### CORS Middleware

Handles Cross-Origin Resource Sharing (CORS) for browser-based applications:

```php
namespace App\Middleware;

class Cors
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private int $maxAge;
    private bool $allowCredentials;

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // Process actual request and add CORS headers
        $response = $next($request);
        return $this->addCorsHeaders($response, $origin);
    }
}
```

**Configuration (via environment variables):**
```env
CORS_ALLOW_ORIGINS="*"  # or "https://app.example.com,https://admin.example.com"
CORS_ALLOW_METHODS="GET,POST,PUT,PATCH,DELETE,OPTIONS"
CORS_ALLOW_HEADERS="Authorization,Content-Type"
CORS_MAX_AGE="600"
CORS_ALLOW_CREDENTIALS="false"
```

**Features:**
- **Preflight Handling**: Automatic OPTIONS request handling
- **Origin Validation**: Configurable allowed origins (supports wildcards)
- **Method Validation**: Validates allowed HTTP methods
- **Header Validation**: Validates allowed request headers
- **Credentials Support**: Optional cookie/credential support
- **Vary Headers**: Proper caching headers for proxies

### AuthBearer Middleware

Provides JWT-based Bearer token authentication:

```php
namespace App\Middleware;

class AuthBearer
{
    public function handle(Request $request, callable $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        try {
            // Verify JWT token and extract claims
            $claims = Token::verify($bearerToken);

            // Attach claims to request for use in controllers
            $request->setClaims($claims);

            return $next($request);

        } catch (ValidationException $e) {
            throw $e; // Re-throw for ErrorHandler
        } catch (InvalidArgumentException $e) {
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->unauthorized('Token verification failed');
        }
    }

    private function unauthorized(string $message): Response
    {
        return Problem::unauthorized($message)
            ->header('WWW-Authenticate', 'Bearer realm="API"');
    }
}
```

**Features:**
- **JWT Verification**: Validates JWT tokens using configured secrets
- **Claims Extraction**: Makes user claims available to controllers via `$request->claims()`
- **Proper HTTP Headers**: Returns `WWW-Authenticate` header for 401 responses
- **Error Handling**: Distinguishes between missing, invalid, and expired tokens
- **Integration**: Works with the `Request::isAuthenticated()` method

### RateLimiter Middleware

Implements configurable rate limiting to prevent abuse:

```php
namespace App\Middleware;

class RateLimiter
{
    private Store $store;           // Storage backend (APCu or File)
    private int $defaultLimit;      // Requests per window
    private int $defaultWindow;     // Time window in seconds

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        // Check if request exceeds rate limit
        if (!$this->store->allow($key, $limit, $window)) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        // Record the hit and get current state
        $result = $this->store->hit($key, $limit, $window);

        $response = $next($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $limit, $result['remaining'], $result['resetAt']);
    }
}
```

**Configuration:**
```env
RATE_LIMIT_DEFAULT="60"      # Requests per window
RATE_LIMIT_WINDOW="60"       # Window size in seconds
RATE_LIMIT_STORE="apcu"      # Storage: "apcu" or "file"
```

**Features:**
- **User-based Limiting**: Uses JWT user ID when authenticated, IP address otherwise
- **Configurable Limits**: Per-route limits can be customized
- **Multiple Storage Backends**: APCu (fast) or file-based (portable)
- **Standard Headers**: Returns `X-RateLimit-*` headers
- **Jitter**: Adds random delay to prevent thundering herd
- **429 Responses**: Proper "Too Many Requests" responses with `Retry-After`

### ETag Middleware

Implements HTTP ETag caching for GET requests:

```php
namespace App\Middleware;

class ETag
{
    public function handle(Request $request, callable $next): Response
    {
        // Process request to get response
        $response = $next($request);

        // Only apply ETag for appropriate responses
        if (!$this->shouldApplyETag($request, $response)) {
            return $response;
        }

        // Generate ETag from response content
        $etag = $this->generateETag($response->getBody());

        // Check client's If-None-Match header
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            // Return 304 Not Modified
            return Response::noContent()
                ->status(304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        // Add ETag to response
        return $response->header('ETag', $etag);
    }
}
```

**Features:**
- **Automatic ETag Generation**: SHA-256 hash of response content
- **Conditional Requests**: Handles `If-None-Match` headers
- **304 Responses**: Returns `304 Not Modified` for unchanged content
- **JSON-only**: Only applies to `application/json` responses
- **GET-only**: Only applies to GET requests with 200 status

### JsonBodyParser Middleware

Validates JSON request bodies and content types:

```php
namespace App\Middleware;

class JsonBodyParser
{
    private array $methodsRequiringJson = ['POST', 'PUT', 'PATCH'];

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        $contentType = $request->header('content-type');

        // Skip validation for methods that don't need bodies
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS', 'DELETE'])) {
            return $next($request);
        }

        // For methods with bodies, validate content type and JSON format
        if (in_array($method, $this->methodsRequiringJson)) {
            $contentLength = (int) ($request->header('content-length') ?? 0);

            if ($contentLength > 0) {
                // Require application/json content type
                if (!$contentType || !str_contains($contentType, 'application/json')) {
                    return Problem::unsupportedMediaType('Content-Type must be application/json');
                }

                // Validate JSON format
                $body = file_get_contents('php://input');
                if ($body !== '') {
                    json_decode($body, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return Problem::badRequest('Invalid JSON format: ' . json_last_error_msg());
                    }
                }
            }
        }

        return $next($request);
    }
}
```

**Features:**
- **Content-Type Validation**: Ensures JSON content type for requests with bodies
- **JSON Format Validation**: Validates JSON syntax
- **Method-Specific**: Only validates methods that typically have request bodies
- **Detailed Errors**: Returns specific error messages for JSON parsing failures

### RequestId Middleware

Adds unique request IDs for logging and tracing:

```php
namespace App\Middleware;

class RequestId
{
    public function handle(Request $request, callable $next): Response
    {
        // Generate unique request ID
        $requestId = $this->generateRequestId();

        // Make available to logging and other systems
        putenv("REQUEST_ID={$requestId}");

        $response = $next($request);

        // Add to response headers for client tracking
        return $response->header('X-Request-Id', $requestId);
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

## Middleware Pipeline

### Pipeline Architecture

The middleware pipeline is implemented in two places:

1. **Global Pipeline**: Handled by `MiddlewareRunner` in `index.php`
2. **Route Pipeline**: Handled by `Router` for route-specific middleware

### Global Middleware Setup

```php
// public/index.php
$middlewareRunner = new MiddlewareRunner();
$middlewareRunner->add(new ErrorHandler());      // Catch all exceptions
$middlewareRunner->add(new RequestId());         // Add request tracking
$middlewareRunner->add(new Cors());              // Handle CORS
$middlewareRunner->add(new JsonBodyParser());    // Validate JSON

$response = $middlewareRunner->handle($request, function (Request $request) use ($router) {
    return $router->dispatch($request);
});
```

### Route-Specific Middleware

```php
// Apply middleware to individual routes
$router->get('/profile', [UserController::class, 'profile'], [
    AuthBearer::class,
    ETag::class
]);

// Apply middleware to route groups
$router->group('/v1', ['middleware' => [AuthBearer::class]], function($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);
});

// Set global middleware for all routes
$router->setGlobalMiddleware([
    RateLimiter::class,
    ETag::class
]);
```

### Execution Order

Middleware executes in this order:

1. **Global Pipeline Middleware** (from `MiddlewareRunner`)
2. **Router Global Middleware** (from `$router->setGlobalMiddleware()`)
3. **Route Group Middleware** (from route group definitions)
4. **Route-Specific Middleware** (from individual route definitions)
5. **Route Handler** (controller or closure)

```php
// Example execution chain:
// ErrorHandler -> RequestId -> Cors -> JsonBodyParser -> AuthBearer -> ETag -> Controller
```

## Custom Middleware

### Creating Custom Middleware

```php
<?php

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class CustomLoggerMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $startTime = microtime(true);
        $method = $request->method();
        $path = $request->path();

        // Log request start
        error_log("REQUEST START: {$method} {$path}");

        // Process request
        $response = $next($request);

        // Log request completion
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $status = $response->getStatusCode();
        error_log("REQUEST END: {$method} {$path} - {$status} ({$duration}ms)");

        return $response;
    }
}
```

### Early Response Middleware

Middleware can return responses without calling `$next()`:

```php
class MaintenanceMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if ($this->isMaintenanceMode()) {
            return Response::json([
                'message' => 'System is under maintenance',
                'retry_after' => 3600
            ], 503)->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
```

### Request Modification Middleware

Middleware can modify the request before passing it along:

```php
class ApiVersionMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $version = $request->header('api-version', '1.0');

        // Add version info to request (you'd need to extend Request class)
        // This is conceptual - Request is currently immutable

        return $next($request);
    }
}
```

## Testing Middleware

### Unit Testing

```php
class CorTest extends TestCase
{
    public function test_cors_adds_headers_for_simple_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['test' => true]);
        });

        $this->assertEquals('*', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
    }

    public function test_cors_handles_preflight_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNotNull($response->getHeader('access-control-allow-methods'));
    }
}
```

### Integration Testing

```php
class MiddlewarePipelineTest extends TestCase
{
    public function test_middleware_pipeline_execution_order(): void
    {
        $executed = [];

        $middleware1 = new class($executed) {
            public function __construct(private array &$executed) {}

            public function handle(Request $request, callable $next): Response {
                $this->executed[] = 'middleware1-before';
                $response = $next($request);
                $this->executed[] = 'middleware1-after';
                return $response;
            }
        };

        $middleware2 = new class($executed) {
            public function __construct(private array &$executed) {}

            public function handle(Request $request, callable $next): Response {
                $this->executed[] = 'middleware2-before';
                $response = $next($request);
                $this->executed[] = 'middleware2-after';
                return $response;
            }
        };

        $runner = new MiddlewareRunner();
        $runner->add($middleware1);
        $runner->add($middleware2);

        $runner->handle($request, function () use (&$executed) {
            $executed[] = 'handler';
            return Response::json(['test' => true]);
        });

        $this->assertEquals([
            'middleware1-before',
            'middleware2-before',
            'handler',
            'middleware2-after',
            'middleware1-after'
        ], $executed);
    }
}
```

## Performance Considerations

### Middleware Overhead

- **Instantiation**: Middleware is instantiated on each request (consider singleton patterns for heavy middleware)
- **Pipeline Depth**: Each middleware layer adds function call overhead
- **Memory Usage**: Middleware objects remain in memory for the request duration

### Optimization Strategies

1. **Conditional Processing**: Skip expensive operations when not needed
2. **Early Returns**: Return responses early when possible
3. **Efficient Storage**: Use APCu over file storage for rate limiting
4. **Minimal State**: Keep middleware stateless when possible

### Caching Middleware

Some middleware (like ETag) implement caching to avoid regenerating responses:

```php
// ETag middleware allows browsers to cache responses
// Rate limiter uses APCu to cache rate limit state
// CORS middleware caches policy decisions
```

## Best Practices

### Middleware Design

1. **Single Responsibility**: Each middleware should have one clear purpose
2. **Immutability**: Don't modify request objects (they're immutable)
3. **Error Handling**: Let ErrorHandler middleware catch exceptions
4. **Stateless**: Avoid storing state between requests
5. **Configuration**: Use environment variables for configuration

### Security

1. **Input Validation**: Validate all input in middleware
2. **Output Sanitization**: Sanitize all output headers and data
3. **Authentication First**: Place authentication middleware early in the pipeline
4. **Rate Limiting**: Apply rate limiting to prevent abuse
5. **CORS Configuration**: Configure CORS carefully for production

### Performance

1. **Order Matters**: Place faster middleware first
2. **Conditional Execution**: Skip processing when not needed
3. **Efficient Storage**: Use appropriate storage backends
4. **Memory Management**: Avoid memory leaks in long-running processes
