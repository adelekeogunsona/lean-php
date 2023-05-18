# Testing

The LeanPHP framework provides a comprehensive testing ecosystem designed to ensure code quality, reliability, and maintainability. This guide covers the testing infrastructure, patterns, and best practices implemented in the framework.

## Table of Contents

- [Overview](#overview)
- [Testing Architecture](#testing-architecture)
- [PHPUnit Configuration](#phpunit-configuration)
- [Test Environment Setup](#test-environment-setup)
- [Unit Testing](#unit-testing)
- [Integration Testing](#integration-testing)
- [Database Testing](#database-testing)
- [API Testing](#api-testing)
- [Middleware Testing](#middleware-testing)
- [Authentication Testing](#authentication-testing)
- [Error Handling Testing](#error-handling-testing)
- [Performance Testing](#performance-testing)
- [Test Utilities and Helpers](#test-utilities-and-helpers)
- [Testing Best Practices](#testing-best-practices)

## Overview

LeanPHP's testing strategy follows industry best practices with a focus on:

1. **Unit Tests** - Testing individual components in isolation
2. **Integration Tests** - Testing component interactions and complete request flows
3. **Fast Execution** - Optimized test suite for rapid feedback
4. **Comprehensive Coverage** - Tests for all critical functionality
5. **Real-World Scenarios** - Tests that mirror actual usage patterns

### Testing Components

- **PHPUnit Framework** - Primary testing framework
- **Test Bootstrap** (`tests/bootstrap.php`) - Test environment initialization
- **Mock Objects** - For isolating dependencies
- **Test Databases** - SQLite in-memory databases for testing
- **Request Simulation** - Creating and testing HTTP requests
- **Environment Management** - Clean test environments

## Testing Architecture

### Directory Structure

```
tests/
├── bootstrap.php              # Test initialization
├── Unit/                      # Unit tests
│   ├── ConfigTest.php
│   ├── CorsTest.php
│   ├── DBTest.php
│   ├── ErrorHandlerTest.php
│   ├── ETagTest.php
│   ├── HttpTest.php
│   ├── ProblemTest.php
│   ├── RateLimiterMiddlewareTest.php
│   ├── RateLimitTest.php
│   ├── RequireScopesTest.php
│   ├── RouterHeadOptionsTest.php
│   ├── SecurityTest.php
│   └── TokenTest.php
└── Integration/               # Integration tests
    └── ApiFlowTest.php
```

### Test Categories

**Unit Tests:**
- Test individual classes and methods in isolation
- Mock external dependencies
- Fast execution (milliseconds per test)
- High coverage of edge cases

**Integration Tests:**
- Test complete request/response cycles
- Test middleware interactions
- Use real components without mocking
- Test authentication and authorization flows

## PHPUnit Configuration

### Configuration File (`phpunit.xml`)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory="storage/cache/phpunit"
         testdox="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./src</directory>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <env name="DB_DSN" value="sqlite::memory:"/>
    </php>
</phpunit>
```

### Configuration Features

- **Test Bootstrap**: Initializes autoloader and environment
- **Color Output**: Enhanced readability
- **Test Documentation**: Human-readable test descriptions
- **Cache Directory**: Improved performance
- **Source Inclusion**: Code coverage analysis
- **Environment Variables**: Testing-specific configuration

## Test Environment Setup

### Bootstrap File (`tests/bootstrap.php`)

```php
<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}
```

### Environment Management

```php
class ConfigTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        // Store original environment
        $this->originalEnv = [
            'APP_ENV' => ['env' => $_ENV['APP_ENV'] ?? null, 'server' => $_SERVER['APP_ENV'] ?? null],
            'APP_DEBUG' => ['env' => $_ENV['APP_DEBUG'] ?? null, 'server' => $_SERVER['APP_DEBUG'] ?? null],
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment
        foreach ($this->originalEnv as $key => $values) {
            if ($values['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $values['env'];
            }
        }
    }
}
```

### Test Database Setup

```php
class DBTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not available');
        }

        // Reset connection for clean state
        DB::resetConnection();

        // Configure in-memory SQLite for testing
        putenv('DB_DSN=sqlite::memory:');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');
        putenv('DB_ATTR_PERSISTENT=false');
    }

    protected function tearDown(): void
    {
        DB::resetConnection();
    }
}
```

## Unit Testing

### Testing Individual Components

Unit tests focus on testing individual classes and methods in isolation:

```php
class ProblemTest extends TestCase
{
    public function test_make_creates_basic_problem_response(): void
    {
        $response = Problem::make(404, 'Not Found', 'User 123 not found');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeaders()['content-type']);

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/generic', $body['type']);
        $this->assertEquals('Not Found', $body['title']);
        $this->assertEquals(404, $body['status']);
        $this->assertEquals('User 123 not found', $body['detail']);
    }

    public function test_validation_creates_422_problem_with_errors(): void
    {
        $errors = [
            'email' => ['Must be a valid email address'],
            'password' => ['Must be at least 8 characters long']
        ];

        $response = Problem::validation($errors);

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/validation', $body['type']);
        $this->assertEquals('Unprocessable Content', $body['title']);
        $this->assertEquals($errors, $body['errors']);
    }
}
```

### Testing HTTP Components

```php
class HttpTest extends TestCase
{
    public function test_request_from_globals_parses_correctly(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/users?page=2';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_GET['page'] = '2';

        $request = Request::fromGlobals();

        $this->assertEquals('POST', $request->method());
        $this->assertEquals('/api/users', $request->path());
        $this->assertEquals('2', $request->query('page'));
        $this->assertEquals('application/json', $request->header('content-type'));
    }

    public function test_response_json_creates_proper_response(): void
    {
        $data = ['message' => 'Hello', 'status' => 'success'];
        $response = Response::json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertEquals(json_encode($data), $response->getBody());
    }
}
```

### Mocking Dependencies

```php
class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $errorHandler;
    private Request $request;

    protected function setUp(): void
    {
        $this->errorHandler = new ErrorHandler();

        // Create mock request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test/path';
        $this->request = Request::fromGlobals();
    }

    public function test_debug_mode_shows_exception_details(): void
    {
        $_ENV['APP_DEBUG'] = 'true';

        $exception = new Exception('Test exception message', 123);

        $response = $this->errorHandler->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('Test exception message', $body['detail']);
        $this->assertArrayHasKey('debug', $body);
    }
}
```

## Integration Testing

### Complete Request Flow Testing

Integration tests verify that components work together correctly:

```php
class ApiFlowTest extends TestCase
{
    private Router $router;
    private array $globalMiddleware;

    protected function setUp(): void
    {
        $this->setupTestEnvironment();
        $this->setupTestDatabase();
        $this->setupRouter();
    }

    private function setupTestEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['JWT_KID'] = 'test-key';
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://app.example.com';
        $_ENV['RATE_LIMIT_DEFAULT'] = '100';
    }

    private function setupRouter(): void
    {
        $this->router = new Router();

        // Set up global middleware stack
        $this->globalMiddleware = [
            new App\Middleware\ErrorHandler(),
            new App\Middleware\Cors(),
            new App\Middleware\JsonBodyParser(),
            new App\Middleware\RateLimiter(),
        ];

        // Register test routes
        $this->router->get('/health', [HealthController::class, 'check']);
        $this->router->post('/auth/login', [AuthController::class, 'login']);

        $this->router->group(['middleware' => [new App\Middleware\AuthBearer()]], function ($router) {
            $router->get('/users', [UserController::class, 'index']);
            $router->post('/users', [UserController::class, 'create']);
        });
    }

    public function test_complete_authentication_flow(): void
    {
        // 1. Login to get token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/auth/login';

        $loginData = ['email' => 'admin@example.com', 'password' => 'admin123'];
        $request = Request::fromGlobals()->setJsonData($loginData);

        $response = $this->processRequest($request);
        $this->assertEquals(200, $response->getStatusCode());

        $loginBody = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('token', $loginBody);

        // 2. Use token for authenticated request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $loginBody['token'];

        $authenticatedRequest = Request::fromGlobals();
        $response = $this->processRequest($authenticatedRequest);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('users', $body);
    }

    private function processRequest(Request $request): Response
    {
        return (new MiddlewareRunner())->run(
            $request,
            $this->globalMiddleware,
            fn($req) => $this->router->dispatch($req)
        );
    }
}
```

### Testing Middleware Interactions

```php
public function test_cors_preflight_with_authentication(): void
{
    $_ENV['CORS_ALLOW_ORIGINS'] = 'https://app.example.com';
    $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $_SERVER['REQUEST_URI'] = '/users';
    $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization,Content-Type';

    $request = Request::fromGlobals();
    $response = $this->processRequest($request);

    $this->assertEquals(204, $response->getStatusCode());
    $this->assertEquals('https://app.example.com', $response->getHeader('access-control-allow-origin'));
    $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
}
```

## Database Testing

### In-Memory Database Testing

```php
class DBTest extends TestCase
{
    public function test_can_create_table_and_insert_data(): void
    {
        // Create test table
        $createSql = "CREATE TABLE test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            age INTEGER
        )";

        $affected = DB::execute($createSql);
        $this->assertEquals(0, $affected); // DDL returns 0

        // Insert test data
        $insertSql = "INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)";
        $affected = DB::execute($insertSql, ['John Doe', 'john@example.com', 30]);
        $this->assertEquals(1, $affected);

        // Verify data
        $users = DB::select('SELECT * FROM test_users WHERE email = ?', ['john@example.com']);
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]['name']);
    }

    public function test_transaction_rollback_on_exception(): void
    {
        DB::execute("CREATE TABLE test_accounts (id INTEGER PRIMARY KEY, balance INTEGER)");
        DB::execute("INSERT INTO test_accounts (id, balance) VALUES (1, 100), (2, 50)");

        try {
            DB::transaction(function () {
                DB::execute("UPDATE test_accounts SET balance = balance - 50 WHERE id = 1");
                DB::execute("UPDATE test_accounts SET balance = balance + 50 WHERE id = 2");

                // Simulate an error
                throw new Exception("Simulated error");
            });
        } catch (Exception $e) {
            // Expected exception
        }

        // Verify rollback
        $accounts = DB::select('SELECT * FROM test_accounts ORDER BY id');
        $this->assertEquals(100, $accounts[0]['balance']); // Unchanged
        $this->assertEquals(50, $accounts[1]['balance']);  // Unchanged
    }
}
```

### Testing Database Connections

```php
public function test_handles_different_data_types(): void
{
    DB::execute("CREATE TABLE test_types (
        id INTEGER PRIMARY KEY,
        text_col TEXT,
        int_col INTEGER,
        real_col REAL,
        blob_col BLOB
    )");

    $testData = [
        'Hello World',
        42,
        3.14,
        'Binary data'
    ];

    DB::execute(
        "INSERT INTO test_types (text_col, int_col, real_col, blob_col) VALUES (?, ?, ?, ?)",
        $testData
    );

    $result = DB::select('SELECT * FROM test_types WHERE id = 1');
    $this->assertEquals('Hello World', $result[0]['text_col']);
    $this->assertEquals(42, $result[0]['int_col']);
    $this->assertEquals(3.14, $result[0]['real_col']);
}
```

## API Testing

### Testing REST Endpoints

```php
class UserControllerTest extends TestCase
{
    public function test_create_user_with_valid_data(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securepassword123'
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals()->setJsonData($userData);
        $controller = new UserController();

        $response = $controller->create($request);

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('user', $body);
        $this->assertEquals('john@example.com', $body['user']['email']);
    }

    public function test_create_user_with_invalid_data_returns_validation_error(): void
    {
        $invalidData = [
            'name' => '', // Invalid: empty name
            'email' => 'invalid-email', // Invalid: bad email format
            'password' => '123' // Invalid: too short
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';

        $request = Request::fromGlobals()->setJsonData($invalidData);
        $controller = new UserController();

        $response = $controller->create($request);

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/validation', $body['type']);
        $this->assertArrayHasKey('errors', $body);
    }
}
```

### Testing HTTP Status Codes

```php
public function test_not_found_returns_404(): void
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/users/999';

    $request = Request::fromGlobals();
    $controller = new UserController();

    $response = $controller->show($request);

    $this->assertEquals(404, $response->getStatusCode());

    $body = json_decode($response->getBody(), true);
    $this->assertEquals('/problems/not-found', $body['type']);
}
```

## Middleware Testing

### Testing Individual Middleware

```php
class CorsTest extends TestCase
{
    private Cors $corsMiddleware;

    protected function setUp(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://app.example.com,https://admin.example.com';
        $_ENV['CORS_ALLOW_METHODS'] = 'GET,POST,PUT,DELETE,OPTIONS';
        $_ENV['CORS_ALLOW_HEADERS'] = 'Authorization,Content-Type';
        $_ENV['CORS_MAX_AGE'] = '600';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

        $this->corsMiddleware = new Cors();
    }

    public function test_preflight_request_with_allowed_origin(): void
    {
        $request = $this->createRequest('OPTIONS', '/api/users', [
            'origin' => 'https://app.example.com',
            'access-control-request-method' => 'POST',
            'access-control-request-headers' => 'Authorization,Content-Type'
        ]);

        $response = $this->corsMiddleware->handle($request, function () {
            return Response::json(['test' => 'data']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('https://app.example.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('POST', $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('600', $response->getHeader('access-control-max-age'));
    }

    public function test_actual_request_with_cors_headers(): void
    {
        $request = $this->createRequest('GET', '/api/users', [
            'origin' => 'https://app.example.com'
        ]);

        $response = $this->corsMiddleware->handle($request, function () {
            return Response::json(['users' => []]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://app.example.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
    }
}
```

### Testing Rate Limiting

```php
class RateLimiterMiddlewareTest extends TestCase
{
    private RateLimiter $middleware;
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new MemoryStore(); // Test implementation
        $this->middleware = new RateLimiter($this->store);
    }

    public function test_allows_requests_under_limit(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        $next = function (Request $req): Response {
            return Response::json(['success' => true]);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('60', $response->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('59', $response->getHeader('X-RateLimit-Remaining'));
    }

    public function test_blocks_requests_over_limit(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        $next = function (Request $req): Response {
            return Response::json(['success' => true]);
        };

        // Exhaust the rate limit
        for ($i = 0; $i < 60; $i++) {
            $this->middleware->handle($request, $next);
        }

        // This request should be rate limited
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertNotNull($response->getHeader('Retry-After'));

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/too-many-requests', $body['type']);
    }
}
```

## Authentication Testing

### Testing JWT Token Verification

```php
class TokenTest extends TestCase
{
    private array $testKeys;

    protected function setUp(): void
    {
        // Generate test RSA key pair
        $this->testKeys = $this->generateTestKeys();

        $_ENV['JWT_KEYS'] = json_encode([
            'current' => [
                'kid' => 'test-key',
                'private_key' => $this->testKeys['private'],
                'public_key' => $this->testKeys['public']
            ]
        ]);
        $_ENV['JWT_KID'] = 'test-key';
    }

    public function test_can_generate_and_verify_token(): void
    {
        $claims = [
            'sub' => 'user123',
            'scopes' => 'read:users,write:users',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $token = Token::generate($claims);
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token); // JWT format

        $verifiedClaims = Token::verify($token);
        $this->assertEquals('user123', $verifiedClaims['sub']);
        $this->assertEquals('read:users,write:users', $verifiedClaims['scopes']);
    }

    public function test_expired_token_throws_exception(): void
    {
        $expiredClaims = [
            'sub' => 'user123',
            'exp' => time() - 3600, // Expired 1 hour ago
            'iat' => time() - 7200,
        ];

        $token = Token::generate($expiredClaims);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');

        Token::verify($token);
    }
}
```

### Testing Authentication Middleware

```php
class AuthBearerTest extends TestCase
{
    private AuthBearer $middleware;

    protected function setUp(): void
    {
        $this->setupJWTKeys();
        $this->middleware = new AuthBearer();
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $request = $this->createRequest('GET', '/protected');

        $response = $this->middleware->handle($request, function () {
            return Response::json(['protected' => 'data']);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Bearer realm="API"', $response->getHeader('WWW-Authenticate'));

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/unauthorized', $body['type']);
    }

    public function test_valid_token_allows_access(): void
    {
        $token = $this->generateValidToken(['sub' => 'user123']);
        $request = $this->createRequest('GET', '/protected', [
            'Authorization' => "Bearer {$token}"
        ]);

        $response = $this->middleware->handle($request, function (Request $req) {
            // Verify claims were attached
            $this->assertEquals('user123', $req->claim('sub'));
            return Response::json(['protected' => 'data']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## Error Handling Testing

### Testing Error Response Formats

```php
class ErrorHandlerTest extends TestCase
{
    public function test_production_mode_hides_sensitive_information(): void
    {
        $_ENV['APP_DEBUG'] = 'false';

        $exception = new Exception('Sensitive database error: password123', 123);

        $response = $this->errorHandler->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('An unexpected error occurred', $body['detail']);
        $this->assertArrayNotHasKey('debug', $body);

        // Ensure sensitive information is not leaked
        $bodyString = $response->getBody();
        $this->assertStringNotContainsString('password123', $bodyString);
        $this->assertStringNotContainsString(__FILE__, $bodyString);
    }

    public function test_validation_exception_handling(): void
    {
        $errors = ['email' => ['Email is required']];
        $problemResponse = Problem::validation($errors);
        $validationException = new ValidationException($problemResponse);

        $response = $this->errorHandler->handle($this->request, function () use ($validationException) {
            throw $validationException;
        });

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/validation', $body['type']);
        $this->assertEquals($errors, $body['errors']);
        $this->assertEquals('/test/path', $body['instance']);
    }
}
```

## Performance Testing

### Response Time Testing

```php
class PerformanceTest extends TestCase
{
    public function test_response_time_within_acceptable_limits(): void
    {
        $startTime = microtime(true);

        $response = $this->makeRequest('GET', '/api/users');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(100, $responseTime, 'Response time should be under 100ms');
    }

    public function test_memory_usage_within_limits(): void
    {
        $startMemory = memory_get_usage(true);

        $response = $this->makeRequest('GET', '/api/users');

        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        $this->assertLessThan(5, $memoryUsed, 'Memory usage should be under 5MB per request');
    }
}
```

### Load Testing Simulation

```php
public function test_concurrent_request_handling(): void
{
    $requests = 100;
    $startTime = microtime(true);

    for ($i = 0; $i < $requests; $i++) {
        $response = $this->makeRequest('GET', '/health');
        $this->assertEquals(200, $response->getStatusCode());
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    $requestsPerSecond = $requests / $totalTime;

    $this->assertGreaterThan(500, $requestsPerSecond, 'Should handle at least 500 requests per second');
}
```

## Test Utilities and Helpers

### Request Creation Helpers

```php
trait TestHelpers
{
    protected function createRequest(string $method, string $uri, array $headers = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::fromGlobals();
    }

    protected function createJsonRequest(string $method, string $uri, array $data): Request
    {
        $request = $this->createRequest($method, $uri, [
            'content-type' => 'application/json'
        ]);

        return $request->setJsonData($data);
    }

    protected function generateTestToken(array $claims = []): string
    {
        $defaultClaims = [
            'sub' => 'test-user',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        return Token::generate(array_merge($defaultClaims, $claims));
    }
}
```

### Database Helpers

```php
trait DatabaseTestHelpers
{
    protected function createTestTables(): void
    {
        DB::execute("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            scopes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    protected function seedTestData(): void
    {
        $users = [
            ['Admin User', 'admin@example.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin:users,admin:system'],
            ['Regular User', 'user@example.com', password_hash('user123', PASSWORD_DEFAULT), 'read:users'],
        ];

        foreach ($users as $user) {
            DB::execute(
                'INSERT INTO users (name, email, password, scopes) VALUES (?, ?, ?, ?)',
                $user
            );
        }
    }
}
```

### Assertion Helpers

```php
trait AssertionHelpers
{
    protected function assertJsonResponse(Response $response, int $expectedStatus = 200): array
    {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);
        $this->assertIsArray($body, 'Response body should be valid JSON');

        return $body;
    }

    protected function assertProblemResponse(Response $response, int $expectedStatus, string $expectedType): array
    {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);
        $this->assertEquals($expectedType, $body['type']);
        $this->assertEquals($expectedStatus, $body['status']);

        return $body;
    }

    protected function assertRateLimitHeaders(Response $response): void
    {
        $this->assertNotNull($response->getHeader('X-RateLimit-Limit'));
        $this->assertNotNull($response->getHeader('X-RateLimit-Remaining'));
        $this->assertNotNull($response->getHeader('X-RateLimit-Reset'));
    }
}
```

## Testing Best Practices

### Test Organization

1. **One Class per Test File**: Each test file focuses on one class or component
2. **Descriptive Test Names**: Use method names that describe what is being tested
3. **Arrange-Act-Assert**: Structure tests with clear setup, execution, and verification phases
4. **Test Independence**: Each test should be independent and not rely on other tests

### Test Data Management

```php
public function test_user_creation_with_various_inputs(): void
{
    $testCases = [
        ['John Doe', 'john@example.com', 'password123', true],
        ['', 'jane@example.com', 'password123', false], // Empty name
        ['Jane Doe', 'invalid-email', 'password123', false], // Invalid email
        ['Bob Smith', 'bob@example.com', '123', false], // Short password
    ];

    foreach ($testCases as [$name, $email, $password, $shouldSucceed]) {
        $response = $this->createUser(['name' => $name, 'email' => $email, 'password' => $password]);

        if ($shouldSucceed) {
            $this->assertEquals(201, $response->getStatusCode());
        } else {
            $this->assertEquals(422, $response->getStatusCode());
        }
    }
}
```

### Environment Isolation

```php
protected function setUp(): void
{
    // Save current environment
    $this->originalEnvironment = $_ENV;

    // Set test-specific environment
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['APP_DEBUG'] = 'true';

    // Reset any global state
    DB::resetConnection();
}

protected function tearDown(): void
{
    // Restore original environment
    $_ENV = $this->originalEnvironment;

    // Clean up test data
    $this->cleanupTestData();
}
```

### Continuous Integration

The test suite is designed to run efficiently in CI environments:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Run tests with verbose output
./vendor/bin/phpunit --testdox
```

### Test Coverage Goals

- **Unit Tests**: 90%+ coverage for individual classes
- **Integration Tests**: Cover all major user journeys
- **Edge Cases**: Test error conditions and boundary cases
- **Performance Tests**: Verify response times and resource usage
