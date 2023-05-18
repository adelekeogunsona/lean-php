# Built-in Middleware

LeanPHP provides a comprehensive collection of built-in middleware that handles common cross-cutting concerns like authentication, CORS, error handling, rate limiting, and request/response processing. Each middleware is designed to be lightweight, secure, and easy to configure.

## 🏗️ Middleware Architecture

### Middleware Interface

All middleware in LeanPHP implements a simple interface with a single `handle` method:

```php
public function handle(Request $request, callable $next): Response
```

- **`$request`**: The incoming HTTP request object
- **`$next`**: Callable that invokes the next middleware or route handler
- **Returns**: HTTP response (either from `$next()` or created by the middleware)

### Middleware Pipeline

Middleware operates on the "onion" model - each middleware layer wraps around the next, creating a pipeline where requests pass through middleware in one direction and responses pass back through in reverse order:

```
Request → Middleware1 → Middleware2 → Middleware3 → Handler
Response ← Middleware1 ← Middleware2 ← Middleware3 ← Handler
```

### Pipeline Implementation

The framework implements middleware pipelines in two places:

1. **Global Pipeline**: Handled by `MiddlewareRunner` in `public/index.php`
2. **Route Pipeline**: Handled by `Router` for route-specific middleware

```php
// Global middleware setup in public/index.php
$middlewareRunner = new MiddlewareRunner();
$middlewareRunner->add(new ErrorHandler());      // Catch all exceptions
$middlewareRunner->add(new RequestId());         // Add request tracking
$middlewareRunner->add(new Cors());              // Handle CORS
$middlewareRunner->add(new JsonBodyParser());    // Validate JSON

$response = $middlewareRunner->handle($request, function (Request $request) use ($router) {
    return $router->dispatch($request);
});
```

## 📦 Built-in Middleware Components

### 1. ErrorHandler Middleware

**Location**: `app/Middleware/ErrorHandler.php`
**Purpose**: Catches and handles all exceptions, converting them to appropriate HTTP responses

#### How It Works

```php
class ErrorHandler
{
    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
}
```

#### Exception Handling Logic

1. **ValidationException**: Converts to 422 responses with field-specific errors
2. **General Exceptions**: Converts to 500 responses with debug info (if enabled)
3. **Production Mode**: Hides sensitive error details
4. **Debug Mode**: Shows full exception details including stack traces

#### Configuration

- **`APP_DEBUG`**: Controls error detail visibility
  - `true`: Shows exception messages, stack traces, debug info
  - `false`: Returns generic "Internal Server Error" message

#### Response Format

