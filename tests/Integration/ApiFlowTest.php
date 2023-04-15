<?php

declare(strict_types=1);

namespace Tests\Integration;

use LeanPHP\Routing\Router;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\ResponseEmitter;
use LeanPHP\Logging\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests covering complete API request flows.
 * Tests the full middleware pipeline and authentication workflows.
 */
class ApiFlowTest extends TestCase
{
    private Router $router;
    private array $globalMiddleware;
    private string $testToken = '';
    private string $testTokenWithLimitedScopes = '';

    public static function setUpBeforeClass(): void
    {
        // Set up test environment
        $_ENV['APP_ENV'] = 'development';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['AUTH_JWT_CURRENT_KID'] = 'test';
        $_ENV['AUTH_JWT_KEYS'] = 'test:dGVzdC1rZXktZm9yLWp3dC1zaWduaW5nLTEyMzQ1Njc4OTA';
        $_ENV['AUTH_TOKEN_TTL'] = '3600';
        $_ENV['RATE_LIMIT_STORE'] = 'file';
        $_ENV['RATE_LIMIT_DEFAULT'] = '5'; // Low limit for testing
        $_ENV['RATE_LIMIT_WINDOW'] = '60';
        $_ENV['CORS_ALLOW_ORIGINS'] = '*';
        $_ENV['LOG_PATH'] = 'storage/logs/test.log';
    }

    protected function setUp(): void
    {
        // Set up database for each test
        $this->setupTestDatabase();

        // Set up router and routes like the real application
        $this->setupRouter();

        // Generate test tokens
        $this->generateTestTokens();
    }

