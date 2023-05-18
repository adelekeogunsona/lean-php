# Security

The LeanPHP framework implements multiple layers of security to protect your application from common threats and vulnerabilities. This comprehensive security system includes authentication, authorization, CORS protection, rate limiting, and various security middleware components.

## Table of Contents

- [Overview](#overview)
- [Authentication System](#authentication-system)
- [Authorization and Scopes](#authorization-and-scopes)
- [JWT Token Security](#jwt-token-security)
- [CORS Security](#cors-security)
- [Rate Limiting Protection](#rate-limiting-protection)
- [Request Security](#request-security)
- [Response Security](#response-security)
- [Security Headers](#security-headers)
- [Input Validation Security](#input-validation-security)
- [Error Handling Security](#error-handling-security)
- [Security Best Practices](#security-best-practices)
- [Security Testing](#security-testing)

## Overview

LeanPHP's security architecture follows defense-in-depth principles with multiple security layers:

1. **Authentication Layer** - JWT-based Bearer token authentication
2. **Authorization Layer** - Scope-based access control
3. **Network Security** - CORS protection and origin validation
4. **Rate Limiting** - Protection against abuse and DoS attacks
5. **Input Security** - Request validation and sanitization
6. **Output Security** - Safe error responses and information disclosure prevention

### Security Components

- **AuthBearer Middleware** (`app/Middleware/AuthBearer.php`) - JWT token verification and authentication
- **RequireScopes Middleware** (`app/Middleware/RequireScopes.php`) - Authorization and scope validation
- **CORS Middleware** (`app/Middleware/Cors.php`) - Cross-origin request protection
- **RateLimiter Middleware** (`app/Middleware/RateLimiter.php`) - Rate limiting and abuse prevention
- **Token Class** (`src/Auth/Token.php`) - JWT token generation and verification
- **Validation System** - Input validation and sanitization

## Authentication System

### JWT Bearer Token Authentication

The framework uses JWT (JSON Web Tokens) with Bearer authentication for stateless, secure authentication:

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
            $claims = Token::verify($bearerToken);
            $request->setClaims($claims);
            return $next($request);
        } catch (InvalidArgumentException $e) {
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        }
    }
}
```

### Authentication Flow

1. **Client Request**: Includes `Authorization: Bearer <token>` header
2. **Token Extraction**: Middleware extracts token from header
3. **Token Verification**: Validates signature, expiration, and format
4. **Claims Attachment**: Verified claims are attached to the request
5. **Request Processing**: Continues to next middleware/controller

### Bearer Token Format

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

### Authentication Security Features

- **Stateless Authentication**: No server-side session storage required
- **Token Expiration**: Configurable token lifetime (`exp` claim)
- **Signature Verification**: RSA256 signature validation
- **Cryptographic Security**: Uses strong RSA keys for signing
- **WWW-Authenticate Header**: Proper authentication challenge response

### Authentication Error Responses

```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Missing or invalid Authorization header",
    "instance": "/api/protected-resource"
}
```

The response includes the WWW-Authenticate header for proper HTTP authentication:
```http
WWW-Authenticate: Bearer realm="API"
```

## Authorization and Scopes

### Scope-Based Authorization

The framework implements fine-grained authorization using JWT scopes:

```php
namespace App\Middleware;

class RequireScopes
{
    private array $requiredScopes;

    public function __construct(array $requiredScopes)
    {
        $this->requiredScopes = $requiredScopes;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$request->isAuthenticated()) {
            return Problem::unauthorized('Authentication required');
        }

        $userScopes = $this->getUserScopes($request);

        foreach ($this->requiredScopes as $requiredScope) {
            if (!in_array($requiredScope, $userScopes, true)) {
                return Problem::forbidden("Missing required scope: {$requiredScope}");
            }
        }

        return $next($request);
    }
}
```

### Scope Implementation

Scopes are stored in JWT tokens and enforced by middleware:

```json
{
    "sub": "user123",
    "scopes": "read:users,write:users,admin:system",
    "exp": 1234567890,
    "iat": 1234567800
}
```

### Authorization Patterns

```php
// Route-level authorization
$router->get('/admin/users', [AdminController::class, 'listUsers'])
    ->middleware([
        new AuthBearer(),
        new RequireScopes(['admin:users'])
    ]);

// Multiple scope requirements (all required)
$router->post('/admin/system/restart', [SystemController::class, 'restart'])
    ->middleware([
        new AuthBearer(),
        new RequireScopes(['admin:system', 'write:system'])
    ]);

// Controller-level scope checking
public function createUser(Request $request): Response
{
    // Additional authorization logic
    if (!$request->hasScope('write:users')) {
        return Problem::forbidden('Insufficient permissions');
    }

    // Continue with user creation...
}
```

### Common Scope Patterns

- **Resource-based**: `read:users`, `write:users`, `delete:users`
- **Action-based**: `create`, `update`, `delete`, `admin`
- **Hierarchical**: `admin:system`, `admin:users`, `user:profile`
- **Granular**: `read:user:profile`, `write:user:password`

## JWT Token Security

### Token Structure and Claims

LeanPHP uses RS256 (RSA with SHA-256) for JWT signing:

```json
{
    "typ": "JWT",
    "alg": "RS256",
    "kid": "key-id"
}
{
    "sub": "user123",
    "scopes": "read:users,write:users",
    "exp": 1234567890,
    "iat": 1234567800,
    "iss": "your-api.com",
    "aud": "your-client-app"
}
```

### Security Features

**Cryptographic Security:**
- **RS256 Algorithm**: Asymmetric signing for enhanced security
- **Key Rotation**: Support for multiple keys via `kid` (Key ID)
- **Strong Keys**: RSA keys with minimum 2048-bit length

**Token Validation:**
- **Signature Verification**: Ensures token hasn't been tampered with
- **Expiration Check**: Prevents use of expired tokens
- **Format Validation**: Validates JWT structure and required claims
- **Issuer Validation**: Verifies token comes from trusted source

### Token Configuration

```php
// JWT key configuration
$jwtKeys = [
    'current' => [
        'kid' => env_string('JWT_KID'),
        'private_key' => env_string('JWT_PRIVATE_KEY'),
        'public_key' => env_string('JWT_PUBLIC_KEY'),
    ]
];

// Token generation
$token = Token::generate([
    'sub' => $userId,
    'scopes' => implode(',', $userScopes),
    'exp' => time() + (60 * 60), // 1 hour expiration
    'iat' => time(),
]);
```

### Token Security Best Practices

1. **Short Expiration Times**: Typically 15 minutes to 1 hour
2. **Secure Storage**: Store keys outside web root
3. **Key Rotation**: Regular key rotation for enhanced security
4. **HTTPS Only**: Always transmit tokens over HTTPS
5. **Secure Headers**: Include tokens only in Authorization header

## CORS Security

### Cross-Origin Resource Sharing Protection

The CORS middleware provides security against unauthorized cross-origin requests:

```php
namespace App\Middleware;

class Cors
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private bool $allowCredentials;

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }

        // Process actual request
        $response = $next($request);

        return $this->addCorsHeaders($response, $origin);
    }
}
```

### CORS Security Features

**Origin Validation:**
- **Explicit Allow Lists**: Only specified origins are permitted
- **No Wildcard with Credentials**: Prevents `*` when credentials are enabled
- **Case-Sensitive Matching**: Exact origin matching for security
- **Protocol Enforcement**: Distinguishes between HTTP and HTTPS

**Method Control:**
- **Limited Methods**: Only specified HTTP methods are allowed
- **Preflight Validation**: OPTIONS requests validate allowed methods
- **Automatic HEAD/OPTIONS**: Safe methods are automatically supported

**Header Security:**
- **Controlled Headers**: Only whitelisted headers are permitted
- **Authorization Protection**: Controls access to authentication headers
- **Custom Header Validation**: Validates custom application headers

### CORS Configuration Security

```bash
# Secure production configuration
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-API-Key
CORS_MAX_AGE=600
CORS_ALLOW_CREDENTIALS=true

# Insecure configuration (avoid in production)
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_CREDENTIALS=true  # Invalid combination
```

### CORS Security Validation

```php
private function isOriginAllowed(string $origin): bool
{
    // Wildcard check
    if (in_array('*', $this->allowedOrigins, true)) {
        // Cannot use wildcard with credentials
        return !$this->allowCredentials;
    }

    // Exact origin matching
    return in_array($origin, $this->allowedOrigins, true);
}

private function handlePreflight(Request $request): Response
{
    $origin = $request->header('origin');
    $method = $request->header('access-control-request-method');
    $headers = $request->header('access-control-request-headers');

    // Validate origin, method, and headers
    if (!$this->isOriginAllowed($origin) ||
        !$this->isMethodAllowed($method) ||
        !$this->areHeadersAllowed($headers)) {
        return Response::json(['error' => 'CORS policy violation'], 403);
    }

    return $this->buildPreflightResponse($origin);
}
```

### CORS Security Considerations

**Credential Security:**
- When `allowCredentials=true`, cookies and authorization headers are sent
- Requires explicit origin specification (no wildcards)
- Increases security risk if misconfigured

**Origin Validation:**
- Must match exactly (case-sensitive)
- Subdomain differences matter: `app.example.com` ≠ `api.example.com`
- Protocol differences matter: `https://` ≠ `http://`

## Rate Limiting Protection

### Anti-Abuse and DoS Protection

The rate limiting system protects against abuse and denial-of-service attacks:

```php
namespace App\Middleware;

class RateLimiter
{
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        if (!$this->store->allow($key, $limit, $window)) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        $result = $this->store->hit($key, $limit, $window);
        $response = $next($request);

        return $this->addRateLimitHeaders($response, $limit, $result['remaining'], $result['resetAt']);
    }
}
```

### Rate Limiting Security Features

**Key-Based Limiting:**
- **User-Based**: Authenticated users get individual limits
- **IP-Based**: Anonymous users limited by IP address
- **Isolation**: Different users don't affect each other's limits

**Algorithm Security:**
- **Sliding Window**: More accurate than fixed windows
- **Request Tracking**: Tracks individual request timestamps
- **Automatic Cleanup**: Expired requests are automatically removed

**Anti-Thundering Herd:**
- **Jitter Addition**: Random 1-5 second delay in retry-after headers
- **Prevents Synchronization**: Stops clients from retrying simultaneously

### Rate Limiting Key Generation

```php
private function getRateLimitKey(Request $request): string
{
    // Prefer authenticated user ID
    if ($request->isAuthenticated()) {
        $userId = $request->claim('sub');
        if ($userId) {
            return "user:{$userId}";
        }
    }

    // Fall back to IP address
    $ip = $request->getClientIp();
    return "ip:{$ip}";
}
```

### Security Benefits

1. **DoS Protection**: Prevents denial-of-service attacks
2. **Brute Force Prevention**: Limits authentication attempts
3. **Resource Protection**: Prevents API abuse
4. **Fair Usage**: Ensures equitable resource distribution

### Rate Limit Headers

Security-relevant headers are included in responses:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1234567890
Retry-After: 23
```

## Request Security

### Input Validation and Sanitization

**JSON Body Validation:**
```php
// JsonBodyParser middleware validates JSON format
if (!str_contains($contentType, 'application/json')) {
    return Problem::make(
        415,
        'Unsupported Media Type',
        'Content-Type must be application/json',
        '/problems/unsupported-media-type'
    );
}

// Validate JSON syntax
json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    return Problem::make(
        400,
        'Bad Request',
        'Invalid JSON format: ' . json_last_error_msg(),
        '/problems/invalid-json'
    );
}
```

**Header Validation:**
- Content-Type verification for POST/PUT requests
- Authorization header format validation
- Custom header whitelisting through CORS

**Parameter Validation:**
- URL parameter sanitization
- Query parameter validation
- Route parameter type checking

### Request Size Limits

```php
// Content-Length validation
$contentLength = (int) $request->header('content-length', '0');
$maxSize = 1024 * 1024; // 1MB limit

if ($contentLength > $maxSize) {
    return Problem::make(413, 'Payload Too Large', 'Request body too large');
}
```

## Response Security

### Information Disclosure Prevention

**Debug Mode Controls:**
```php
// Production mode (APP_DEBUG=false)
$detail = $debug ? $e->getMessage() : 'An unexpected error occurred';

if ($debug) {
    // Only in development
    $body['debug'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 10),
    ];
}
```

**Safe Error Responses:**
- Generic error messages in production
- No stack traces or file paths exposed
- Consistent response format prevents information leakage
- No database schema or internal structure disclosure

### Response Header Security

```php
// Security headers (example implementation)
$response->header('X-Content-Type-Options', 'nosniff');
$response->header('X-Frame-Options', 'DENY');
$response->header('X-XSS-Protection', '1; mode=block');
```

## Security Headers

### Recommended Security Headers

While not implemented by default, consider adding these headers:

```php
// Content Security Policy
$response->header('Content-Security-Policy', "default-src 'self'");

// Strict Transport Security (HTTPS only)
$response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

// Prevent MIME type sniffing
$response->header('X-Content-Type-Options', 'nosniff');

// Clickjacking protection
$response->header('X-Frame-Options', 'DENY');

// XSS protection
$response->header('X-XSS-Protection', '1; mode=block');

// Referrer policy
$response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
```

### API-Specific Headers

```php
// Cache control for sensitive data
$response->header('Cache-Control', 'no-store, no-cache, must-revalidate');

// Prevent caching of authentication responses
$response->header('Pragma', 'no-cache');
```

## Input Validation Security

### Validation Rules Security

The validation system provides security through input sanitization:

```php
// Secure validation patterns
$validator = Validator::make($data, [
    'email' => 'required|email|max:255',           // Email format validation
    'password' => 'required|string|min:8|max:128', // Length constraints
    'name' => 'required|string|min:2|max:100',     // Prevent empty/overly long names
    'role' => 'required|in:user,admin',            // Whitelist values
    'age' => 'integer|min:0|max:150',              // Numeric constraints
]);
```

### SQL Injection Prevention

The database layer uses prepared statements:

```php
// Safe parameterized queries
$users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
$affected = DB::execute('INSERT INTO users (name, email) VALUES (?, ?)', [$name, $email]);

// Named parameters also supported
$user = DB::select('SELECT * FROM users WHERE id = :id AND status = :status', [
    'id' => $userId,
    'status' => 'active'
]);
```

### XSS Prevention

JSON responses are inherently safer than HTML:

```php
// Safe JSON encoding
$response = Response::json([
    'message' => $userInput // Automatically escaped in JSON
]);

// Additional encoding flags for security
$encoded = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
```

## Error Handling Security

### Information Disclosure Prevention

**Production Environment:**
- Generic error messages prevent information leakage
- No stack traces to avoid exposing application structure
- No file paths to prevent directory traversal information
- Consistent error format prevents fingerprinting

**Debug Environment:**
- Detailed error information for development
- Should never be enabled in production
- Controlled through `APP_DEBUG` environment variable

### Secure Error Logging

```php
// Secure error logging (recommended implementation)
private function logError(Throwable $e, Request $request): void
{
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => $request->method(),
        'path' => $request->path(),
        'ip' => $request->getClientIp(),
        'user_agent' => $request->header('user-agent'),
    ];

    // Remove sensitive information
    unset($logData['password'], $logData['token']);

    error_log(json_encode($logData));
}
```

## Security Best Practices

### Authentication Security

1. **Use HTTPS Only**: Always transmit tokens over encrypted connections
2. **Short Token Lifetimes**: 15 minutes to 1 hour maximum
3. **Secure Key Storage**: Store JWT keys outside web root
4. **Key Rotation**: Regular rotation of signing keys
5. **Strong Algorithms**: Use RS256, avoid HS256 for production

### Authorization Security

1. **Principle of Least Privilege**: Grant minimum required permissions
2. **Scope Granularity**: Use fine-grained scopes for better control
3. **Regular Scope Audits**: Review and update permission scopes
4. **Scope Validation**: Always validate scopes on protected endpoints

### Rate Limiting Security

1. **Multiple Rate Limits**: Different limits for different endpoints
2. **User vs IP Limiting**: Separate limits for authenticated/anonymous users
3. **Graceful Degradation**: Handle rate limit storage failures gracefully
4. **Monitor Abuse**: Log and alert on rate limit violations

### General Security

1. **Input Validation**: Validate all input data
2. **Output Encoding**: Properly encode all output
3. **Error Handling**: Never expose internal details in production
4. **Security Headers**: Implement appropriate security headers
5. **Regular Updates**: Keep dependencies updated
6. **Security Audits**: Regular security reviews and testing

## Security Testing

### Authentication Testing

```php
public function test_missing_authorization_header_returns_401(): void
{
    $response = $this->makeRequest('GET', '/protected-endpoint');

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertEquals('Bearer realm="API"', $response->getHeader('www-authenticate'));
}

public function test_invalid_token_returns_401(): void
{
    $response = $this->makeRequest('GET', '/protected-endpoint', [], [
        'Authorization' => 'Bearer invalid-token'
    ]);

    $this->assertEquals(401, $response->getStatusCode());
}

public function test_expired_token_returns_401(): void
{
    $expiredToken = $this->generateExpiredToken();

    $response = $this->makeRequest('GET', '/protected-endpoint', [], [
        'Authorization' => "Bearer {$expiredToken}"
    ]);

    $this->assertEquals(401, $response->getStatusCode());
}
```

### Authorization Testing

```php
public function test_insufficient_scopes_returns_403(): void
{
    $token = $this->generateTokenWithScopes(['read:users']);

    $response = $this->makeRequest('POST', '/admin/users', [], [
        'Authorization' => "Bearer {$token}"
    ]);

    $this->assertEquals(403, $response->getStatusCode());
}

public function test_sufficient_scopes_allows_access(): void
{
    $token = $this->generateTokenWithScopes(['admin:users']);

    $response = $this->makeRequest('POST', '/admin/users', ['name' => 'Test'], [
        'Authorization' => "Bearer {$token}"
    ]);

    $this->assertEquals(201, $response->getStatusCode());
}
```

### CORS Security Testing

```php
public function test_cors_blocks_unauthorized_origin(): void
{
    $response = $this->makeRequest('OPTIONS', '/api/users', [], [
        'Origin' => 'https://malicious.com',
        'Access-Control-Request-Method' => 'GET'
    ]);

    $this->assertEquals(403, $response->getStatusCode());
}

public function test_cors_allows_authorized_origin(): void
{
    $response = $this->makeRequest('OPTIONS', '/api/users', [], [
        'Origin' => 'https://app.example.com',
        'Access-Control-Request-Method' => 'GET'
    ]);

    $this->assertEquals(204, $response->getStatusCode());
    $this->assertEquals('https://app.example.com', $response->getHeader('access-control-allow-origin'));
}
```

### Rate Limiting Testing

```php
public function test_rate_limiting_blocks_excessive_requests(): void
{
    $token = $this->generateValidToken();

    // Make requests up to the limit
    for ($i = 0; $i < 60; $i++) {
        $response = $this->makeRequest('GET', '/api/users', [], [
            'Authorization' => "Bearer {$token}"
        ]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // Next request should be rate limited
    $response = $this->makeRequest('GET', '/api/users', [], [
        'Authorization' => "Bearer {$token}"
    ]);

    $this->assertEquals(429, $response->getStatusCode());
    $this->assertNotNull($response->getHeader('retry-after'));
}
```

### Security Test Scenarios

1. **Authentication bypass attempts**
2. **Token manipulation and tampering**
3. **Scope escalation attempts**
4. **CORS policy violations**
5. **Rate limit circumvention**
6. **Input validation bypasses**
7. **Error message information disclosure**
8. **Timing attack resilience**