**Validation Error (422):**
```json
{
    "type": "/problems/validation",
    "title": "Validation Failed",
    "status": 422,
    "detail": "The request data failed validation",
    "instance": "/api/users",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

**Server Error (500) - Debug Mode:**
```json
{
    "type": "/problems/internal-server-error",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Division by zero",
    "instance": "/api/calculate",
    "debug": {
        "message": "Division by zero",
        "file": "/app/Controllers/MathController.php",
        "line": 42,
        "trace": [...],
        "class": "DivisionByZeroError"
    }
}
```

#### Features

- **RFC 7807 Compliance**: Returns Problem+JSON responses
- **Instance Tracking**: Includes request path in error responses
- **Logging Integration**: Logs all unhandled exceptions
- **Production Safety**: Hides sensitive data in production
- **Stack Trace Limiting**: Limits trace depth to prevent memory issues

### 2. CORS Middleware

**Location**: `app/Middleware/Cors.php`
**Purpose**: Handles Cross-Origin Resource Sharing (CORS) for browser security

#### How It Works

```php
class Cors
{
    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // Process the actual request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin);
    }
}
```

#### Configuration

All CORS settings are configured via environment variables:

```bash
CORS_ALLOW_ORIGINS=*                                        # or comma-separated list
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS       # HTTP methods
CORS_ALLOW_HEADERS=Authorization,Content-Type              # Request headers
CORS_MAX_AGE=600                                           # Preflight cache (seconds)
CORS_ALLOW_CREDENTIALS=false                               # Allow cookies/auth
```

#### Preflight Request Handling

For OPTIONS requests, the middleware:

1. **Validates Origin**: Checks if requesting origin is allowed
2. **Validates Method**: Ensures requested method is permitted
3. **Validates Headers**: Checks if requested headers are allowed
4. **Returns 204**: With appropriate CORS headers or empty response

#### CORS Headers Added

**Preflight Response:**
```
Access-Control-Allow-Origin: https://example.com
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type
Access-Control-Max-Age: 600
Access-Control-Allow-Credentials: true
Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers
```

**Actual Response:**
```
Access-Control-Allow-Origin: https://example.com
Access-Control-Allow-Credentials: true
Vary: Origin
```

#### Security Features

- **Origin Validation**: Only allows configured origins
- **Wildcard Handling**: Supports `*` for development, specific origins for production
- **Credentials Security**: When credentials enabled, never returns `*` for origin
- **Vary Headers**: Proper cache control for proxies and CDNs

### 3. AuthBearer Middleware

**Location**: `app/Middleware/AuthBearer.php`
**Purpose**: Provides JWT-based Bearer token authentication

#### How It Works

```php
class AuthBearer
{
    public function handle(Request $request, callable $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        try {
            $claims = Token::verify($bearerToken);
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
}
```

#### Authentication Flow

1. **Extract Token**: Gets Bearer token from `Authorization` header
2. **Verify Token**: Uses `Token::verify()` to validate JWT signature and expiration
3. **Extract Claims**: Parses JWT payload and makes claims available
4. **Attach to Request**: Stores claims in request object for controller access
5. **Error Handling**: Returns 401 responses for invalid/missing tokens

#### JWT Token Format

Expected header format:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

#### Claims Access

After successful authentication, controllers can access user claims:

```php
public function profile(Request $request): Response
{
    $userId = $request->claim('sub');        // User ID
    $email = $request->claim('email');       // User email
    $scopes = $request->claim('scopes', []); // User permissions

    // Check if authenticated
    if (!$request->isAuthenticated()) {
        return Problem::unauthorized('Authentication required');
    }
}
```

#### Error Responses

**Missing Token (401):**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Missing or invalid Authorization header",
    "instance": "/api/profile"
}
```

**Invalid Token (401):**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Invalid token: Token has expired",
    "instance": "/api/profile"
}
```

#### Features

- **JWT Verification**: Validates signature using configured keys
- **Claims Extraction**: Makes user data available to controllers
- **Proper HTTP Headers**: Returns `WWW-Authenticate` header for 401 responses
- **Error Distinction**: Different errors for missing, invalid, and expired tokens
- **Security Integration**: Works with `Request::isAuthenticated()` method

### 4. RateLimiter Middleware

**Location**: `app/Middleware/RateLimiter.php`
**Purpose**: Implements request rate limiting to prevent API abuse

#### How It Works

```php
class RateLimiter
{
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        // Check if request is allowed
        if (!$this->store->allow($key, $limit, $window)) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        // Record the hit and continue
        $result = $this->store->hit($key, $limit, $window);
        $response = $next($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $limit, $result['remaining'], $result['resetAt']);
    }
}
```

#### Rate Limiting Strategy

1. **Key Generation**: Creates unique keys based on user ID (if authenticated) or IP address
2. **Limit Checking**: Validates against configured limits before processing
3. **Hit Recording**: Records the request after processing
4. **Header Addition**: Adds rate limit information to all responses

#### Storage Backends

**File Store** (`RATE_LIMIT_STORE=file`):
- Stores data in `storage/ratelimit/` directory
- Good for development and single-server deployments
- Persists across PHP process restarts

**APCu Store** (`RATE_LIMIT_STORE=apcu`):
- Uses APCu shared memory extension
- High performance for production
- Requires APCu extension to be installed

#### Configuration

```bash
RATE_LIMIT_STORE=file        # Storage backend: file|apcu
RATE_LIMIT_DEFAULT=60        # Requests per window
RATE_LIMIT_WINDOW=60         # Window duration in seconds
```

#### Rate Limit Headers

All responses include rate limiting information:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

#### Rate Limit Exceeded Response (429)

```json
{
    "type": "/problems/too-many-requests",
    "title": "Too Many Requests",
    "status": 429,
    "detail": "Rate limit exceeded",
    "instance": "/api/users",
    "retryAfter": 65
}
```

#### Features

- **User-based Limiting**: Uses user ID for authenticated requests
- **IP-based Fallback**: Uses client IP for unauthenticated requests
- **Jitter**: Adds 1-5 second jitter to prevent thundering herd
- **Configurable Storage**: Supports multiple storage backends
- **Performance Optimized**: Efficient algorithms for high-throughput APIs

### 5. ETag Middleware

**Location**: `app/Middleware/ETag.php`
**Purpose**: Implements HTTP ETag caching for improved performance

#### How It Works

```php
class ETag
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (!$this->shouldApplyETag($request, $response)) {
            return $response;
        }

        $etag = $this->generateETag($response->getBody());
        $ifNoneMatch = $request->header('if-none-match');

        if ($ifNoneMatch && $this->etagMatches($ifNoneMatch, $etag)) {
            return Response::noContent()
                ->status(304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        return $response->header('ETag', $etag);
    }
}
```

#### ETag Generation

1. **Content Hashing**: Generates SHA256 hash of response body
2. **Base64URL Encoding**: Encodes hash using base64url (URL-safe)
3. **Quote Wrapping**: Wraps in quotes per HTTP specification

```php
private function generateETag(string $content): string
{
    $hash = hash('sha256', $content, true);
    $base64url = $this->base64UrlEncode($hash);
    return '"' . $base64url . '"';
}
```

#### Conditional Request Handling

**Client Request with ETag:**
```
GET /api/users
If-None-Match: "abc123def456"
```

**Server Response - Content Changed (200):**
```
HTTP/1.1 200 OK
ETag: "xyz789uvw012"
Content-Type: application/json

