# Logging System

The LeanPHP framework provides a comprehensive logging system for tracking application events, debugging issues, and monitoring system health. The logging system includes automatic request tracking, sensitive data redaction, and flexible output configuration suitable for both development and production environments.

## Table of Contents

- [Overview](#overview)
- [Core Logger Class](#core-logger-class)
- [Log Levels](#log-levels)
- [Context Management](#context-management)
- [Security Features](#security-features)
- [Request Tracking](#request-tracking)
- [Configuration](#configuration)
- [Output Formats](#output-formats)
- [Integration with Middleware](#integration-with-middleware)
- [Production Considerations](#production-considerations)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)

## Overview

The LeanPHP logging system consists of several integrated components:

- **Logger Class**: Core logging functionality with multiple log levels
- **RequestId Middleware**: Automatic request tracking and correlation
- **Security Features**: Automatic redaction of sensitive information
- **Context Support**: Structured logging with contextual data
- **Flexible Output**: File-based or stream-based logging
- **Singleton Pattern**: Global logger instance for consistent usage

## Core Logger Class

### Basic Logger Implementation

The logger provides a simple but powerful interface:

```php
namespace LeanPHP\Logging;

class Logger
{
    private string $logPath;
    private array $context = [];

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;

        // Ensure log directory exists (skip for streams like php://stderr)
        if (!str_starts_with($logPath, 'php://')) {
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }
}
```

### Singleton Access

The logger uses a singleton pattern for global access:

```php
public static function getInstance(): self
{
    static $instance = null;

    if ($instance === null) {
        $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs/app.log';
        $instance = new self($logPath);
    }

    return $instance;
}

// Usage
$logger = Logger::getInstance();
$logger->info('Application started');
```

### Automatic Directory Creation

The logger automatically creates log directories:

```php
// Log path: storage/logs/api/app.log
// Automatically creates: storage/, storage/logs/, storage/logs/api/
$logger = new Logger('storage/logs/api/app.log');
```

This ensures logs can be written immediately without manual setup.

## Log Levels

### Available Log Levels

The logger supports four standard log levels:

```php
// Information messages
$logger->info('User logged in', ['user_id' => 123]);

// Error messages
$logger->error('Database connection failed', ['dsn' => 'mysql:...']);

// Warning messages
$logger->warning('Rate limit approaching', ['remaining' => 5]);

// Debug messages
$logger->debug('Processing request', ['endpoint' => '/api/users']);
```

### Log Level Usage Guidelines

**INFO Level**:
- Application lifecycle events
- Successful operations
- Business logic milestones
- User actions

**ERROR Level**:
- Exceptions and errors
- Failed operations
- System failures
- Critical issues

**WARNING Level**:
- Recoverable errors
- Performance issues
- Configuration problems
- Approaching limits

**DEBUG Level**:
- Detailed execution flow
- Variable states
- Algorithm steps
- Development debugging

### Log Entry Format

All log entries follow a consistent format:

```php
private function log(string $level, string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');

    // Merge context with global context
    $fullContext = array_merge($this->context, $context);

    // Redact sensitive information
    $fullContext = $this->redactSensitiveData($fullContext);

    // Format the log entry
    $logEntry = sprintf(
        "[%s] %s: %s%s\n",
        $timestamp,
        $level,
        $message,
        empty($fullContext) ? '' : ' ' . json_encode($fullContext, JSON_UNESCAPED_SLASHES)
    );

    // Write to file
    $flags = FILE_APPEND;
    if (!str_starts_with($this->logPath, 'php://')) {
        $flags |= LOCK_EX;
    }
    file_put_contents($this->logPath, $logEntry, $flags);
}
```

Example log output:
```
[2024-01-15 14:30:25] INFO: User authenticated {"user_id":123,"method":"jwt","request_id":"a1b2c3d4-e5f6g7h8"}
[2024-01-15 14:30:26] ERROR: Database query failed {"query":"SELECT * FROM users","error":"Connection timeout","request_id":"a1b2c3d4-e5f6g7h8"}
```

## Context Management

### Global Context

Set context that appears in all subsequent log entries:

```php
$logger = Logger::getInstance();

// Set global context
$logger->setContext([
    'user_id' => 123,
    'session_id' => 'abc123',
    'environment' => 'production'
]);

// All subsequent logs include this context
$logger->info('Processing request');
// Output: [2024-01-15 14:30:25] INFO: Processing request {"user_id":123,"session_id":"abc123","environment":"production"}
```

### Per-Message Context

Add context to individual log messages:

```php
$logger->info('User action performed', [
    'action' => 'profile_update',
    'changes' => ['email', 'phone'],
    'ip_address' => '192.168.1.100'
]);
```

### Context Merging

Local context is merged with global context:

```php
// Global context
$logger->setContext(['user_id' => 123, 'session_id' => 'abc']);

// Local context merges with global
$logger->info('Action performed', ['action' => 'login', 'user_id' => 456]);
// Result: {"user_id":456,"session_id":"abc","action":"login"}
// Note: Local user_id overrides global user_id
```

### Structured Logging Benefits

Structured context enables:
- **Log aggregation**: Search across multiple log entries
- **Filtering**: Find logs by specific criteria
- **Metrics**: Extract statistics from log data
- **Debugging**: Trace requests across components

## Security Features

### Automatic Data Redaction

The logger automatically redacts sensitive information:

```php
private function redactSensitiveData(array $context): array
{
    $redacted = $context;
    $sensitiveKeys = ['authorization', 'bearer', 'token', 'password', 'secret', 'key'];

    foreach ($redacted as $key => $value) {
        $keyLower = strtolower((string) $key);

        // Check if this key contains sensitive information
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains($keyLower, $sensitiveKey)) {
                $redacted[$key] = '[REDACTED]';
                break;
            }
        }

        // Recursively redact arrays
        if (is_array($value)) {
            $redacted[$key] = $this->redactSensitiveData($value);
        }
    }

    return $redacted;
}
```

### Redacted Data Examples

```php
// Before redaction
$context = [
    'authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
    'user_password' => 'secret123',
    'api_key' => 'sk-1234567890abcdef',
    'user_email' => 'user@example.com',  // Not redacted
    'user_id' => 123                     // Not redacted
];

// After redaction
$context = [
    'authorization' => '[REDACTED]',
    'user_password' => '[REDACTED]',
    'api_key' => '[REDACTED]',
    'user_email' => 'user@example.com',
    'user_id' => 123
];
```

### Nested Data Redaction

The redaction works recursively on nested arrays:

```php
$context = [
    'request' => [
        'headers' => [
            'authorization' => 'Bearer token123',
            'content-type' => 'application/json'
        ],
        'body' => [
            'username' => 'john',
            'password' => 'secret123'
        ]
    ]
];

// After redaction
$context = [
    'request' => [
        'headers' => [
            'authorization' => '[REDACTED]',
            'content-type' => 'application/json'
        ],
        'body' => [
            'username' => 'john',
            'password' => '[REDACTED]'
        ]
    ]
];
```

### Sensitive Keywords

The logger redacts keys containing these substrings (case-insensitive):
- `authorization`
- `bearer`
- `token`
- `password`
- `secret`
- `key`

## Request Tracking

### RequestId Middleware

The `RequestId` middleware provides automatic request correlation:

```php
namespace App\Middleware;

class RequestId
{
    public function handle(Request $request, callable $next): Response
    {
        // Get or generate request ID
        $requestId = $request->header('x-request-id') ?: $this->generateRequestId();

        // Set request ID in logger context
        $logger = Logger::getInstance();
        $logger->setContext(['request_id' => $requestId]);

        // Log the incoming request
        $logger->info('Incoming request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->header('user-agent'),
        ]);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response = $response->header('X-Request-Id', $requestId);

        // Log the response
        $logger->info('Outgoing response', [
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4))
        );
    }
}
```

### Request ID Format

Generated request IDs follow a consistent format:
```
a1b2c3d4-e5f6g7h8
```

- 8 hex characters + dash + 8 hex characters
- Total length: 17 characters
- URL-safe and easy to copy/paste

### Request Correlation

With request IDs, you can trace complete request flows:

```
[2024-01-15 14:30:25] INFO: Incoming request {"method":"POST","path":"/api/users","user_agent":"curl/7.68.0","request_id":"a1b2c3d4-e5f6g7h8"}
[2024-01-15 14:30:25] DEBUG: Validating request data {"request_id":"a1b2c3d4-e5f6g7h8"}
[2024-01-15 14:30:25] INFO: User created {"user_id":124,"request_id":"a1b2c3d4-e5f6g7h8"}
[2024-01-15 14:30:25] INFO: Outgoing response {"status":201,"request_id":"a1b2c3d4-e5f6g7h8"}
```

### Request ID Propagation

Request IDs can be:
- **Received**: From client via `X-Request-Id` header
- **Generated**: Automatically created if not provided
- **Returned**: Sent back to client in response headers
- **Logged**: Included in all log entries for the request

## Configuration

### Environment Variables

Configure logging through environment variables:

```bash
# Log file path
LOG_PATH=storage/logs/app.log

# For containerized environments
LOG_PATH=php://stderr

# Custom log location
LOG_PATH=/var/log/myapp/application.log
```

### Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_PATH` | `storage/logs/app.log` | Log file path or stream |

### Development Configuration

```bash
# Development - file-based logging
LOG_PATH=storage/logs/app.log
```

### Production Configuration

```bash
# Production - stream to stderr for containers
LOG_PATH=php://stderr

# Or centralized logging
LOG_PATH=/var/log/myapp/app.log
```

### Stream vs File Logging

**File Logging** (`storage/logs/app.log`):
- **Pros**: Persistent, can be rotated, easy to access
- **Cons**: Disk space usage, requires file permissions
- **Use case**: Traditional server deployments

**Stream Logging** (`php://stderr`):
- **Pros**: No disk usage, container-friendly, centralized collection
- **Cons**: No persistence, requires external log aggregation
- **Use case**: Containerized deployments, cloud platforms

## Output Formats

### Standard Log Format

All log entries follow this format:
```
[TIMESTAMP] LEVEL: MESSAGE CONTEXT
```

Example:
```
[2024-01-15 14:30:25] INFO: User authenticated {"user_id":123,"method":"jwt","request_id":"a1b2c3d4-e5f6g7h8"}
```

### Timestamp Format

Timestamps use ISO 8601 format without timezone:
```php
$timestamp = date('Y-m-d H:i:s');
// Example: 2024-01-15 14:30:25
```

### JSON Context

Context data is encoded as JSON with specific flags:
```php
json_encode($fullContext, JSON_UNESCAPED_SLASHES)
```

This ensures:
- URLs aren't escaped (`/` remains `/`)
- Readable output
- Valid JSON for parsing

### Empty Context Handling

When no context is provided, context JSON is omitted:
```
[2024-01-15 14:30:25] INFO: Application started
[2024-01-15 14:30:25] INFO: User logged in {"user_id":123}
```

## Integration with Middleware

### Middleware Stack Integration

Logging integrates with the middleware stack:

```php
// Typical middleware order
$globalMiddleware = [
    new App\Middleware\RequestId(),     // First - sets up request tracking
    new App\Middleware\ErrorHandler(),  // May log errors
    new App\Middleware\RateLimiter(),   // May log rate limit events
    // ... other middleware
];
```

### Error Handler Integration

The error handler can use logging for error tracking:

```php
class ErrorHandler
{
    private function handleException(Throwable $e, Request $request): Response
    {
        $logger = Logger::getInstance();

        $logger->error('Unhandled exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'path' => $request->path(),
            'method' => $request->method()
        ]);

        // Return error response...
    }
}
```

### Custom Middleware Logging

Add logging to custom middleware:

```php
class CustomMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $logger = Logger::getInstance();

        $start = microtime(true);
        $logger->debug('Middleware processing started', [
            'middleware' => static::class
        ]);

        $response = $next($request);

        $duration = microtime(true) - $start;
        $logger->info('Middleware processing completed', [
            'middleware' => static::class,
            'duration_ms' => round($duration * 1000, 2)
        ]);

        return $response;
    }
}
```

## Production Considerations

### Log Rotation

For file-based logging in production, implement log rotation:

```bash
# Using logrotate (Linux)
/var/log/myapp/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
```

### Centralized Logging

For multi-server deployments:

```bash
# Send logs to centralized system
LOG_PATH=php://stderr

# Use container orchestration log collection
# Docker: docker logs container_name
# Kubernetes: kubectl logs pod_name
```

### Log Aggregation

Common log aggregation solutions:
- **ELK Stack**: Elasticsearch, Logstash, Kibana
- **Fluentd**: Log collector and processor
- **Splunk**: Commercial log analysis platform
- **Datadog**: Cloud-based monitoring
- **AWS CloudWatch**: Amazon's logging service

### File Locking

The logger uses file locking for concurrent writes:

```php
// Uses LOCK_EX for atomic writes
file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
```

This prevents:
- **Corrupted log entries**: Multiple processes writing simultaneously
- **Lost log data**: Race conditions during writes
- **Partial writes**: Incomplete log entries

## Performance Optimization

### File vs Stream Performance

**File Logging Performance**:
- **Disk I/O**: Adds latency to requests
- **File locking**: Can create contention
- **Buffer management**: OS handles buffering

**Stream Logging Performance**:
- **Memory based**: Faster than disk I/O
- **No locking**: No file contention
- **Immediate output**: No intermediate storage

### Context Size Management

Keep context data reasonable:

```php
// Good - specific, relevant data
$logger->info('User action', [
    'action' => 'profile_update',
    'user_id' => 123,
    'fields' => ['email', 'phone']
]);

// Avoid - large, complex objects
$logger->info('User action', [
    'user' => $fullUserObject,      // Potentially huge
    'request' => $fullRequestData,  // Lots of irrelevant data
    'session' => $sessionObject     // Complex nested data
]);
```

### Memory Usage

Logger memory usage is minimal:
- **Singleton pattern**: One instance per request
- **No buffering**: Immediate write to output
- **Context redaction**: Prevents sensitive data accumulation

## Testing

### Unit Tests

Test logging functionality comprehensively:

```php
public function test_logger_redacts_authorization_headers(): void
{
    $tempLogFile = tempnam(sys_get_temp_dir(), 'test_log');
    $logger = new Logger($tempLogFile);

    $sensitiveContext = [
        'authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
        'user_id' => 123,
        'action' => 'login'
    ];

    $logger->info('Test message', $sensitiveContext);

    $logContents = file_get_contents($tempLogFile);

    // Should contain redacted authorization
    $this->assertStringContainsString('[REDACTED]', $logContents);
    $this->assertStringNotContainsString('Bearer eyJ0eXAi', $logContents);

    // Should contain non-sensitive data
    $this->assertStringContainsString('"user_id":123', $logContents);
    $this->assertStringContainsString('"action":"login"', $logContents);

    unlink($tempLogFile);
}

public function test_logger_handles_nested_sensitive_data(): void
{
    $tempLogFile = tempnam(sys_get_temp_dir(), 'test_log');
    $logger = new Logger($tempLogFile);

    $context = [
        'request' => [
            'headers' => [
                'authorization' => 'Bearer token123',
                'content-type' => 'application/json'
            ],
            'body' => [
                'username' => 'john',
                'password' => 'secret123'
            ]
        ],
        'user_id' => 456
    ];

    $logger->debug('Request processing', $context);

    $logContents = file_get_contents($tempLogFile);

    // Check redaction in nested data
    $this->assertStringContainsString('[REDACTED]', $logContents);
    $this->assertStringNotContainsString('Bearer token123', $logContents);
    $this->assertStringNotContainsString('secret123', $logContents);

    // Non-sensitive data should remain
    $this->assertStringContainsString('"username":"john"', $logContents);
    $this->assertStringContainsString('"user_id":456', $logContents);

    unlink($tempLogFile);
}
```

### Integration Tests

Test logging in complete request flows:

```php
public function test_request_id_logging_flow(): void
{
    // Configure test environment
    $_ENV['LOG_PATH'] = 'php://stderr';

    // Capture stderr output
    $this->expectOutputRegex('/request_id/');

    // Make request that should generate logs
    $response = $this->makeRequest('GET', '/api/users');

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertNotNull($response->getHeader('X-Request-Id'));
}
```

### Log Format Validation

Ensure log format consistency:

```php
public function test_log_format_consistency(): void
{
    $tempLogFile = tempnam(sys_get_temp_dir(), 'test_log');
    $logger = new Logger($tempLogFile);

    $logger->info('Test message', ['key' => 'value']);

    $logContents = file_get_contents($tempLogFile);
    $lines = explode("\n", trim($logContents));

    foreach ($lines as $line) {
        // Each line should match: [TIMESTAMP] LEVEL: MESSAGE
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] [A-Z]+: .*$/',
            $line
        );
    }

    unlink($tempLogFile);
}
```

### Common Test Scenarios

1. **Log levels**: All levels write correctly
2. **Context merging**: Global and local context combine properly
3. **Sensitive data**: Redaction works for all sensitive patterns
4. **File creation**: Directories created automatically
5. **Request correlation**: Request IDs appear in all logs
6. **Format consistency**: Log format matches specification
7. **Performance**: Logging doesn't significantly impact response time
