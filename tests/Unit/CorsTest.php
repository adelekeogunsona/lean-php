<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\Cors;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use PHPUnit\Framework\TestCase;

class CorsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up environment variables
        unset(
            $_ENV['CORS_ALLOW_ORIGINS'],
            $_ENV['CORS_ALLOW_METHODS'],
            $_ENV['CORS_ALLOW_HEADERS'],
            $_ENV['CORS_MAX_AGE'],
            $_ENV['CORS_ALLOW_CREDENTIALS'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_ORIGIN'],
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'],
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']
        );
    }

    public function test_preflight_request_with_allowed_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com,https://app.example.com';
        $_ENV['CORS_ALLOW_METHODS'] = 'GET,POST,PUT,DELETE';
        $_ENV['CORS_ALLOW_HEADERS'] = 'Authorization,Content-Type,X-Custom';
        $_ENV['CORS_MAX_AGE'] = '3600';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization,Content-Type';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('GET, POST, PUT, DELETE', $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('Authorization, Content-Type, X-Custom', $response->getHeader('access-control-allow-headers'));
        $this->assertEquals('3600', $response->getHeader('access-control-max-age'));

        // Check Vary header for preflight
        $this->assertEquals('Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
                           $response->getHeader('vary'));
    }

    public function test_preflight_request_with_disallowed_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://allowed.com';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        // No CORS headers should be set for disallowed origins
        $this->assertNull($response->getHeader('access-control-allow-origin'));
        $this->assertNull($response->getHeader('access-control-allow-methods'));
    }

    public function test_preflight_request_with_wildcard_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = '*';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://any-origin.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeader('access-control-allow-origin'));

        // Vary header should still be set
        $this->assertEquals('Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
                           $response->getHeader('vary'));
    }

    public function test_preflight_request_with_credentials_enabled(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://trusted.com';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://trusted.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('https://trusted.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
    }

    public function test_preflight_request_with_wildcard_and_credentials(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = '*';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        // When credentials are enabled, we can't use *, must use specific origin
        $this->assertEquals('https://example.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
    }

    public function test_regular_request_with_allowed_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $expectedResponse = Response::json(['data' => 'test']);

        $response = $cors->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
        $this->assertNull($response->getHeader('access-control-allow-credentials'));
    }

    public function test_regular_request_with_wildcard_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = '*';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://any-domain.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $expectedResponse = Response::json(['success' => true]);

        $response = $cors->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertEquals('*', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
    }

    public function test_regular_request_with_disallowed_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://allowed.com';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://blocked.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $expectedResponse = Response::json(['data' => 'test']);

        $response = $cors->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        // No CORS headers for disallowed origin, but Vary header should still be set
        $this->assertNull($response->getHeader('access-control-allow-origin'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
    }

    public function test_regular_request_without_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        // No HTTP_ORIGIN header

        $request = Request::fromGlobals();
        $cors = new Cors();

        $expectedResponse = Response::json(['data' => 'test']);

        $response = $cors->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        // No origin means no CORS headers, but Vary should still be set
        $this->assertNull($response->getHeader('access-control-allow-origin'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
    }

    public function test_preflight_request_with_disallowed_method(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://example.com';
        $_ENV['CORS_ALLOW_METHODS'] = 'GET,POST';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'DELETE';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        // Method not allowed, so no CORS headers should be set
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull($response->getHeader('access-control-allow-origin'));
    }

    public function test_environment_variable_parsing(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = ' https://one.com , https://two.com ';
        $_ENV['CORS_ALLOW_METHODS'] = ' get , post , put ';
        $_ENV['CORS_ALLOW_HEADERS'] = ' authorization , content-type , x-custom ';

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://one.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        // Check that whitespace is trimmed and methods are uppercased
        $this->assertEquals('https://one.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('GET, POST, PUT', $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('authorization, content-type, x-custom',
                           $response->getHeader('access-control-allow-headers'));
    }

    public function test_default_environment_values(): void
    {
        // Don't set any environment variables, use defaults
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $response = $cors->handle($request, function () {
            return Response::json(['should' => 'not reach here']);
        });

        // Should use default values
        $this->assertEquals('*', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('GET, POST, PUT, PATCH, DELETE, OPTIONS',
                           $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('Authorization, Content-Type',
                           $response->getHeader('access-control-allow-headers'));
        $this->assertEquals('600', $response->getHeader('access-control-max-age'));
        $this->assertNull($response->getHeader('access-control-allow-credentials'));
    }

    public function test_credentials_enabled_with_specific_origin(): void
    {
        $_ENV['CORS_ALLOW_ORIGINS'] = 'https://trusted.com';
        $_ENV['CORS_ALLOW_CREDENTIALS'] = 'true';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'https://trusted.com';

        $request = Request::fromGlobals();
        $cors = new Cors();

        $expectedResponse = Response::json(['data' => 'test']);

        $response = $cors->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertEquals('https://trusted.com', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('true', $response->getHeader('access-control-allow-credentials'));
        $this->assertEquals('Origin', $response->getHeader('vary'));
    }
}
