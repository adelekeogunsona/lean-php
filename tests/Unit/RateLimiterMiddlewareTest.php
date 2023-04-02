<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Middleware\RateLimiter;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\RateLimit\FileStore;

class RateLimiterMiddlewareTest extends TestCase
{
    private string $tempDir;
    private FileStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/leanphp_middleware_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->store = new FileStore($this->tempDir);

        // Set test environment variables
        $_ENV['RATE_LIMIT_DEFAULT'] = '3';
        $_ENV['RATE_LIMIT_WINDOW'] = '60';
        $_ENV['RATE_LIMIT_STORE'] = 'file';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Clean up env vars
        unset($_ENV['RATE_LIMIT_DEFAULT']);
        unset($_ENV['RATE_LIMIT_WINDOW']);
        unset($_ENV['RATE_LIMIT_STORE']);
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        $middleware = new RateLimiter($this->store);
        $request = $this->createMockRequest();

        $nextCalled = false;
        $next = function (Request $req) use (&$nextCalled): Response {
            $nextCalled = true;
            return Response::json(['success' => true]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('3', $response->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('2', $response->getHeader('X-RateLimit-Remaining')); // After 1 hit
        $this->assertIsString($response->getHeader('X-RateLimit-Reset'));
    }

    public function testBlocksRequestsOverLimit(): void
    {
        $middleware = new RateLimiter($this->store);
        $request = $this->createMockRequest();

        $next = function (Request $req): Response {
            return Response::json(['success' => true]);
        };

        // Make requests up to the limit
        for ($i = 0; $i < 3; $i++) {
            $response = $middleware->handle($request, $next);
            if ($i < 2) {
                $this->assertEquals(200, $response->getStatusCode());
            }
        }

        // Next request should be rate limited
        $response = $middleware->handle($request, $next);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));
        $this->assertEquals('3', $response->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('0', $response->getHeader('X-RateLimit-Remaining'));
        $this->assertIsString($response->getHeader('Retry-After'));
    }

    public function testUsesUserIdWhenAuthenticated(): void
    {
        $middleware = new RateLimiter($this->store);
        $request = $this->createMockRequest();

        // Mock authenticated request
        $request->setClaims(['sub' => 'user-123', 'scopes' => ['users.read']]);

        $nextCalled = false;
        $next = function (Request $req) use (&$nextCalled): Response {
            $nextCalled = true;
            return Response::json(['success' => true]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify that rate limiting is applied per user
        // Create a different unauthenticated request (different IP)
        $anotherRequest = $this->createMockRequest('192.168.1.2');

        $response2 = $middleware->handle($anotherRequest, $next);
        $this->assertEquals(200, $response2->getStatusCode());
        // Should have full remaining limit since it's a different key
        $this->assertEquals('2', $response2->getHeader('X-RateLimit-Remaining'));
    }

    public function testUsesIpWhenNotAuthenticated(): void
    {
        $middleware = new RateLimiter($this->store);
        $request = $this->createMockRequest('192.168.1.100');

        $next = function (Request $req): Response {
            return Response::json(['success' => true]);
        };

        // Make multiple requests from the same IP
        for ($i = 0; $i < 3; $i++) {
            $response = $middleware->handle($request, $next);
            if ($i < 2) {
                $this->assertEquals(200, $response->getStatusCode());
            }
        }

        // Should be rate limited
        $response = $middleware->handle($request, $next);
        $this->assertEquals(429, $response->getStatusCode());
    }

    private function createMockRequest(string $ip = '127.0.0.1'): Request
    {
        // Mock the server globals
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REMOTE_ADDR'] = $ip;

        return Request::fromGlobals();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

