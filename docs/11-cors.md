# CORS (Cross-Origin Resource Sharing)

The LeanPHP framework provides comprehensive CORS (Cross-Origin Resource Sharing) support to enable secure cross-origin requests from web browsers. The CORS middleware handles preflight requests, validates origins, and adds appropriate headers to responses according to the CORS specification.

## Table of Contents

- [Overview](#overview)
- [How CORS Works](#how-cors-works)
- [Middleware Implementation](#middleware-implementation)
- [Configuration](#configuration)
- [Preflight Request Handling](#preflight-request-handling)
- [Origin Validation](#origin-validation)
- [Headers Management](#headers-management)
- [Security Considerations](#security-considerations)
- [Usage Examples](#usage-examples)
- [Browser Compatibility](#browser-compatibility)
- [Testing](#testing)

## Overview

CORS is a browser security feature that controls how web pages in one domain can access resources from another domain. The LeanPHP CORS middleware provides:

- **Automatic preflight handling**: Responds to OPTIONS requests correctly
- **Origin validation**: Configurable allow/deny lists for request origins
- **Method filtering**: Control which HTTP methods are allowed
- **Header management**: Specify which headers can be sent and received
- **Credentials support**: Optional support for cookies and authorization headers
- **Caching optimization**: Configurable preflight response caching

## How CORS Works

### Simple Requests

Simple requests are sent directly by the browser and include:
- GET, HEAD, or POST methods
- Only "simple" headers (Accept, Accept-Language, Content-Language, Content-Type with specific values)
- Content-Type of application/x-www-form-urlencoded, multipart/form-data, or text/plain

```http
# Browser sends request with Origin header
GET /api/users HTTP/1.1
Host: api.example.com
Origin: https://app.example.com

# Server responds with CORS headers
HTTP/1.1 200 OK
Access-Control-Allow-Origin: https://app.example.com
Vary: Origin
```

### Preflight Requests

Complex requests trigger a preflight OPTIONS request first:
- Non-simple HTTP methods (PUT, DELETE, PATCH, etc.)
- Custom headers (Authorization, X-API-Key, etc.)
- Content-Type of application/json

```http
# Browser sends preflight request
OPTIONS /api/users HTTP/1.1
Host: api.example.com
Origin: https://app.example.com
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Authorization, Content-Type

# Server responds with allowed methods and headers
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://app.example.com
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type
Access-Control-Max-Age: 600
Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers
```

## Middleware Implementation

### Core Middleware Class

The CORS middleware handles both preflight and actual requests:

```php
namespace App\Middleware;

class Cors
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private int $maxAge;
    private bool $allowCredentials;

    public function __construct()
    {
        // Parse configuration from environment variables
        $this->allowedOrigins = $this->parseOrigins($_ENV['CORS_ALLOW_ORIGINS'] ?? '*');
        $this->allowedMethods = $this->parseMethods($_ENV['CORS_ALLOW_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $this->allowedHeaders = $this->parseHeaders($_ENV['CORS_ALLOW_HEADERS'] ?? 'Authorization,Content-Type');
        $this->maxAge = (int) ($_ENV['CORS_MAX_AGE'] ?? 600);
        $this->allowCredentials = filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

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

### Request Flow

1. **Origin Detection**: Extract the Origin header from the request
2. **Request Type**: Determine if it's a preflight (OPTIONS) or actual request
3. **Origin Validation**: Check if the origin is in the allowed list
4. **Header Addition**: Add appropriate CORS headers to the response
5. **Caching**: Set cache headers for preflight responses

## Configuration

### Environment Variables

Configure CORS behavior through environment variables:

```bash
# Origin configuration
CORS_ALLOW_ORIGINS=*                                    # Allow all origins
CORS_ALLOW_ORIGINS=https://app.example.com             # Single origin
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com  # Multiple origins

# Method configuration
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS   # Default methods
CORS_ALLOW_METHODS=GET,POST,OPTIONS                    # Restricted methods

# Header configuration
CORS_ALLOW_HEADERS=Authorization,Content-Type          # Default headers
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-API-Key,X-Client-Version  # Extended headers

# Cache configuration
CORS_MAX_AGE=600                                       # 10 minutes (default)
CORS_MAX_AGE=3600                                      # 1 hour

# Credentials configuration
CORS_ALLOW_CREDENTIALS=false                           # Default (more secure)
CORS_ALLOW_CREDENTIALS=true                            # Allow credentials
```

### Configuration Options

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `CORS_ALLOW_ORIGINS` | string | `*` | Comma-separated list of allowed origins |
| `CORS_ALLOW_METHODS` | string | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | Allowed HTTP methods |
| `CORS_ALLOW_HEADERS` | string | `Authorization,Content-Type` | Allowed request headers |
| `CORS_MAX_AGE` | integer | `600` | Preflight cache duration in seconds |
| `CORS_ALLOW_CREDENTIALS` | boolean | `false` | Allow cookies and authorization headers |

### Configuration Parsing

The middleware parses configuration strings into arrays:

```php
private function parseOrigins(string $origins): array
{
    if ($origins === '*') {
        return ['*'];
    }
    return array_map('trim', explode(',', $origins));
}

private function parseMethods(string $methods): array
{
    return array_map(fn($method) => strtoupper(trim($method)), explode(',', $methods));
}

private function parseHeaders(string $headers): array
{
    return array_map('trim', explode(',', $headers));
}
```

## Preflight Request Handling

### OPTIONS Request Processing

Preflight requests are handled specially by the middleware:

```php
private function handlePreflight(Request $request, ?string $origin): Response
{
    $response = Response::noContent()->status(204);

    // Check if origin is allowed
    if (!$this->isOriginAllowed($origin)) {
        return $response; // No CORS headers for disallowed origins
    }

    $requestMethod = $request->header('access-control-request-method');
    $requestHeaders = $request->header('access-control-request-headers');

    // Check if method is allowed
    if ($requestMethod && !in_array(strtoupper($requestMethod), $this->allowedMethods)) {
        return $response; // Method not allowed
    }

    // Add preflight response headers
    $response = $response
        ->header('Access-Control-Allow-Origin', $this->getAllowOriginValue($origin))
        ->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
        ->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
        ->header('Access-Control-Max-Age', (string) $this->maxAge);

    if ($this->allowCredentials) {
        $response = $response->header('Access-Control-Allow-Credentials', 'true');
    }

    // Add Vary headers for proper caching
    $response = $response->header('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');

    return $response;
}
```

### Preflight Validation

The middleware validates preflight requests by checking:

1. **Origin**: Must be in the allowed origins list
2. **Method**: Requested method must be in allowed methods
3. **Headers**: All requested headers must be allowed
4. **Credentials**: Must be compatible with origin policy

### Preflight Response Headers

| Header | Purpose | Example |
|--------|---------|---------|
| `Access-Control-Allow-Origin` | Allowed origin for the request | `https://app.example.com` |
| `Access-Control-Allow-Methods` | Allowed HTTP methods | `GET, POST, PUT, DELETE, OPTIONS` |
| `Access-Control-Allow-Headers` | Allowed request headers | `Authorization, Content-Type` |
| `Access-Control-Max-Age` | Cache duration for preflight | `600` |
| `Access-Control-Allow-Credentials` | Whether credentials are allowed | `true` |
| `Vary` | Headers that affect response caching | `Origin, Access-Control-Request-Method` |

## Origin Validation

### Allowed Origins Check

The middleware implements flexible origin validation:

```php
private function isOriginAllowed(?string $origin): bool
{
    if (!$origin) {
        return false; // No origin header
    }

    // Allow all origins if configured with *
    if (in_array('*', $this->allowedOrigins)) {
        return true;
    }

    // Check if origin is in the explicit allow list
    return in_array($origin, $this->allowedOrigins);
}
```

### Access-Control-Allow-Origin Value

The middleware carefully sets the `Access-Control-Allow-Origin` header:

```php
private function getAllowOriginValue(?string $origin): string
{
    // If allowing all origins and credentials are disabled, return *
    if (in_array('*', $this->allowedOrigins) && !$this->allowCredentials) {
        return '*';
    }

    // When credentials are enabled, we must return the specific origin (never *)
    return $origin ?? '';
}
```

### Security Rules

1. **Wildcard with Credentials**: Cannot use `*` when credentials are enabled
2. **Explicit Origins**: Must specify exact origins when using credentials
3. **Case Sensitivity**: Origin matching is case-sensitive
4. **Protocol Matching**: https://example.com ≠ http://example.com

## Headers Management

### Standard Response Headers

For actual (non-preflight) requests:

```php
private function addCorsHeaders(Response $response, ?string $origin): Response
{
    // Always add Vary header for proper caching
    if (!$this->isOriginAllowed($origin)) {
        return $response->header('Vary', 'Origin');
    }

    $response = $response
        ->header('Access-Control-Allow-Origin', $this->getAllowOriginValue($origin))
        ->header('Vary', 'Origin');

    if ($this->allowCredentials) {
        $response = $response->header('Access-Control-Allow-Credentials', 'true');
    }

    return $response;
}
```

### Vary Header Importance

The `Vary` header is critical for proper caching:

```php
// For preflight requests
'Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers'

// For actual requests
'Vary: Origin'
```

This ensures that:
- CDNs cache responses per origin
- Browsers don't use cached preflight responses for different origins
- Proxy servers handle CORS correctly

## Security Considerations

### Origin Validation Security

**Secure Configuration**:
```bash
# Production - specific origins only
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_CREDENTIALS=true
```

**Insecure Configuration**:
```bash
# Avoid in production - allows any origin with credentials
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_CREDENTIALS=true  # This combination is invalid and ignored
```

### Credentials and Origins

When `CORS_ALLOW_CREDENTIALS=true`:
- Cannot use wildcard (`*`) for origins
- Must specify exact allowed origins
- Browser sends cookies and authorization headers
- Higher security risk if misconfigured

### Common Security Pitfalls

1. **Overly Permissive Origins**: Using `*` in production
2. **Subdomain Wildcards**: CORS doesn't support `*.example.com`
3. **HTTP vs HTTPS**: Different protocols are different origins
4. **Port Numbers**: `example.com:3000` ≠ `example.com:8080`

## Usage Examples

### Development Setup

Allow all origins for local development:

```bash
# .env file for development
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_MAX_AGE=600
CORS_ALLOW_CREDENTIALS=false
```

### Production Setup

Restrict to specific origins in production:

```bash
# .env file for production
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type
CORS_MAX_AGE=3600
CORS_ALLOW_CREDENTIALS=true
```

### API-Only Setup

For APIs without browser authentication:

```bash
# Public API configuration
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_METHODS=GET,POST,OPTIONS
CORS_ALLOW_HEADERS=Content-Type,Accept
CORS_MAX_AGE=86400
CORS_ALLOW_CREDENTIALS=false
```

### Multiple Environment Setup

Use different configurations per environment:

```bash
# Development
CORS_ALLOW_ORIGINS=http://localhost:3000,http://localhost:8080

# Staging
CORS_ALLOW_ORIGINS=https://staging-app.example.com

# Production
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com
```

## Browser Compatibility

### Supported Browsers

CORS is supported in all modern browsers:
- Chrome 4+
- Firefox 3.5+
- Safari 4+
- Internet Explorer 10+
- Edge (all versions)

### Legacy Browser Handling

For older browsers that don't support CORS:
- Use JSONP for GET requests
- Use server-side proxy for other methods
- Consider iframe-based solutions

### Browser Behavior Differences

**Chrome/Firefox**:
- Full CORS specification support
- Proper preflight caching
- Strict origin validation

**Safari**:
- Generally good CORS support
- Some edge cases with credentials
- Proper preflight handling

**Internet Explorer**:
- IE10+ has full CORS support
- IE8-9 use XDomainRequest (limited)
- No support for credentials in older versions

## Testing

### Unit Tests

Test CORS functionality with comprehensive unit tests:

```php
public function test_preflight_request_with_allowed_origin(): void
{
    $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com';
    $_ENV['CORS_ALLOW_METHODS'] = 'GET,POST,PUT,DELETE';

    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';

    $request = Request::fromGlobals();
    $cors = new Cors();

    $response = $cors->handle($request, function () {
        return Response::json(['should' => 'not reach here']);
    });

    $this->assertEquals(204, $response->getStatusCode());
    $this->assertEquals('https://example.com', $response->getHeader('access-control-allow-origin'));
    $this->assertEquals('GET, POST, PUT, DELETE', $response->getHeader('access-control-allow-methods'));
}

public function test_actual_request_with_cors_headers(): void
{
    $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com';

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

    $request = Request::fromGlobals();
    $cors = new Cors();

    $response = $cors->handle($request, function () {
        return Response::json(['data' => 'test']);
    });

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('https://example.com', $response->getHeader('access-control-allow-origin'));
    $this->assertEquals('Origin', $response->getHeader('vary'));
}
```

### Integration Tests

Test CORS in complete request flows:

```php
public function test_cors_with_authentication(): void
{
    // Configure CORS with credentials
    $_ENV['CORS_ALLOW_ORIGINS'] = 'https://app.example.com';
    $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

    // Test preflight for authenticated request
    $response = $this->makePreflightRequest('POST', '/api/users', [
        'origin' => 'https://app.example.com',
        'headers' => 'Authorization,Content-Type'
    ]);

    $this->assertEquals(204, $response->getStatusCode());
    $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
}
```

### Common Test Scenarios

1. **Allowed origins**: Requests from configured origins succeed
2. **Disallowed origins**: Requests from unconfigured origins fail
3. **Wildcard origins**: `*` allows all origins when credentials disabled
4. **Method validation**: Only allowed methods pass preflight
5. **Header validation**: Only allowed headers pass preflight
6. **Credentials handling**: Proper credential support when enabled
7. **Vary headers**: Correct caching headers set
8. **No origin**: Requests without Origin header handled correctly