    protected function tearDown(): void
    {
        // Clean up environment
        unset(
            $_ENV['APP_ENV'],
            $_ENV['APP_DEBUG'],
            $_ENV['AUTH_JWT_CURRENT_KID'],
            $_ENV['AUTH_JWT_KEYS'],
            $_ENV['AUTH_TOKEN_TTL'],
            $_ENV['RATE_LIMIT_STORE'],
            $_ENV['RATE_LIMIT_DEFAULT'],
            $_ENV['RATE_LIMIT_WINDOW'],
            $_ENV['CORS_ALLOW_ORIGINS'],
            $_ENV['LOG_PATH'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_CONTENT_TYPE'],
            $_SERVER['HTTP_CONTENT_LENGTH'],
            $_SERVER['HTTP_IF_NONE_MATCH'],
            $_SERVER['HTTP_ORIGIN']
        );

        // Clean up test database files
        $testDbFiles = glob('storage/test_database_*.sqlite');
        if ($testDbFiles !== false) {
            foreach ($testDbFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        // Clean up rate limit files
        $rateLimitFiles = glob('storage/ratelimit/*');
        if ($rateLimitFiles !== false) {
            foreach ($rateLimitFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Test complete authentication and authorization flow.
     */
    public function test_complete_authentication_flow(): void
    {
        // Step 1: Login (should succeed)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '100';

        $request = Request::fromGlobals();
        $request->setJsonBody(['email' => 'demo@example.com', 'password' => 'secret']);

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('content-type'));

        $loginData = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('token', $loginData);
        $this->assertArrayHasKey('token_type', $loginData);
        $this->assertEquals('Bearer', $loginData['token_type']);

        $token = $loginData['token'];
        $this->assertNotEmpty($token);

        // Step 2: Use token for authorized request (should succeed)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/v1/users';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_LENGTH']);

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $userData = json_decode($response->getBody(), true);
        $this->assertIsArray($userData);
    }

    /**
     * Test ETag and conditional request flow (304 response).
     */
    public function test_etag_conditional_request_flow(): void
    {
        // Step 1: Make initial request to get ETag
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/v1/users/1';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $etag = $response->getHeader('etag');
        $this->assertNotNull($etag);

        // Step 2: Make conditional request with If-None-Match (should get 304)
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals('', $response->getBody()); // No body for 304
        $this->assertEquals($etag, $response->getHeader('etag'));
    }

    /**
     * Test rate limiting flow (429 response).
     */
    public function test_rate_limiting_flow(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/v1/users';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;

        // Make requests up to the limit (5 requests)
        for ($i = 0; $i < 5; $i++) {
            $request = Request::fromGlobals();
            $response = $this->router->dispatch($request);
            $this->assertEquals(200, $response->getStatusCode(), "Request {$i} should succeed");
        }

        // The 6th request should be rate limited
        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        // Check rate limiting headers
        $this->assertNotNull($response->getHeader('retry-after'));
        $this->assertEquals('5', $response->getHeader('x-ratelimit-limit'));
        $this->assertEquals('0', $response->getHeader('x-ratelimit-remaining'));

        $errorData = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/too-many-requests', $errorData['type']);
        $this->assertEquals('Too Many Requests', $errorData['title']);
    }

    /**
     * Test scope authorization failure (403 response).
     */
    public function test_scope_authorization_failure_flow(): void
    {
        // Try to access admin endpoint with limited scope token
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/v1/users/1';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testTokenWithLimitedScopes;

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $errorData = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/forbidden', $errorData['type']);
        $this->assertEquals('Forbidden', $errorData['title']);
        $this->assertStringContainsString('scope', strtolower($errorData['detail']));
    }

    /**
     * Test validation error flow (422 response).
     */
    public function test_validation_error_flow(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/v1/users';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '100';

        $request = Request::fromGlobals();
        // Invalid data: empty name, invalid email, weak password
        $request->setJsonBody([
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123'
        ]);

        $response = $this->router->dispatch($request);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $errorData = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/validation', $errorData['type']);
        $this->assertEquals('Unprocessable Content', $errorData['title']);
        $this->assertEquals('/v1/users', $errorData['instance']);
        $this->assertArrayHasKey('errors', $errorData);
        $this->assertIsArray($errorData['errors']);
    }

    /**
     * Test unauthorized request flow (401 response).
     */
    public function test_unauthorized_request_flow(): void
    {
        // Step 1: Request without token (should get 401)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/v1/users';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));
        $this->assertEquals('Bearer realm="API"', $response->getHeader('www-authenticate'));

        // Step 2: Request with invalid token (should get 401)
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token-here';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Bearer realm="API"', $response->getHeader('www-authenticate'));
    }

    /**
     * Test CORS preflight flow.
     */
    public function test_cors_preflight_flow(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/v1/users';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization,Content-Type';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeader('access-control-allow-origin'));
        $this->assertNotNull($response->getHeader('access-control-allow-methods'));
        $this->assertNotNull($response->getHeader('access-control-allow-headers'));
        $this->assertEquals('Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
                           $response->getHeader('vary'));
    }

    /**
     * Test complete error scenario with production settings.
     */
    public function test_production_error_handling(): void
    {
        // Switch to production mode
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/error-endpoint'; // Non-existent endpoint

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $errorData = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/not-found', $errorData['type']);
        $this->assertEquals('Not Found', $errorData['title']);
        $this->assertEquals('/error-endpoint', $errorData['instance']);

        // In production, should not have debug info
        $this->assertArrayNotHasKey('debug', $errorData);
    }

    /**
     * Test JSON body parsing and content type validation.
     */
    public function test_json_body_parsing_flow(): void
    {
        // Test 1: Invalid content type (should get 415)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/plain';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '50';

        $request = Request::fromGlobals();
        $response = $this->router->dispatch($request);

        $this->assertEquals(415, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        // Test 2: Valid JSON content type (should work)
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

        $request = Request::fromGlobals();
        $request->setJsonBody(['email' => 'demo@example.com', 'password' => 'secret']);
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Set up test database with demo data.
     */
    private function setupTestDatabase(): void
    {
        // Create a unique database file for this test run
        $dbPath = 'storage/test_database_' . uniqid() . '.sqlite';

        // Ensure storage directory exists
        if (!is_dir('storage')) {
            mkdir('storage', 0755, true);
        }

        try {
            $pdo = new \PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Create users table
            $pdo->exec('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    scopes TEXT DEFAULT "users.read",
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ');

            // Insert demo user
            $passwordHash = password_hash('secret', PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $stmt->execute(['Demo User', 'demo@example.com', $passwordHash]);
            $stmt->execute(['Test User', 'test@example.com', $passwordHash]);

            // Update DB configuration to use test database
            $_ENV['DB_DSN'] = "sqlite:{$dbPath}";
        } catch (\Exception $e) {
            throw new \Exception("Failed to setup test database: " . $e->getMessage());
        }
    }

    /**
     * Set up router with all middleware and routes.
     */
    private function setupRouter(): void
    {
        $this->router = new Router();

        // Set global middleware
        $this->globalMiddleware = [
            \App\Middleware\ErrorHandler::class,
            \App\Middleware\RequestId::class,
            \App\Middleware\Cors::class,
            \App\Middleware\JsonBodyParser::class,
        ];
        $this->router->setGlobalMiddleware($this->globalMiddleware);

        // Add routes like the real application
        $this->router->get('/health', [\App\Controllers\HealthController::class, 'index']);

        $this->router->post('/login', [\App\Controllers\AuthController::class, 'login']);

        $this->router->group('/v1', ['middleware' => [\App\Middleware\ETag::class]], function ($router) {
            $router->get('/users', [\App\Controllers\UserController::class, 'index'], [
                \App\Middleware\AuthBearer::class,
                new \App\Middleware\RequireScopes('users.read'),
                \App\Middleware\RateLimiter::class,
            ]);

            $router->get('/users/{id:\d+}', [\App\Controllers\UserController::class, 'show'], [
                \App\Middleware\AuthBearer::class,
                new \App\Middleware\RequireScopes('users.read'),
                \App\Middleware\RateLimiter::class,
            ]);

            $router->post('/users', [\App\Controllers\UserController::class, 'create'], [
                \App\Middleware\AuthBearer::class,
                new \App\Middleware\RequireScopes('users.write'),
                \App\Middleware\RateLimiter::class,
            ]);

            // Add DELETE route for testing scope authorization failure
            $router->delete('/users/{id:\d+}', function() {
                return \LeanPHP\Http\Response::noContent();
            }, [
                \App\Middleware\AuthBearer::class,
                new \App\Middleware\RequireScopes('users.write,users.delete'),
            ]);
        });
    }

    /**
     * Generate test tokens with different scopes.
     */
    private function generateTestTokens(): void
    {
        // Full access token
        $this->testToken = \LeanPHP\Auth\Token::issue([
            'sub' => 1,
            'scopes' => ['users.read', 'users.write', 'users.delete']
        ], 3600);

        // Limited scope token (only read access)
        $this->testTokenWithLimitedScopes = \LeanPHP\Auth\Token::issue([
            'sub' => 2,
            'scopes' => ['users.read']
        ], 3600);
    }
}
