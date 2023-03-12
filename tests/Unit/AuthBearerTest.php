<?php

declare(strict_types=1);

use App\Middleware\AuthBearer;
use LeanPHP\Auth\Token;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use PHPUnit\Framework\TestCase;

class AuthBearerTest extends TestCase
{
    private AuthBearer $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AuthBearer();

        // Set up test JWT configuration
        putenv('AUTH_JWT_CURRENT_KID=main');
        putenv('AUTH_JWT_KEYS=main:dGVzdC1zZWNyZXQtbWFpbi1rZXk,old:dGVzdC1zZWNyZXQtb2xkLWtleQ');
        putenv('AUTH_TOKEN_TTL=900');
    }

    protected function tearDown(): void
    {
        // Clean up environment
        putenv('AUTH_JWT_CURRENT_KID=');
        putenv('AUTH_JWT_KEYS=');
        putenv('AUTH_TOKEN_TTL=');
    }

    public function test_valid_token_passes_through(): void
    {
        // Create a valid token
        $claims = ['sub' => '123', 'scopes' => ['users.read'], 'email' => 'test@example.com'];
        $token = Token::issue($claims);

        // Create a request with the token
        $request = $this->createRequestWithToken($token);

        // Mock the next middleware
        $nextCalled = false;
        $next = function (Request $r) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertTrue($r->isAuthenticated());
            $this->assertEquals('123', $r->claim('sub'));
            $this->assertEquals(['users.read'], $r->claim('scopes'));
            $this->assertEquals('test@example.com', $r->claim('email'));
            return Response::json(['success' => true]);
        };

        // Handle the request
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $request = $this->createRequestWithoutToken();

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals('Bearer realm="API"', $headers['www-authenticate']);
        $this->assertStringContainsString('application/problem+json', $headers['content-type']);
    }

    public function test_invalid_token_returns_401(): void
    {
        $request = $this->createRequestWithToken('invalid.jwt.token');

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals('Bearer realm="API"', $headers['www-authenticate']);
    }

    public function test_expired_token_returns_401(): void
    {
        // Create an expired token by issuing it with negative TTL
        $claims = ['sub' => '123', 'scopes' => ['users.read']];
        $expiredToken = Token::issue($claims, -1);

        $request = $this->createRequestWithToken($expiredToken);

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertTrue(
            str_contains($body, 'Invalid token') || str_contains($body, 'Token verification failed'),
            "Expected 'Invalid token' or 'Token verification failed' in response body: $body"
        );
    }

    public function test_malformed_authorization_header_returns_401(): void
    {
        $request = $this->createRequestWithCustomHeader('authorization', 'NotBearer token123');

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Missing or invalid Authorization header', $response->getBody());
    }

    private function createRequestWithToken(string $token): Request
    {
        return $this->createRequestWithCustomHeader('authorization', "Bearer $token");
    }

    private function createRequestWithoutToken(): Request
    {
        return $this->createRequestWithCustomHeader('authorization', null);
    }

    private function createRequestWithCustomHeader(string $headerName, ?string $headerValue): Request
    {
        // Create a mock request using reflection since the constructor is private
        $reflection = new \ReflectionClass(Request::class);
        $request = $reflection->newInstanceWithoutConstructor();

        // Set the required properties
        $methodProperty = $reflection->getProperty('method');
        $methodProperty->setAccessible(true);
        $methodProperty->setValue($request, 'GET');

        $pathProperty = $reflection->getProperty('path');
        $pathProperty->setAccessible(true);
        $pathProperty->setValue($request, '/test');

        $headersProperty = $reflection->getProperty('headers');
        $headersProperty->setAccessible(true);
        $headers = $headerValue ? [$headerName => $headerValue] : [];
        $headersProperty->setValue($request, $headers);

        $queryProperty = $reflection->getProperty('query');
        $queryProperty->setAccessible(true);
        $queryProperty->setValue($request, []);

        $jsonProperty = $reflection->getProperty('json');
        $jsonProperty->setAccessible(true);
        $jsonProperty->setValue($request, null);

        return $request;
    }

}
