# HTTP Foundation

The HTTP Foundation in LeanPHP provides a clean, immutable abstraction layer over PHP's superglobals and HTTP handling. It consists of four core components that work together to handle HTTP requests and responses efficiently and securely.

## Architecture Overview

The HTTP foundation is built around these key classes:

- **`Request`** - Represents incoming HTTP requests
- **`Response`** - Represents outgoing HTTP responses
- **`ResponseEmitter`** - Handles the actual HTTP output
- **`Problem`** - RFC 7807 compliant error responses

## Request Class

The `Request` class provides an immutable representation of an incoming HTTP request. It abstracts PHP's superglobals (`$_SERVER`, `$_GET`, `$_POST`, etc.) into a clean, object-oriented interface.

### Request Creation

```php
// Create from PHP globals (typical usage)
$request = Request::fromGlobals();

// Properties are set during construction and cannot be modified
```

The `fromGlobals()` method intelligently extracts data from PHP's superglobals:

- **HTTP Method**: Extracted from `$_SERVER['REQUEST_METHOD']`, defaulting to 'GET'
- **Path**: Parsed from `$_SERVER['REQUEST_URI']` using `parse_url()` to isolate the path component
- **Headers**: Extracted from `$_SERVER` variables starting with `HTTP_`, plus special handling for `CONTENT_TYPE` and `CONTENT_LENGTH`
- **Query Parameters**: Directly from `$_GET`
- **JSON Body**: Auto-parsed from `php://input` when Content-Type is `application/json`

### Header Processing

Headers are normalized during extraction:

```php
// $_SERVER['HTTP_AUTHORIZATION'] becomes 'authorization'
// $_SERVER['HTTP_X_CUSTOM_HEADER'] becomes 'x-custom-header'
// Special cases: CONTENT_TYPE and CONTENT_LENGTH don't have HTTP_ prefix
```

### Request Methods

```php
// Basic request data
$method = $request->method();           // GET, POST, PUT, etc.
$path = $request->path();               // /api/users/123
$header = $request->header('content-type'); // application/json
$query = $request->query('page', 1);    // Query parameter with default

// JSON body access
$json = $request->json();               // Full decoded JSON array
$name = $request->input('name');        // Specific JSON field
$email = $request->input('email', 'default@example.com'); // With default

// Authentication
$token = $request->bearerToken();       // Extracts token from "Bearer <token>"
$claims = $request->claims();           // JWT claims (set by AuthBearer middleware)
$isAuth = $request->isAuthenticated();  // true if claims are present

// Route parameters (set by router)
$params = $request->params();           // ['id' => '123']

// Client information
$ip = $request->getClientIp();          // Checks X-Forwarded-For, X-Real-IP, REMOTE_ADDR
```

### Security Features

The Request class includes several security considerations:

1. **Header normalization**: All header names are converted to lowercase for consistent access
2. **IP detection**: Proper handling of proxy headers for client IP detection
3. **JSON validation**: JSON is only parsed if the content type is appropriate
4. **Immutability**: Once created, request data cannot be modified (except for route params and claims)

## Response Class

The `Response` class represents HTTP responses and provides a fluent interface for building responses.

### Response Creation

```php
// Static factory methods for common response types
$response = Response::json(['message' => 'Hello'], 200);
$response = Response::text('Plain text response', 200);
$response = Response::noContent(); // 204 No Content
```

### Response Building

```php
// Fluent interface for building responses
$response = Response::json(['data' => $data])
    ->status(201)
    ->header('X-Custom', 'value')
    ->header('Cache-Control', 'no-cache');

// Get response data
$status = $response->getStatusCode();      // 201
$headers = $response->getHeaders();        // ['content-type' => 'application/json', ...]
$body = $response->getBody();              // JSON string
$header = $response->getHeader('x-custom'); // 'value'
```

### Special Response Methods

```php
// Create response without body (for HEAD requests)
$headResponse = $response->withoutBody();

// Set body directly
$response->setBody('Custom body content');
```

### Header Management

Headers are stored in lowercase internally for consistency:

```php
$response->header('Content-Type', 'application/json');
$value = $response->getHeader('content-type'); // Works with any case
```

## ResponseEmitter Class

The `ResponseEmitter` handles the actual HTTP output, converting the Response object into real HTTP headers and body output.

### Emission Process

```php
ResponseEmitter::emit($response);
```

The emission process involves several steps:

1. **Status Code**: Set using `http_response_code()`
2. **Header Preparation**: Add Content-Length, format header names, sanitize values
3. **Header Output**: Send headers using `header()` function
4. **Body Output**: Send body content (if appropriate for the status code)