{"users": [...]}
```

**Server Response - Content Unchanged (304):**
```
HTTP/1.1 304 Not Modified
ETag: "abc123def456"
Cache-Control: private, max-age=0, must-revalidate
```

#### Application Conditions

ETag is only applied when:
- **GET Requests**: Only applies to GET requests
- **200 Status**: Only for successful responses
- **JSON Content**: Only for `application/json` responses
- **Non-empty Body**: Must have response content

#### Features

- **Performance Optimization**: Reduces bandwidth for unchanged content
- **Cache Control**: Proper cache headers for browsers and proxies
- **Wildcard Support**: Handles `If-None-Match: *` correctly
- **Multiple ETags**: Supports comma-separated ETag lists
- **Security**: Uses cryptographically secure hashing

### 6. JsonBodyParser Middleware

**Location**: `app/Middleware/JsonBodyParser.php`
**Purpose**: Validates JSON content type and format for API endpoints

#### How It Works

```php
class JsonBodyParser
{
    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        $contentType = $request->header('content-type');

        // Skip validation for methods without bodies
        if (in_array($method, $this->methodsAllowedWithoutJson)) {
            return $next($request);
        }

        // For methods with bodies, validate content type and JSON format
        if (in_array($method, $this->methodsRequiringJson)) {
            if (!$contentType || !str_contains($contentType, 'application/json')) {
                return Problem::make(415, 'Unsupported Media Type',
                    'Content-Type must be application/json');
            }

            // Validate JSON format
            $body = $this->getRequestBody();
            if ($body !== '') {
                json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return Problem::make(400, 'Bad Request',
                        'Invalid JSON format: ' . json_last_error_msg());
                }
            }
        }

        return $next($request);
    }
}
```

#### Method Classification

**Methods Requiring JSON** (`POST`, `PUT`, `PATCH`):
- Must have `Content-Type: application/json` if body present
- Body content must be valid JSON

**Methods Allowed Without JSON** (`GET`, `HEAD`, `OPTIONS`, `DELETE`):
- No content type validation
- Pass through without processing

#### Validation Process

1. **Content-Type Check**: Ensures `application/json` for body-bearing methods
2. **Body Presence**: Checks `Content-Length` and `Transfer-Encoding` headers
3. **JSON Validation**: Attempts to parse JSON and checks for syntax errors
4. **Error Responses**: Returns appropriate 400/415 responses for failures

#### Error Responses

**Unsupported Media Type (415):**
```json
{
    "type": "/problems/unsupported-media-type",
    "title": "Unsupported Media Type",
    "status": 415,
    "detail": "Content-Type must be application/json",
    "instance": "/api/users"
}
```

**Invalid JSON (400):**
```json
{
    "type": "/problems/invalid-json",
    "title": "Bad Request",
    "status": 400,
    "detail": "Invalid JSON format: Syntax error",
    "instance": "/api/users"
}
```

#### Features

- **Content-Type Validation**: Ensures proper API usage
- **JSON Syntax Checking**: Prevents malformed data from reaching controllers
- **Method-Aware**: Different rules for different HTTP methods
- **Performance**: Validates once, cached for request duration
- **Clear Errors**: Specific error messages for different failure types

### 7. RequestId Middleware

**Location**: `app/Middleware/RequestId.php`
**Purpose**: Adds unique request tracking for logging and debugging

#### How It Works

```php
class RequestId
{
    public function handle(Request $request, callable $next): Response
    {
        // Get or generate request ID
        $requestId = $request->header('x-request-id') ?: $this->generateRequestId();

        // Set request ID in logger context
        $logger = Logger::getInstance();
        $logger->setContext(['request_id' => $requestId]);

        // Log incoming request
        $logger->info('Incoming request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->header('user-agent'),
        ]);

