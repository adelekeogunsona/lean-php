# ETag Caching

The LeanPHP framework provides automatic ETag (Entity Tag) generation and validation for efficient HTTP caching. ETags enable conditional requests that can significantly reduce bandwidth usage and improve application performance by allowing clients to cache responses and validate them efficiently.

## Table of Contents

- [Overview](#overview)
- [How ETags Work](#how-etags-work)
- [Middleware Implementation](#middleware-implementation)
- [ETag Generation](#etag-generation)
- [Conditional Request Handling](#conditional-request-handling)
- [Response Types](#response-types)
- [Performance Benefits](#performance-benefits)
- [Security Considerations](#security-considerations)
- [Usage Examples](#usage-examples)
- [Browser Behavior](#browser-behavior)
- [Testing](#testing)

## Overview

ETags (Entity Tags) are HTTP response headers that act as version identifiers for resources. The LeanPHP ETag middleware provides:

- **Automatic ETag generation**: SHA256-based content fingerprinting
- **Conditional request support**: Handles `If-None-Match` headers
- **304 Not Modified responses**: Reduces bandwidth when content hasn't changed
- **JSON-only scope**: Optimized for API responses
- **Base64url encoding**: Web-safe ETag values
- **Cache control headers**: Proper cache directives for optimal behavior

## How ETags Work

### Basic ETag Flow

```http
# Initial request
GET /api/users HTTP/1.1
Host: api.example.com

# Server response with ETag
HTTP/1.1 200 OK
Content-Type: application/json
ETag: "B3K5Gf8M_X1lHJr4KZq2vQ"
Cache-Control: private, max-age=0, must-revalidate

{"users": [...]}

# Subsequent request with If-None-Match
GET /api/users HTTP/1.1
Host: api.example.com
If-None-Match: "B3K5Gf8M_X1lHJr4KZq2vQ"

# Server response if unchanged
HTTP/1.1 304 Not Modified
ETag: "B3K5Gf8M_X1lHJr4KZq2vQ"
Cache-Control: private, max-age=0, must-revalidate
```

### ETag Types

**Strong ETags**: Indicate byte-for-byte equality
```http
ETag: "B3K5Gf8M_X1lHJr4KZq2vQ"
```

**Weak ETags**: Indicate semantic equality (not used by LeanPHP)
```http
ETag: W/"B3K5Gf8M_X1lHJr4KZq2vQ"
```

LeanPHP uses strong ETags exclusively, ensuring exact content matching.

## Middleware Implementation

### Core Middleware Class

The ETag middleware processes responses to add caching support:

```php
namespace App\Middleware;

class ETag
{
    public function handle(Request $request, callable $next): Response
    {
        // Process the request to get the response first
        $response = $next($request);

        // Only apply ETag for GET requests with 200 status and JSON content
        if (!$this->shouldApplyETag($request, $response)) {
            return $response;
        }

        // Generate ETag from response body
        $etag = $this->generateETag($response->getBody());

        // Check If-None-Match header
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            // Return 304 Not Modified with ETag header but no body
            return Response::noContent()
                ->status(304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        // Add ETag header to the response
        return $response->header('ETag', $etag);
    }
}
```

### Application Conditions

The middleware only applies ETags to specific responses:

```php
private function shouldApplyETag(Request $request, Response $response): bool
{
    // Only GET requests
    if ($request->method() !== 'GET') {
        return false;
    }

    // Only 200 OK responses
    if ($response->getStatusCode() !== 200) {
        return false;
    }

    // Only JSON responses
    $contentType = $response->getHeader('content-type');
    if ($contentType === null || !str_starts_with($contentType, 'application/json')) {
        return false;
    }

    // Must have a non-empty body
    return !empty($response->getBody());
}
```

This ensures ETags are only used where they provide value:
- **GET requests**: ETags don't make sense for POST/PUT/DELETE
- **Success responses**: Only cache successful responses
- **JSON content**: API-focused caching strategy
- **Non-empty bodies**: Avoid ETags for empty responses

## ETag Generation

### Content-Based Hashing

ETags are generated using SHA256 hashing of the response content:

```php
private function generateETag(string $content): string
{
    // SHA256 hash, then base64url encode
    $hash = hash('sha256', $content, true);
    $base64url = $this->base64UrlEncode($hash);

    // Wrap in quotes as per HTTP specification
    return '"' . $base64url . '"';
}
```

### Base64url Encoding

The middleware uses base64url encoding for web-safe ETags:

```php
private function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
```

This encoding:
- Replaces `+` with `-`
- Replaces `/` with `_`
- Removes padding `=` characters
- Creates URL-safe strings

### ETag Format

Generated ETags follow the HTTP specification:

```php
// Example ETag generation
$content = '{"users":[{"id":1,"name":"John"}]}';
$hash = hash('sha256', $content, true);
$encoded = base64UrlEncode($hash);
$etag = '"' . $encoded . '"';
// Result: "B3K5Gf8M_X1lHJr4KZq2vQ"
```

### Collision Resistance

SHA256 provides excellent collision resistance:
- **256-bit hash**: Extremely low collision probability
- **Content sensitive**: Any change produces different ETag
- **Deterministic**: Same content always produces same ETag
- **Fast computation**: Minimal performance impact

## Conditional Request Handling

### If-None-Match Processing

The middleware handles conditional requests via the `If-None-Match` header:

```php
private function etagMatches(string $ifNoneMatch, string $etag): bool
{
    // Handle wildcard
    if ($ifNoneMatch === '*') {
        return true;
    }

    // Handle comma-separated list of ETags
    $etags = array_map('trim', explode(',', $ifNoneMatch));

    return in_array($etag, $etags, true);
}
```

### Wildcard Support

The `*` wildcard matches any ETag:

```http
If-None-Match: *
```

This is useful for conditional PUT requests where the client wants to ensure they're not overwriting existing content.

### Multiple ETag Support

Clients can send multiple ETags in the `If-None-Match` header:

```http
If-None-Match: "version1", "version2", "version3"
```

The middleware checks if the current ETag matches any of the provided values.

### ETag Comparison

ETag comparison is:
- **Case-sensitive**: `"ABC"` ≠ `"abc"`
- **Exact match**: Including quotes and formatting
- **String-based**: No semantic interpretation

## Response Types

### 200 OK with ETag

When content is fresh or client has no cached version:

```http
HTTP/1.1 200 OK
Content-Type: application/json
ETag: "B3K5Gf8M_X1lHJr4KZq2vQ"
Content-Length: 1234

{"users": [...]}
```

### 304 Not Modified

When client's cached version is still valid:

```http
HTTP/1.1 304 Not Modified
ETag: "B3K5Gf8M_X1lHJr4KZq2vQ"
Cache-Control: private, max-age=0, must-revalidate
```

Key characteristics of 304 responses:
- **No response body**: Saves bandwidth
- **Same ETag**: Confirms cache validity
- **Cache-Control**: Provides caching guidance
- **Immediate return**: Skips further processing

### Cache-Control Headers

The middleware adds appropriate cache control headers:

```php
return Response::noContent()
    ->status(304)
    ->header('ETag', $etag)
    ->header('Cache-Control', 'private, max-age=0, must-revalidate');
```

- **private**: Response is user-specific (for APIs with auth)
- **max-age=0**: Must revalidate immediately
- **must-revalidate**: Cannot serve stale content

## Performance Benefits

### Bandwidth Reduction

ETags can significantly reduce bandwidth usage:

```php
// Without ETag: 50KB JSON response every time
GET /api/large-dataset
→ 200 OK + 50KB response body

// With ETag: 50KB first time, ~200 bytes subsequent times
GET /api/large-dataset
→ 200 OK + 50KB response body + ETag

GET /api/large-dataset (If-None-Match: "etag")
→ 304 Not Modified + ~200 bytes
```

### Server Processing

304 responses provide server-side benefits:
- **Early return**: Skip response body generation
- **Reduced serialization**: No JSON encoding needed
- **Lower memory**: No large response buffers
- **Faster responses**: Minimal processing time

### Network Efficiency

ETags improve network utilization:
- **Reduced latency**: Smaller 304 responses
- **Better throughput**: More requests per connection
- **Mobile optimization**: Crucial for limited bandwidth
- **CDN efficiency**: Better cache hit rates

### Example Performance Impact

```php
// Large API response
$users = User::with(['profile', 'posts', 'comments'])->get();
$response = Response::json($users); // 100KB response

// First request: 100KB transfer
// Subsequent requests: ~200 bytes (304)
// Bandwidth savings: 99.8%
```

## Security Considerations

### Information Disclosure

ETags can potentially leak information:

```php
// Potentially problematic - reveals user count
$userCount = User::count();
$etag = '"user-count-' . $userCount . '"';

// Better - content-based hash reveals nothing
$content = json_encode($users);
$etag = $this->generateETag($content);
```

### Timing Attacks

ETag generation timing should be consistent:
- **SHA256**: Consistent computation time
- **Content-based**: No data-dependent timing
- **No user-specific**: Avoid user-dependent computation

### Cache Poisoning

ETags help prevent cache poisoning:
- **Content integrity**: Hash verifies content hasn't changed
- **Strong validation**: Byte-for-byte accuracy
- **No predictable**: Cannot guess valid ETags

## Usage Examples

### Basic API Endpoint

```php
// routes/api.php
$router->get('/users', [UserController::class, 'index'])
    ->middleware([new ETag()]);

// UserController
public function index(Request $request): Response
{
    $users = User::all();
    return Response::json($users);
    // ETag automatically added by middleware
}
```

### Custom ETag Logic

While the middleware handles ETags automatically, you can customize behavior:

```php
class CustomETagController
{
    public function index(Request $request): Response
    {
        // Skip ETag for real-time data
        if ($request->query('realtime') === 'true') {
            return Response::json($data)->header('Cache-Control', 'no-cache');
        }

        // Normal ETag processing
        return Response::json($data);
    }
}
```

### Conditional Processing

Leverage ETags for expensive operations:

```php
public function generateReport(Request $request): Response
{
    $reportId = $request->route('id');

    // Check if report has changed since last request
    $lastModified = Report::find($reportId)->updated_at;
    $etag = '"report-' . $reportId . '-' . $lastModified->timestamp . '"';

    $ifNoneMatch = $request->header('if-none-match');
    if ($ifNoneMatch === $etag) {
        return Response::noContent()->status(304)->header('ETag', $etag);
    }

    // Generate expensive report only if needed
    $report = $this->generateExpensiveReport($reportId);
    return Response::json($report)->header('ETag', $etag);
}
```

## Browser Behavior

### Automatic Caching

Modern browsers automatically handle ETags:

```javascript
// Browser automatically sends If-None-Match
fetch('/api/users')
    .then(response => {
        if (response.status === 304) {
            // Use cached data
            return getCachedData('/api/users');
        }
        return response.json();
    });
```

### Cache Storage

Browsers store ETags with cached responses:
- **Memory cache**: For current session
- **Disk cache**: Across browser restarts
- **HTTP cache API**: For service workers

### Developer Tools

ETags are visible in browser developer tools:
- **Network tab**: Shows ETag headers
- **Application tab**: Shows cached responses
- **Console**: ETag values in response headers

## Testing

### Unit Tests

Test ETag functionality comprehensively:

```php
public function test_adds_etag_to_get_json_response(): void
{
    $request = $this->createMockRequest('GET', '/test');

    $next = function (Request $req): Response {
        return Response::json(['data' => 'test']);
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertNotNull($response->getHeader('ETag'));

    // ETag should be quoted and non-empty
    $etag = $response->getHeader('ETag');
    $this->assertStringStartsWith('"', $etag);
    $this->assertStringEndsWith('"', $etag);
    $this->assertGreaterThan(2, strlen($etag));
}

public function test_returns_304_when_if_none_match_matches(): void
{
    // First request to get the ETag
    $request1 = $this->createMockRequest('GET', '/test');

    $next = function (Request $req): Response {
        return Response::json(['data' => 'test']);
    };

    $response1 = $this->middleware->handle($request1, $next);
    $etag = $response1->getHeader('ETag');

    // Second request with If-None-Match header
    $request2 = $this->createMockRequest('GET', '/test', ['if-none-match' => $etag]);

    $response2 = $this->middleware->handle($request2, $next);

    $this->assertEquals(304, $response2->getStatusCode());
    $this->assertEquals($etag, $response2->getHeader('ETag'));
    $this->assertEquals('', $response2->getBody());
}
```

### Integration Tests

Test ETags in complete request flows:

```php
public function test_etag_caching_flow(): void
{
    // First request - should get content and ETag
    $response1 = $this->makeRequest('GET', '/api/users');

    $this->assertEquals(200, $response1->getStatusCode());
    $etag = $response1->getHeader('ETag');
    $this->assertNotNull($etag);

    // Second request with ETag - should get 304
    $response2 = $this->makeRequest('GET', '/api/users', [
        'If-None-Match' => $etag
    ]);

    $this->assertEquals(304, $response2->getStatusCode());
    $this->assertEquals($etag, $response2->getHeader('ETag'));
    $this->assertEmpty($response2->getBody());
}
```

### ETag Consistency Tests

Ensure ETags are deterministic:

```php
public function test_same_content_produces_same_etag(): void
{
    $content = json_encode(['data' => 'test']);

    $etag1 = $this->generateETag($content);
    $etag2 = $this->generateETag($content);

    $this->assertEquals($etag1, $etag2);
}

public function test_different_content_produces_different_etag(): void
{
    $content1 = json_encode(['data' => 'test1']);
    $content2 = json_encode(['data' => 'test2']);

    $etag1 = $this->generateETag($content1);
    $etag2 = $this->generateETag($content2);

    $this->assertNotEquals($etag1, $etag2);
}
```

### Common Test Scenarios

1. **ETag generation**: Correct format and uniqueness
2. **304 responses**: Proper Not Modified handling
3. **Content changes**: ETags change when content changes
4. **Multiple ETags**: If-None-Match with multiple values
5. **Wildcard matching**: If-None-Match with `*`
6. **Non-applicable responses**: ETags not added to POST, 404, etc.
7. **Cache headers**: Proper Cache-Control headers on 304