### Header Processing

The emitter performs several important processing steps:

```php
// Content-Length calculation
$headers = self::prepareHeaders($response->getHeaders(), $body, $statusCode);

// Header name formatting (Title-Case)
$formattedName = self::formatHeaderName($name); // 'content-type' -> 'Content-Type'

// Header value sanitization (remove control characters)
$sanitizedValue = self::sanitizeHeaderValue($value);
```

### Special Status Code Handling

The emitter knows which responses should not have a body:

```php
private static function shouldOmitBody(int $statusCode): bool
{
    return $statusCode === 204      // No Content
        || $statusCode === 304      // Not Modified
        || ($statusCode >= 100 && $statusCode < 200); // 1xx responses
}
```

### Security Features

1. **Header injection protection**: Control characters are stripped from header values
2. **Proper formatting**: Headers are formatted according to HTTP standards
3. **Content-Length handling**: Automatically calculated and added when appropriate

## Problem Class (RFC 7807)

The `Problem` class implements RFC 7807 "Problem Details for HTTP APIs" for consistent error responses.

### Problem Response Structure

```json
{
    "type": "/problems/validation",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The request contains validation errors",
    "instance": "/v1/users",
    "errors": {
        "email": ["Email is required"],
        "password": ["Password must be at least 8 characters"]
    }
}
```

### Factory Methods

```php
// Common HTTP errors
$response = Problem::badRequest('Invalid input');           // 400
$response = Problem::unauthorized('Login required');        // 401
$response = Problem::forbidden('Access denied');            // 403
$response = Problem::notFound('Resource not found');        // 404
$response = Problem::methodNotAllowed(['GET', 'POST']);     // 405
$response = Problem::unsupportedMediaType('JSON required'); // 415
$response = Problem::validation($errors);                   // 422
$response = Problem::tooManyRequests('Rate limited', 60);   // 429

// Custom problems
$response = Problem::make(
    status: 418,
    title: "I'm a teapot",
    detail: "Cannot brew coffee",
    type: '/problems/teapot',
    errors: null,
    instance: '/coffee'
);
```

### Features

1. **Content-Type**: Automatically sets `application/problem+json`
2. **Standard Fields**: Supports all RFC 7807 standard fields
3. **Additional Headers**: Methods like `methodNotAllowed()` add appropriate headers
4. **Validation Errors**: Special handling for validation error collections

## Request Lifecycle

Here's how a request flows through the HTTP foundation:

```php
// 1. Request creation from globals
$request = Request::fromGlobals();

// 2. Middleware processing (adds route params, JWT claims, etc.)
$request->setParams(['id' => '123']);
$request->setClaims(['sub' => 'user123']);

// 3. Controller/handler execution
$response = $controller->handle($request);

// 4. Response emission
ResponseEmitter::emit($response);
```

## Testing Support

The HTTP foundation includes features specifically for testing:

### Request Testing

```php
// Set JSON body for testing
$request->setJsonBody(['name' => 'Test User']);

// Create mock requests using reflection
$reflection = new \ReflectionClass(Request::class);
$request = $reflection->newInstanceWithoutConstructor();
```

### Response Testing

```php
// Check response properties
$this->assertEquals(200, $response->getStatusCode());
$this->assertEquals('application/json', $response->getHeader('content-type'));
$this->assertJson($response->getBody());
```

### Emission Testing

The ResponseEmitter checks `headers_sent()` and skips emission during tests to prevent "headers already sent" errors.

## Performance Considerations

1. **Immutability**: Request objects are immutable, preventing accidental modifications
2. **Lazy Parsing**: JSON is only parsed when the content type is appropriate
3. **Header Caching**: Headers are processed once during request creation
4. **Memory Efficiency**: Large request bodies are handled through streams where possible

## Security Best Practices

1. **Input Validation**: Always validate request data in your controllers
2. **Header Sanitization**: The emitter automatically sanitizes header values
3. **JSON Safety**: JSON parsing includes error checking
4. **IP Detection**: Proper handling of proxy headers for rate limiting and logging
5. **Immutability**: Request data cannot be tampered with after creation

## Integration with Other Components

The HTTP foundation integrates seamlessly with:

- **Router**: Sets route parameters on the request
- **Middleware**: Processes requests and responses in the pipeline
- **Authentication**: JWT claims are attached to the request
- **Validation**: Error responses use the Problem class
- **Rate Limiting**: Uses client IP detection for rate limiting keys