        // Process request
        $response = $next($request);

        // Add request ID to response headers
        $response = $response->header('X-Request-Id', $requestId);

        // Log outgoing response
        $logger->info('Outgoing response', [
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
```

#### Request ID Generation

```php
private function generateRequestId(): string
{
    return sprintf(
        '%s-%s',
        bin2hex(random_bytes(4)),  // 8 character hex
        bin2hex(random_bytes(4))   // 8 character hex
    );
}
```

Generates IDs like: `a1b2c3d4-e5f6g7h8`

#### Request ID Flow

1. **Check Header**: Looks for existing `X-Request-Id` header
2. **Generate if Missing**: Creates new ID if not provided
3. **Logger Context**: Sets ID in logger for all subsequent log entries
4. **Request Logging**: Logs incoming request details
5. **Response Header**: Adds ID to response for client tracking
6. **Response Logging**: Logs outgoing response details

#### Logging Integration

All log entries include the request ID:

```
[2024-09-07 10:15:30] INFO: Incoming request {"request_id":"a1b2c3d4-e5f6g7h8","method":"POST","path":"/api/users"}
[2024-09-07 10:15:30] INFO: User created {"request_id":"a1b2c3d4-e5f6g7h8","user_id":123}
[2024-09-07 10:15:30] INFO: Outgoing response {"request_id":"a1b2c3d4-e5f6g7h8","status":201}
```

#### Features

- **Request Tracking**: Unique ID for each request
- **Log Correlation**: All logs for a request share the same ID
- **Client Forwarding**: Accepts client-provided request IDs
- **Response Headers**: Returns ID for client-side tracking
- **Debugging Aid**: Simplifies tracing issues across logs

### 8. RequireScopes Middleware

**Location**: `app/Middleware/RequireScopes.php`
**Purpose**: Implements scope-based authorization for fine-grained access control

#### How It Works

```php
class RequireScopes
{
    public function handle(Request $request, callable $next): Response
    {
        // Check authentication first
        if (!$request->isAuthenticated()) {
            return Problem::unauthorized('This endpoint requires authentication')
                ->header('WWW-Authenticate', 'Bearer realm="API"');
        }

        // Get token scopes
        $tokenScopes = $request->claim('scopes', []);

        if (!is_array($tokenScopes)) {
            return Problem::forbidden('Token does not contain valid scopes');
        }

        // Check if all required scopes are present
        foreach ($this->requiredScopes as $requiredScope) {
            if (!in_array($requiredScope, $tokenScopes, true)) {
                return Problem::forbidden("Required scope '$requiredScope' is missing");
            }
        }

        return $next($request);
    }
}
```

#### Usage in Routes

```php
// Single scope requirement
$router->get('/admin/users', [AdminController::class, 'users'], [
    AuthBearer::class,
    RequireScopes::check('admin:users:read')
]);

// Multiple scope requirement
$router->post('/admin/users', [AdminController::class, 'create'], [
    AuthBearer::class,
    RequireScopes::check('admin:users:write,admin:users:create')
]);

// Using constructor directly
$router->patch('/admin/settings', [AdminController::class, 'updateSettings'], [
    AuthBearer::class,
    new RequireScopes('admin:settings:write')
]);
```

#### Scope Format

Recommended scope naming convention:
```
resource:action:target
admin:users:read        # Read user data
admin:users:write       # Modify user data
admin:users:delete      # Delete users
user:profile:read       # Read own profile
user:profile:write      # Update own profile
billing:invoices:read   # Read billing data
```

#### Authorization Flow

1. **Authentication Check**: Ensures user is authenticated via JWT
2. **Scope Extraction**: Gets `scopes` claim from JWT token
3. **Scope Validation**: Checks if token contains required scopes
4. **Access Control**: Allows/denies based on scope matching

#### Error Responses

**Not Authenticated (401):**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "This endpoint requires authentication",
    "instance": "/api/admin/users"
}
```

**Missing Scope (403):**
```json
{
    "type": "/problems/forbidden",
    "title": "Forbidden",
    "status": 403,
    "detail": "Required scope 'admin:users:read' is missing",
    "instance": "/api/admin/users"
}
```

#### Features

- **Fine-grained Access**: Control access at feature/resource level
- **JWT Integration**: Works with JWT claims from AuthBearer middleware
- **Multiple Scopes**: Supports requiring multiple scopes simultaneously
- **Clear Errors**: Specific error messages for missing scopes
- **Helper Methods**: `check()` method for easier route definitions

## 🔄 Middleware Execution Order

### Pipeline Execution Sequence

Middleware executes in this specific order:

1. **Global Pipeline Middleware** (from `MiddlewareRunner`)
2. **Router Global Middleware** (from `$router->setGlobalMiddleware()`)
3. **Route Group Middleware** (from route group definitions)
4. **Route-Specific Middleware** (from individual route definitions)
5. **Route Handler** (controller or closure)

### Recommended Order

```php
// Global middleware order (in public/index.php)
$middlewareRunner->add(new ErrorHandler());      // First - catch all exceptions
$middlewareRunner->add(new RequestId());         // Second - add request tracking
$middlewareRunner->add(new Cors());              // Third - handle preflight
$middlewareRunner->add(new JsonBodyParser());    // Fourth - validate content

// Route-specific middleware order
$router->post('/api/users', [UserController::class, 'create'], [
    RateLimiter::class,     // First - prevent abuse
    AuthBearer::class,      // Second - authenticate user
    RequireScopes::class,   // Third - authorize action
    ETag::class            // Last - optimize response
]);
```

### Order Importance

**ErrorHandler First**: Must be first to catch exceptions from all other middleware

**RequestId Early**: Should be early to ensure all logs have request ID

**CORS Before Auth**: CORS must handle preflight OPTIONS requests before authentication

**RateLimit Before Auth**: Prevent unnecessary authentication work for rate-limited requests

**Auth Before Authorization**: Must authenticate before checking permissions

**ETag Last**: Should be last to cache final response content

## 🚀 Performance Considerations

### Middleware Overhead

- **Instantiation**: Middleware is instantiated on each request
- **Pipeline Depth**: Each middleware layer adds function call overhead
- **Memory Usage**: Middleware objects remain in memory for request duration

### Optimization Strategies

1. **Conditional Processing**: Skip expensive operations when not needed
2. **Early Returns**: Return responses early when possible
3. **Efficient Storage**: Use APCu over file storage for rate limiting
4. **Minimal State**: Keep middleware stateless when possible

### Performance Features

**ETag Middleware**: Allows browsers to cache responses, reducing bandwidth

**Rate Limiter**: Uses APCu for high-performance state storage

**CORS Middleware**: Caches policy decisions to avoid repeated calculations

**JSON Parser**: Validates once and caches results for request duration

## 🔒 Security Best Practices

### Input Validation

- **Content-Type Validation**: JsonBodyParser ensures proper API usage
- **JSON Syntax Checking**: Prevents malformed data injection
- **Origin Validation**: CORS middleware validates request origins

### Authentication & Authorization

- **JWT Verification**: AuthBearer validates token signatures and expiration
- **Scope Checking**: RequireScopes provides fine-grained access control
- **Proper Headers**: Returns correct WWW-Authenticate headers

### Error Handling

- **Information Disclosure**: ErrorHandler hides sensitive data in production
- **Rate Limiting**: Prevents brute force and abuse attacks
- **Request Tracking**: RequestId aids in security monitoring

### Production Configuration

```bash
# Security-focused production settings
APP_DEBUG=false                                    # Hide error details
CORS_ALLOW_ORIGINS=https://app.example.com        # Specific origins
CORS_ALLOW_CREDENTIALS=false                       # Disable credentials
RATE_LIMIT_STORE=apcu                             # High-performance storage
RATE_LIMIT_DEFAULT=60                             # Reasonable limits
```

## 🧪 Testing Middleware

### Unit Testing Middleware

```php
class CorSTest extends TestCase
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
}
```

### Integration Testing

```php
class MiddlewarePipelineTest extends TestCase
{
    public function test_middleware_execution_order(): void
    {
        $executed = [];

        $middleware1 = new class($executed) {
            public function handle(Request $request, callable $next): Response {
                $this->executed[] = 'middleware1-before';
                $response = $next($request);
                $this->executed[] = 'middleware1-after';
                return $response;
            }
        };

        $runner = new MiddlewareRunner();
        $runner->add($middleware1);

        $runner->handle($request, function () use (&$executed) {
            $executed[] = 'handler';
            return Response::json(['test' => true]);
        });

        $this->assertEquals([
            'middleware1-before',
            'handler',
            'middleware1-after'
        ], $executed);
    }
}
```
