# Rate Limiting

The LeanPHP framework provides robust rate limiting capabilities to protect your API from abuse, implement quality of service controls, and ensure fair resource usage among clients. The rate limiting system uses a sliding window algorithm with multiple storage backends for flexibility and performance.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Storage Backends](#storage-backends)
- [Middleware Implementation](#middleware-implementation)
- [Configuration](#configuration)
- [Usage Examples](#usage-examples)
- [Headers and Responses](#headers-and-responses)
- [Security Features](#security-features)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)

## Overview

The rate limiting system consists of several components working together:

- **Store Interface**: Defines the contract for rate limiting storage backends
- **FileStore**: File-based storage implementation with file locking for concurrency
- **ApcuStore**: In-memory storage using APCu extension for high performance
- **RateLimiter Middleware**: HTTP middleware that applies rate limiting to requests
- **Automatic Key Generation**: Per-user or per-IP rate limiting based on authentication

## Core Concepts

### Sliding Window Algorithm

The framework uses a sliding window algorithm that:
- Tracks individual request timestamps within a time window
- Automatically expires old requests outside the window
- Provides accurate rate limiting without discrete time buckets
- Prevents burst requests from consuming the entire quota

### Rate Limiting Keys

The system generates rate limiting keys based on request context:

```php
// For authenticated users
"user:{user_id}"    // Based on JWT 'sub' claim

// For anonymous users
"ip:{ip_address}"   // Based on client IP address
```

This ensures that:
- Authenticated users have independent rate limits regardless of IP
- Different users on the same IP don't interfere with each other
- Anonymous users are limited by IP to prevent abuse

### Time Windows

Rate limits are applied over configurable time windows:
- **Default**: 60 seconds (1 minute)
- **Configurable**: Any duration in seconds
- **Sliding**: Window moves with each request, not in discrete chunks

## Storage Backends

### Store Interface

All storage backends implement the `Store` interface:

```php
namespace LeanPHP\RateLimit;

interface Store
{
    /**
     * Record a hit and return remaining count and reset time
     */
    public function hit(string $key, int $limit, int $window): array;

    /**
     * Check if a request is allowed without incrementing counter
     */
    public function allow(string $key, int $limit, int $window): bool;

    /**
     * Get seconds to wait before retrying
     */
    public function retryAfter(string $key, int $limit, int $window): int;
}
```

### FileStore Implementation

The `FileStore` provides persistent, file-based rate limiting:

```php
class FileStore implements Store
{
    private string $storageDir;

    public function __construct(string $storageDir = 'storage/ratelimit')
    {
        $this->storageDir = $storageDir;

        // Automatically create storage directory
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
}
```

**Key Features**:
- **File Locking**: Uses `flock()` for atomic read-modify-write operations
- **Automatic Cleanup**: Removes expired timestamps during operations
- **Concurrency Safe**: Multiple processes can safely access the same limits
- **Persistent**: Rate limits survive application restarts
- **No Dependencies**: Works on any PHP installation

**File Format**:
```json
{
    "hits": [1234567890, 1234567920, 1234567950],
    "updated": 1234567950
}
```

**Concurrency Handling**:
```php
// Exclusive lock for writes
if (!flock($fp, LOCK_EX)) {
    throw new RuntimeException("Failed to acquire lock");
}

// Shared lock for reads
if (!flock($fp, LOCK_SH)) {
    return true; // Assume allowed if can't lock
}
```

### ApcuStore Implementation

The `ApcuStore` provides high-performance, in-memory rate limiting:

```php
class ApcuStore implements Store
{
    public function hit(string $key, int $limit, int $window): array
    {
        if (!extension_loaded('apcu')) {
            throw new RuntimeException('APCu extension is not loaded');
        }

        // Implementation uses sliding window with APCu keys
    }
}
```

**Key Features**:
- **High Performance**: In-memory storage with microsecond access times
- **Automatic Expiry**: Uses APCu TTL for automatic cleanup
- **Shared Memory**: Data survives individual request cycles
- **Extension Required**: Requires APCu PHP extension
- **Lost on Restart**: Rate limits reset when server restarts

**Storage Strategy**:
```php
// Generate window-specific keys
$windowKey = $this->getWindowKey($key, $now, $window);
$countKey = "count:{$windowKey}";
$timestampKey = "ts:{$windowKey}";

// Store with TTL for automatic cleanup
apcu_store($countKey, $count, $window);
apcu_store($timestampKey, $timestamp, $window);
```

### Automatic Fallback

The system automatically falls back to FileStore if APCu is not available:

```php
private function createStore(): Store
{
    $storeType = env_string('RATE_LIMIT_STORE', 'apcu');

    return match ($storeType) {
        'apcu' => extension_loaded('apcu') ? new ApcuStore() : new FileStore(),
        'file' => new FileStore(),
        default => throw new InvalidArgumentException("Unsupported store: {$storeType}")
    };
}
```

## Middleware Implementation

### RateLimiter Middleware

The `RateLimiter` middleware integrates rate limiting into the HTTP request pipeline:

```php
namespace App\Middleware;

class RateLimiter
{
    private Store $store;
    private int $defaultLimit;
    private int $defaultWindow;

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        // Check if request is allowed before processing
        if (!$this->store->allow($key, $limit, $window)) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        // Record the hit and get current state
        $result = $this->store->hit($key, $limit, $window);

        // Continue to next middleware/controller
        $response = $next($request);

        // Add rate limit headers to the response
        return $this->addRateLimitHeaders($response, $limit, $result['remaining'], $result['resetAt']);
    }
}
```

### Key Generation Logic

The middleware intelligently determines the rate limiting key:

```php
private function getRateLimitKey(Request $request): string
{
    // Prefer authenticated user ID from JWT
    if ($request->isAuthenticated()) {
        $userId = $request->claim('sub');
        if ($userId) {
            return "user:{$userId}";
        }
    }

    // Fall back to client IP address
    $ip = $request->getClientIp();
    return "ip:{$ip}";
}
```

This approach ensures:
- **User-based limiting**: Authenticated users get individual quotas
- **IP-based limiting**: Anonymous users are limited by source IP
- **Isolation**: Different users from same IP don't interfere
- **Fallback**: System works even without authentication

### Error Response Generation

When rate limits are exceeded, the middleware returns a structured error:

```php
private function rateLimitExceeded(string $key, int $limit, int $window): Response
{
    $retryAfter = $this->store->retryAfter($key, $limit, $window);

    // Add jitter to prevent thundering herd
    if ($retryAfter > 0) {
        $jitter = random_int(1, 5);
        $retryAfter += $jitter;
    }

    $response = Problem::tooManyRequests('Rate limit exceeded', $retryAfter);

    return $this->addRateLimitHeaders($response, $limit, 0, time() + $retryAfter);
}
```

## Configuration

### Environment Variables

Configure rate limiting behavior through environment variables:

```bash
# Storage backend selection
RATE_LIMIT_STORE=apcu          # 'apcu' or 'file'

# Default limits (applied to all endpoints)
RATE_LIMIT_DEFAULT=60          # Requests per window
RATE_LIMIT_WINDOW=60           # Window size in seconds
```

### Configuration Options

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `RATE_LIMIT_STORE` | string | `apcu` | Storage backend (`apcu` or `file`) |
| `RATE_LIMIT_DEFAULT` | integer | `60` | Default requests per window |
| `RATE_LIMIT_WINDOW` | integer | `60` | Time window in seconds |

### Storage Backend Selection

**APCu (Recommended for Production)**:
```bash
RATE_LIMIT_STORE=apcu
```
- **Pros**: Very fast, automatic cleanup, shared between processes
- **Cons**: Requires APCu extension, data lost on restart
- **Use Case**: High-traffic production environments

**File Storage (Default)**:
```bash
RATE_LIMIT_STORE=file
```
- **Pros**: No dependencies, persistent across restarts, works everywhere
- **Cons**: Slower due to file I/O, requires disk space
- **Use Case**: Development, low-traffic sites, when APCu unavailable

## Usage Examples

### Basic Setup

Add the middleware to your global middleware stack:

```php
// In your bootstrap or routing setup
$globalMiddleware = [
    new App\Middleware\RateLimiter(),
    // ... other middleware
];
```

### Custom Rate Limits

You can create custom rate limiters with different settings:

```php
// Strict rate limiter for auth endpoints
$authLimiter = new RateLimiter(
    store: new FileStore('storage/auth_limits'),
    defaultLimit: 5,    // Only 5 attempts
    defaultWindow: 300  // Per 5 minutes
);

// Lenient rate limiter for public API
$publicLimiter = new RateLimiter(
    store: new ApcuStore(),
    defaultLimit: 1000,  // 1000 requests
    defaultWindow: 3600  // Per hour
);
```

### Integration with Router

Apply rate limiting to specific routes:

```php
// Global rate limiting
$router->middleware([
    new App\Middleware\RateLimiter()
]);

// Route-specific rate limiting
$router->group(['middleware' => [new StrictRateLimiter()]], function ($router) {
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/register', [AuthController::class, 'register']);
});
```

## Headers and Responses

### Standard Rate Limit Headers

The middleware adds standard HTTP headers to all responses:

```http
X-RateLimit-Limit: 60         # Total requests allowed in window
X-RateLimit-Remaining: 45     # Requests remaining in current window
X-RateLimit-Reset: 1234567890 # Unix timestamp when window resets
```

### Success Response Example

```http
HTTP/1.1 200 OK
Content-Type: application/json
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1234567950

{
    "data": { ... }
}
```

### Rate Limit Exceeded Response

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1234567950
Retry-After: 23

{
    "type": "/problems/too-many-requests",
    "title": "Too Many Requests",
    "status": 429,
    "detail": "Rate limit exceeded",
    "instance": "/api/endpoint"
}
```

### Headers Explained

- **X-RateLimit-Limit**: The maximum number of requests allowed in the time window
- **X-RateLimit-Remaining**: How many requests the client can make before hitting the limit
- **X-RateLimit-Reset**: Unix timestamp when the current window resets (oldest request expires)
- **Retry-After**: Number of seconds to wait before making another request (only on 429)

## Security Features

### Anti-Thundering Herd

The system adds random jitter to retry-after times to prevent all clients from retrying simultaneously:

```php
if ($retryAfter > 0) {
    $jitter = random_int(1, 5);
    $retryAfter += $jitter;
}
```

### Sliding Window Benefits

The sliding window algorithm prevents:
- **Burst attacks**: Can't consume entire quota in the first second
- **Window boundary exploitation**: No discrete time buckets to game
- **Unfair distribution**: Rate limits are smoothly applied over time

### Separate Limits by Authentication

Different rate limiting keys ensure:
- Authenticated users don't affect each other
- Anonymous users are properly limited by IP
- Compromised credentials don't affect other users
- Fair resource allocation

## Performance Considerations

### Storage Backend Performance

**APCu Performance**:
- **Read/Write**: Microsecond latency
- **Memory Usage**: Minimal, automatic cleanup
- **Concurrency**: High, shared memory model
- **Scalability**: Limited by server memory

**File Storage Performance**:
- **Read/Write**: Millisecond latency (depends on disk)
- **File Locking**: Serializes concurrent access
- **I/O Impact**: Increases with request volume
- **Scalability**: Limited by disk I/O and locking contention

### Optimization Tips

1. **Use APCu in production** for better performance
2. **Monitor storage directory** size when using FileStore
3. **Consider cleanup scripts** for old rate limit files
4. **Tune window sizes** based on your use case
5. **Use larger windows** for less frequent cleanup

### Memory Usage

**APCu Store**:
```php
// Approximately 100-200 bytes per active window
// Example: 1000 concurrent users = ~200KB memory
```

**File Store**:
```php
// Approximately 50-100 bytes per file on disk
// Plus file system overhead (inodes, directory entries)
```

## Testing

### Unit Tests

The framework includes comprehensive tests for all rate limiting components:

```php
public function testFileStoreBasicFunctionality(): void
{
    $store = new FileStore($this->tempDir);
    $key = 'test-key';
    $limit = 5;
    $window = 60;

    // First hit should work
    $result = $store->hit($key, $limit, $window);
    $this->assertEquals(4, $result['remaining']);
    $this->assertIsInt($result['resetAt']);
}

public function testRateLimitExceeded(): void
{
    // Test hitting the rate limit
    for ($i = 0; $i < 3; $i++) {
        $response = $middleware->handle($request, $next);
        if ($i < 2) {
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    // Should be rate limited now
    $response = $middleware->handle($request, $next);
    $this->assertEquals(429, $response->getStatusCode());
}
```

### Integration Tests

Test rate limiting in complete request flows:

```php
public function test_rate_limiting_flow(): void
{
    // Set up test environment
    $_ENV['RATE_LIMIT_DEFAULT'] = '3';
    $_ENV['RATE_LIMIT_WINDOW'] = '60';

    // Make requests up to limit
    for ($i = 0; $i < 3; $i++) {
        $response = $this->makeRequest('GET', '/api/users');
        $this->assertEquals(200, $response->getStatusCode());
    }

    // Next request should be rate limited
    $response = $this->makeRequest('GET', '/api/users');
    $this->assertEquals(429, $response->getStatusCode());
}
```

### Testing Different Scenarios

1. **Basic functionality**: Requests under limit pass
2. **Rate limit enforcement**: Requests over limit fail
3. **Window expiry**: Old requests expire correctly
4. **Concurrent access**: Multiple processes handle locking correctly
5. **Authentication context**: User vs IP-based limiting
6. **Header validation**: Correct headers on all responses
7. **Error responses**: Proper 429 responses with retry-after

The rate limiting system provides a robust, scalable solution for protecting your API while maintaining excellent performance and user experience.