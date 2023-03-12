<?php

declare(strict_types=1);

use App\Middleware\RequireScopes;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use PHPUnit\Framework\TestCase;

class RequireScopesTest extends TestCase
{
    public function test_authenticated_request_with_valid_scopes_passes(): void
    {
        $middleware = new RequireScopes('users.read');
        $request = $this->createAuthenticatedRequest(['users.read', 'users.write']);

        $nextCalled = false;
        $next = function (Request $r) use (&$nextCalled) {
            $nextCalled = true;
            return Response::json(['success' => true]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_authenticated_request_with_multiple_required_scopes_passes(): void
    {
        $middleware = new RequireScopes('users.read,users.write');
        $request = $this->createAuthenticatedRequest(['users.read', 'users.write', 'admin']);

        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
            return Response::json(['success' => true]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_authenticated_request_missing_required_scope_returns_403(): void
    {
        $middleware = new RequireScopes('users.write');
        $request = $this->createAuthenticatedRequest(['users.read']); // Missing users.write

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertStringContainsString('application/problem+json', $headers['content-type']);
        $this->assertStringContainsString("Required scope 'users.write' is missing", $response->getBody());
    }

    public function test_authenticated_request_with_missing_multiple_scopes_returns_403(): void
    {
        $middleware = new RequireScopes('users.read,users.write,admin');
        $request = $this->createAuthenticatedRequest(['users.read']); // Missing users.write and admin

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString("Required scope 'users.write' is missing", $response->getBody());
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $middleware = new RequireScopes('users.read');
        $request = $this->createUnauthenticatedRequest();

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals('Bearer realm="API"', $headers['www-authenticate']);
        $this->assertStringContainsString('This endpoint requires authentication', $response->getBody());
    }

    public function test_authenticated_request_with_invalid_scopes_returns_403(): void
    {
        $middleware = new RequireScopes('users.read');
        $request = $this->createAuthenticatedRequestWithInvalidScopes();

        $next = function () {
            $this->fail('Next middleware should not be called');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Token does not contain valid scopes', $response->getBody());
    }

    public function test_static_check_method_creates_instance(): void
    {
        $middleware = RequireScopes::check('users.read,users.write');

        $this->assertInstanceOf(RequireScopes::class, $middleware);

        // Test that it works as expected
        $request = $this->createAuthenticatedRequest(['users.read', 'users.write']);

        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
            return Response::json(['success' => true]);
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createAuthenticatedRequest(array $scopes): Request
    {
        $request = $this->createBasicRequest();

        // Set claims to simulate authenticated request
        $claims = [
            'sub' => '123',
            'scopes' => $scopes,
            'iat' => time(),
            'exp' => time() + 900
        ];

        $request->setClaims($claims);

        return $request;
    }

    private function createAuthenticatedRequestWithInvalidScopes(): Request
    {
        $request = $this->createBasicRequest();

        // Set invalid scopes (not an array)
        $claims = [
            'sub' => '123',
            'scopes' => 'invalid-not-an-array',
            'iat' => time(),
            'exp' => time() + 900
        ];

        $request->setClaims($claims);

        return $request;
    }

    private function createUnauthenticatedRequest(): Request
    {
        return $this->createBasicRequest();
        // No claims set, so isAuthenticated() will return false
    }

    private function createBasicRequest(): Request
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
        $headersProperty->setValue($request, []);

        $queryProperty = $reflection->getProperty('query');
        $queryProperty->setAccessible(true);
        $queryProperty->setValue($request, []);

        $jsonProperty = $reflection->getProperty('json');
        $jsonProperty->setAccessible(true);
        $jsonProperty->setValue($request, null);

        return $request;
    }
}
