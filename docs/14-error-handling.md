# Error Handling

The LeanPHP framework provides a comprehensive error handling system that converts all exceptions into standardized HTTP responses using RFC 7807 Problem Details format. This system ensures consistent error responses across your API while providing appropriate information based on the environment configuration.

## Table of Contents

- [Overview](#overview)
- [ErrorHandler Middleware](#errorhandler-middleware)
- [Problem Class (RFC 7807)](#problem-class-rfc-7807)
- [Exception Types](#exception-types)
- [Debug Mode vs Production Mode](#debug-mode-vs-production-mode)
- [Error Response Format](#error-response-format)
- [Validation Error Handling](#validation-error-handling)
- [HTTP Status Codes](#http-status-codes)
- [Security Considerations](#security-considerations)
- [Integration with Other Components](#integration-with-other-components)
- [Testing Error Handling](#testing-error-handling)

## Overview

The error handling system in LeanPHP consists of two main components:

1. **ErrorHandler Middleware** (`app/Middleware/ErrorHandler.php`) - Catches all exceptions and converts them to appropriate HTTP responses
2. **Problem Class** (`src/Http/Problem.php`) - Implements RFC 7807 Problem Details for standardized error responses

### Key Features

- **Standardized Responses**: All errors follow RFC 7807 Problem Details format
- **Environment-Aware**: Shows detailed error information in debug mode, generic messages in production
- **Exception Type Handling**: Special handling for validation exceptions and other specific exception types
- **Security-First**: Prevents information disclosure in production environments
- **Consistent Format**: All error responses have the same structure and content-type

## ErrorHandler Middleware

The `ErrorHandler` middleware is the central component responsible for catching and processing all unhandled exceptions in your application.

### How It Works

```php
namespace App\Middleware;

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

The middleware:
1. **Wraps the entire request pipeline** in a try-catch block
2. **Allows normal execution** when no exceptions occur
3. **Catches all throwables** (exceptions and errors)
4. **Converts exceptions** to standardized Problem responses
5. **Adds context information** like the request path

### Exception Processing Flow

```php
private function handleException(Throwable $e, Request $request): Response
{
    $debug = env_bool('APP_DEBUG', false);

    // Special handling for validation exceptions
    if ($e instanceof ValidationException) {
        $response = $e->getResponse();
        $body = json_decode($response->getBody(), true);
        $body['instance'] = $request->path();
        $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $response->setBody($encodedBody ?: '{"error": "Encoding failed"}');
    }

    // Default error handling
    $status = 500;
    $title = 'Internal Server Error';
    $type = '/problems/internal-server-error';
    $instance = $request->path();
    $detail = $debug ? $e->getMessage() : 'An unexpected error occurred';

    $problem = Problem::make($status, $title, $detail, $type, null, $instance);

    // Add debug information if enabled
    if ($debug) {
        $debugInfo = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 10),
            'class' => get_class($e),
        ];

        $body = json_decode($problem->getBody(), true);
        $body['debug'] = $debugInfo;
        $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $problem = $problem->setBody($encodedBody ?: '{"error": "Failed to encode debug information"}');
    }

    return $problem;
}
```

### Key Processing Steps

1. **Environment Detection**: Checks `APP_DEBUG` configuration
2. **Exception Type Check**: Special handling for `ValidationException`
3. **Instance Path Addition**: Adds the request path to identify where the error occurred
4. **Message Filtering**: Shows detailed messages in debug mode, generic messages in production
5. **Debug Information**: Includes stack trace and exception details when debugging
6. **Response Construction**: Creates standardized Problem response

## Problem Class (RFC 7807)

The `Problem` class implements RFC 7807 "Problem Details for HTTP APIs" specification, providing a standardized way to return error information.

### Core Structure

```php
public static function make(
    int $status,
    string $title,
    ?string $detail = null,
    string $type = '/problems/generic',
    ?array $errors = null,
    ?string $instance = null
): Response
```

### Problem Response Format

```json
{
    "type": "/problems/validation",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The request contains validation errors",
    "instance": "/api/users",
    "errors": {
        "email": ["Email is required"],
        "password": ["Password must be at least 8 characters"]
    }
}
```

### Field Descriptions

- **type**: URI identifying the problem type (RFC 7807 requirement)
- **title**: Human-readable summary of the problem type
- **status**: HTTP status code
- **detail**: Human-readable explanation specific to this occurrence
- **instance**: URI identifying the specific occurrence (usually the request path)
- **errors**: Additional error-specific data (optional, used for validation errors)

### Factory Methods

The Problem class provides convenient factory methods for common HTTP error responses:

```php
// 400 Bad Request
Problem::badRequest('Invalid JSON body');

// 401 Unauthorized
Problem::unauthorized('Authentication required');

// 403 Forbidden
Problem::forbidden('Insufficient permissions');

// 404 Not Found
Problem::notFound('Resource not found');

// 405 Method Not Allowed
Problem::methodNotAllowed(['GET', 'POST']);

// 415 Unsupported Media Type
Problem::unsupportedMediaType('JSON required');

// 422 Unprocessable Content (validation errors)
Problem::validation($errors);

// 429 Too Many Requests
Problem::tooManyRequests('Rate limited', 60);

// 500 Internal Server Error
Problem::internalServerError('Database unavailable');
```

### Response Headers

All Problem responses include:
```http
Content-Type: application/problem+json
```

Some methods add additional headers:
```php
// 405 Method Not Allowed includes allowed methods
$response->header('allow', implode(', ', $allowedMethods));

// 429 Too Many Requests includes retry information
$response->header('retry-after', (string) $retryAfter);
```

## Exception Types

### Standard Exceptions

Most exceptions are handled as 500 Internal Server Error responses:

```php
try {
    $result = $database->query('SELECT * FROM users');
} catch (PDOException $e) {
    // Caught by ErrorHandler middleware
    // Returns 500 with generic message in production
    // Returns 500 with PDO error message in debug mode
}
```

### ValidationException

`ValidationException` receives special treatment because it already contains a properly formatted Problem response:

```php
// In controller
$validator = Validator::make($data, $rules);
$validator->validate(); // Throws ValidationException if validation fails

// In ErrorHandler
if ($e instanceof ValidationException) {
    $response = $e->getResponse(); // Already a Problem response
    // Add instance path and return
}
```

### Custom Exception Handling

You can extend the ErrorHandler to handle specific exception types:

```php
// In handleException method
if ($e instanceof DatabaseConnectionException) {
    return Problem::make(503, 'Service Unavailable', 'Database temporarily unavailable');
}

if ($e instanceof RateLimitException) {
    return Problem::tooManyRequests('API rate limit exceeded', $e->getRetryAfter());
}
```

## Debug Mode vs Production Mode

### Debug Mode (APP_DEBUG=true)

When debug mode is enabled:

```json
{
    "type": "/problems/internal-server-error",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Undefined variable: invalidVar",
    "instance": "/api/users/123",
    "debug": {
        "message": "Undefined variable: invalidVar",
        "file": "/app/Controllers/UserController.php",
        "line": 45,
        "trace": [
            {
                "file": "/app/Controllers/UserController.php",
                "line": 45,
                "function": "show",
                "class": "App\\Controllers\\UserController"
            }
        ],
        "class": "Error"
    }
}
```

**Debug Information Includes:**
- **Original exception message**
- **File path where error occurred**
- **Line number**
- **Stack trace** (limited to 10 frames for performance)
- **Exception class name**

### Production Mode (APP_DEBUG=false)

In production mode:

```json
{
    "type": "/problems/internal-server-error",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "An unexpected error occurred",
    "instance": "/api/users/123"
}
```

**Security Features:**
- **Generic error messages** prevent information disclosure
- **No stack traces** to avoid revealing application structure
- **No file paths** to prevent directory structure disclosure
- **No debug information** to maintain security

## Error Response Format

### Content-Type

All error responses use the standard Problem content type:
```http
Content-Type: application/problem+json
```

### Response Structure

Basic error response:
```json
{
    "type": "string",       // Required: Problem type URI
    "title": "string",      // Required: Human-readable summary
    "status": 500,          // Required: HTTP status code
    "detail": "string",     // Optional: Specific details
    "instance": "string"    // Optional: Request path
}
```

Extended error response (validation):
```json
{
    "type": "/problems/validation",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The request contains validation errors",
    "instance": "/api/users",
    "errors": {
        "field1": ["Error message 1", "Error message 2"],
        "field2": ["Error message 3"]
    }
}
```

Debug mode addition:
```json
{
    "type": "/problems/internal-server-error",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Database connection failed",
    "instance": "/api/users",
    "debug": {
        "message": "SQLSTATE[08006] [7] could not connect to server",
        "file": "/app/Database/Connection.php",
        "line": 23,
        "trace": [...],
        "class": "PDOException"
    }
}
```

## Validation Error Handling

### ValidationException Flow

1. **Validator fails** validation rules
2. **ValidationException** is thrown with a Problem response
3. **ErrorHandler** catches the exception
4. **Instance path** is added to the response
5. **Original Problem response** is returned

```php
// In Validator class
if ($this->fails()) {
    $problem = Problem::make(
        422,
        'Unprocessable Content',
        'The given data was invalid.',
        '/problems/validation'
    );

    $body = json_decode($problem->getBody(), true);
    $body['errors'] = $this->errors();
    $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $problem = $problem->setBody($encodedBody);

    throw new ValidationException($problem);
}

// In ErrorHandler
if ($e instanceof ValidationException) {
    $response = $e->getResponse();
    $body = json_decode($response->getBody(), true);
    $body['instance'] = $request->path(); // Add request path
    $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $response->setBody($encodedBody ?: '{"error": "Encoding failed"}');
}
```

### Validation Error Structure

```json
{
    "type": "/problems/validation",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The given data was invalid.",
    "instance": "/api/users",
    "errors": {
        "name": [
            "The name field is required."
        ],
        "email": [
            "The email field is required.",
            "The email must be a valid email address."
        ],
        "password": [
            "The password must be at least 8 characters."
        ]
    }
}
```

## HTTP Status Codes

The error handling system uses appropriate HTTP status codes:

### Client Errors (4xx)

- **400 Bad Request**: Invalid request format, malformed JSON
- **401 Unauthorized**: Authentication required or failed
- **403 Forbidden**: Authenticated but insufficient permissions
- **404 Not Found**: Resource or endpoint not found
- **405 Method Not Allowed**: HTTP method not supported for endpoint
- **415 Unsupported Media Type**: Incorrect Content-Type header
- **422 Unprocessable Content**: Validation errors
- **429 Too Many Requests**: Rate limit exceeded

### Server Errors (5xx)

- **500 Internal Server Error**: Unhandled exceptions and server errors
- **503 Service Unavailable**: Temporary service issues (database down, etc.)

### Usage Examples

```php
// In middleware or controllers
return Problem::badRequest('Invalid JSON format');           // 400
return Problem::unauthorized('Token expired');               // 401
return Problem::forbidden('Admin access required');          // 403
return Problem::notFound('User not found');                 // 404
return Problem::methodNotAllowed(['GET', 'POST']);          // 405
return Problem::unsupportedMediaType('JSON required');      // 415
return Problem::validation($validationErrors);              // 422
return Problem::tooManyRequests('Rate limited', 60);        // 429
return Problem::internalServerError('Database error');      // 500
```

## Security Considerations

### Information Disclosure Prevention

**Production Mode Security:**
- **Generic error messages** prevent revealing internal implementation details
- **No stack traces** to avoid exposing code structure
- **No file paths** to prevent directory traversal information
- **No database error details** to prevent schema disclosure

**Debug Mode Cautions:**
- **Only enable in development** environments
- **Never use in production** to prevent information leakage
- **Sensitive information** may be exposed in stack traces

### Error Logging

While not implemented in the basic ErrorHandler, production applications should log errors:

```php
// Enhanced error handling (example)
private function handleException(Throwable $e, Request $request): Response
{
    // Log the error regardless of debug mode
    error_log(sprintf(
        'Exception: %s in %s:%d - Request: %s %s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $request->method(),
        $request->path()
    ));

    // Continue with normal error handling...
}
```

### Safe Error Responses

- **Consistent response format** prevents information leakage through response structure
- **Standard HTTP status codes** don't reveal internal application logic
- **Generic problem types** avoid exposing implementation details

## Integration with Other Components

### Middleware Pipeline

The ErrorHandler should be placed early in the middleware pipeline to catch exceptions from all other middleware:

```php
$middlewareStack = [
    new ErrorHandler(),      // Must be first to catch all exceptions
    new RateLimiter(),
    new CORS(),
    new AuthBearer(),
    // ... other middleware
];
```

### Validation System Integration

The error handler seamlessly integrates with the validation system:

```php
// In UserController
public function create(Request $request): Response
{
    $validator = Validator::make($request->json(), [
        'email' => 'required|email',
        'password' => 'required|min:8'
    ]);

    $validator->validate(); // May throw ValidationException

    // If validation passes, continue...
}

// ValidationException is automatically caught by ErrorHandler
// and converted to proper 422 response with validation errors
```

### Router Integration

The router integration ensures all route-related errors are properly handled:

```php
// In Router
public function dispatch(Request $request): Response
{
    try {
        // Route matching and controller execution
        return $this->executeRoute($request);
    } catch (Throwable $e) {
        // All exceptions bubble up to ErrorHandler middleware
        throw $e;
    }
}
```

### Database Error Handling

Database exceptions are automatically converted to appropriate responses:

```php
// In a controller or service
try {
    $users = DB::select('SELECT * FROM users WHERE id = ?', [$id]);
} catch (PDOException $e) {
    // Automatically caught by ErrorHandler
    // Returns 500 with appropriate message based on debug mode
}
```

## Testing Error Handling

### Unit Testing

Test the ErrorHandler middleware behavior:

```php
class ErrorHandlerTest extends TestCase
{
    public function test_debug_mode_shows_exception_details(): void
    {
        $_ENV['APP_DEBUG'] = 'true';

        $exception = new Exception('Test exception message', 123);

        $response = $this->errorHandler->handle($this->request, function() use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('Test exception message', $body['detail']);
        $this->assertArrayHasKey('debug', $body);
        $this->assertEquals('Test exception message', $body['debug']['message']);
    }

    public function test_production_mode_hides_exception_details(): void
    {
        $_ENV['APP_DEBUG'] = 'false';

        $exception = new Exception('Sensitive internal error message', 123);

        $response = $this->errorHandler->handle($this->request, function() use ($exception) {
            throw $exception;
        });

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('An unexpected error occurred', $body['detail']);
        $this->assertArrayNotHasKey('debug', $body);
    }
}
```

### Integration Testing

Test error handling in complete request flows:

```php
public function test_validation_error_handling(): void
{
    $response = $this->makeRequest('POST', '/api/users', [
        'name' => '',
        'email' => 'invalid-email'
    ]);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

    $body = json_decode($response->getBody(), true);
    $this->assertEquals('/problems/validation', $body['type']);
    $this->assertArrayHasKey('errors', $body);
}

public function test_not_found_error_handling(): void
{
    $response = $this->makeRequest('GET', '/non-existent-endpoint');

    $this->assertEquals(404, $response->getStatusCode());
    $body = json_decode($response->getBody(), true);
    $this->assertEquals('/problems/not-found', $body['type']);
}
```

### Error Scenarios to Test

1. **Validation failures** - 422 responses with error details
2. **Authentication failures** - 401 responses
3. **Authorization failures** - 403 responses
4. **Resource not found** - 404 responses
5. **Method not allowed** - 405 responses
6. **Rate limiting** - 429 responses
7. **Server errors** - 500 responses
8. **Debug vs production** - Different response content based on APP_DEBUG
9. **Special exception types** - ValidationException handling
