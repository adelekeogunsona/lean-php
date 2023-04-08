<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Middleware\ETag;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class ETagTest extends TestCase
{
    private ETag $middleware;

    protected function setUp(): void
    {
        $this->middleware = new ETag();
    }

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
        $this->assertGreaterThan(2, strlen($etag)); // More than just quotes
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
        $this->assertEquals('', $response2->getBody()); // No body for 304
        $this->assertNotNull($response2->getHeader('Cache-Control'));
    }

    public function test_handles_multiple_etags_in_if_none_match(): void
    {
        $request1 = $this->createMockRequest('GET', '/test');

        $next = function (Request $req): Response {
            return Response::json(['data' => 'test']);
        };

        $response1 = $this->middleware->handle($request1, $next);
        $etag = $response1->getHeader('ETag');

        // Request with multiple ETags including ours
        $ifNoneMatch = '"old-etag", ' . $etag . ', "another-etag"';
        $request2 = $this->createMockRequest('GET', '/test', ['if-none-match' => $ifNoneMatch]);

        $response2 = $this->middleware->handle($request2, $next);

        $this->assertEquals(304, $response2->getStatusCode());
    }

    public function test_handles_wildcard_if_none_match(): void
    {
        $request = $this->createMockRequest('GET', '/test', ['if-none-match' => '*']);

        $next = function (Request $req): Response {
            return Response::json(['data' => 'test']);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(304, $response->getStatusCode());
    }

    public function test_skips_etag_for_non_get_requests(): void
    {
        $request = $this->createMockRequest('POST', '/test');

        $next = function (Request $req): Response {
            return Response::json(['data' => 'test']);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->getHeader('ETag'));
    }

    public function test_skips_etag_for_non_200_responses(): void
    {
        $request = $this->createMockRequest('GET', '/test');

        $next = function (Request $req): Response {
            return Response::json(['error' => 'Not found'], 404);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertNull($response->getHeader('ETag'));
    }

    public function test_skips_etag_for_non_json_responses(): void
    {
        $request = $this->createMockRequest('GET', '/test');

        $next = function (Request $req): Response {
            return Response::text('Plain text response');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->getHeader('ETag'));
    }

    public function test_skips_etag_for_empty_responses(): void
    {
        $request = $this->createMockRequest('GET', '/test');

        $next = function (Request $req): Response {
            return Response::noContent();
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull($response->getHeader('ETag'));
    }

    public function test_generates_consistent_etag_for_same_content(): void
    {
        $request1 = $this->createMockRequest('GET', '/test');
        $request2 = $this->createMockRequest('GET', '/test');

        $next = function (Request $req): Response {
            return Response::json(['data' => 'test']);
        };

        $response1 = $this->middleware->handle($request1, $next);
        $response2 = $this->middleware->handle($request2, $next);

        $this->assertEquals($response1->getHeader('ETag'), $response2->getHeader('ETag'));
    }

    public function test_generates_different_etag_for_different_content(): void
    {
        $request1 = $this->createMockRequest('GET', '/test');
        $request2 = $this->createMockRequest('GET', '/test');

        $next1 = function (Request $req): Response {
            return Response::json(['data' => 'test1']);
        };

        $next2 = function (Request $req): Response {
            return Response::json(['data' => 'test2']);
        };

        $response1 = $this->middleware->handle($request1, $next1);
        $response2 = $this->middleware->handle($request2, $next2);

        $this->assertNotEquals($response1->getHeader('ETag'), $response2->getHeader('ETag'));
    }

    public function test_proceeds_when_if_none_match_does_not_match(): void
    {
        $request = $this->createMockRequest('GET', '/test', ['if-none-match' => '"different-etag"']);

        $nextCalled = false;
        $next = function (Request $req) use (&$nextCalled): Response {
            $nextCalled = true;
            return Response::json(['data' => 'test']);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->getHeader('ETag'));
        $this->assertNotEquals('"different-etag"', $response->getHeader('ETag'));
    }

    private function createMockRequest(string $method, string $path, array $headers = []): Request
    {
        // Create a mock request using reflection since the constructor is private
        $reflection = new \ReflectionClass(Request::class);
        $request = $reflection->newInstanceWithoutConstructor();

        // Set the required properties
        $methodProperty = $reflection->getProperty('method');
        $methodProperty->setAccessible(true);
        $methodProperty->setValue($request, $method);

        $pathProperty = $reflection->getProperty('path');
        $pathProperty->setAccessible(true);
        $pathProperty->setValue($request, $path);

        $headersProperty = $reflection->getProperty('headers');
        $headersProperty->setAccessible(true);
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
