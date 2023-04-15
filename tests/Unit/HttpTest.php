<?php

declare(strict_types=1);

namespace Tests\Unit;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\ResponseEmitter;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function test_response_json_creates_json_response(): void
    {
        $data = ['message' => 'Hello, World!', 'status' => 'ok'];
        $response = Response::json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['content-type']);
        $this->assertEquals(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $response->getBody());
    }

    public function test_response_text_creates_text_response(): void
    {
        $response = Response::text('Hello, World!', 200);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaders()['content-type']);
        $this->assertEquals('Hello, World!', $response->getBody());
    }

    public function test_response_no_content_creates_204_response(): void
    {
        $response = Response::noContent();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->getBody());
    }

    public function test_response_status_sets_status_code(): void
    {
        $response = Response::json(['test' => true])->status(422);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_response_header_sets_header(): void
    {
        $response = Response::json(['test' => true])
            ->header('X-Custom-Header', 'custom-value')
            ->header('Cache-Control', 'no-cache');

        $headers = $response->getHeaders();
        $this->assertEquals('custom-value', $headers['x-custom-header']);
        $this->assertEquals('no-cache', $headers['cache-control']);
    }

    public function test_request_bearer_token_extracts_from_authorization_header(): void
    {
        // Mock $_SERVER for fromGlobals
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123token';

        $request = Request::fromGlobals();

        $this->assertEquals('abc123token', $request->bearerToken());
    }

    public function test_request_bearer_token_returns_null_without_header(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $request = Request::fromGlobals();

        $this->assertNull($request->bearerToken());
    }

    public function test_request_bearer_token_returns_null_without_bearer_prefix(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $request = Request::fromGlobals();

        $this->assertNull($request->bearerToken());
    }

    public function test_request_from_globals_parses_method_and_path(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/users/123?param=value';

        $request = Request::fromGlobals();

        $this->assertEquals('POST', $request->method());
        $this->assertEquals('/api/users/123', $request->path());
    }

    public function test_request_from_globals_parses_headers(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = Request::fromGlobals();

        $this->assertEquals('application/json', $request->header('content-type'));
        $this->assertEquals('custom-value', $request->header('x-custom-header'));
    }

    public function test_request_from_globals_parses_query_parameters(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_GET['param1'] = 'value1';
        $_GET['param2'] = 'value2';

        $request = Request::fromGlobals();

        $this->assertEquals('value1', $request->query('param1'));
        $this->assertEquals('value2', $request->query('param2'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
    }

    public function test_request_params_can_be_set_and_retrieved(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = Request::fromGlobals();
        $request->setParams(['id' => '123', 'type' => 'user']);

        $this->assertEquals(['id' => '123', 'type' => 'user'], $request->params());
    }

    public function test_response_emitter_should_omit_body_for_no_content_responses(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('shouldOmitBody');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 204));
        $this->assertTrue($method->invoke(null, 304));
        $this->assertTrue($method->invoke(null, 100));
        $this->assertTrue($method->invoke(null, 101));

        $this->assertFalse($method->invoke(null, 200));
        $this->assertFalse($method->invoke(null, 404));
        $this->assertFalse($method->invoke(null, 500));
    }

    public function test_response_emitter_prepare_headers_adds_content_length(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('prepareHeaders');
        $method->setAccessible(true);

        $headers = ['content-type' => 'application/json'];
        $body = '{"message":"test"}';
        $result = $method->invoke(null, $headers, $body, 200);

        $this->assertEquals((string) strlen($body), $result['content-length']);
        $this->assertEquals('application/json', $result['content-type']);
    }

    public function test_response_emitter_prepare_headers_does_not_add_content_length_for_no_body_responses(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('prepareHeaders');
        $method->setAccessible(true);

        $headers = ['content-type' => 'application/json'];
        $body = 'some body';

        // Test 204 No Content
        $result = $method->invoke(null, $headers, $body, 204);
        $this->assertArrayNotHasKey('content-length', $result);

        // Test 304 Not Modified
        $result = $method->invoke(null, $headers, $body, 304);
        $this->assertArrayNotHasKey('content-length', $result);
    }

    public function test_response_emitter_prepare_headers_does_not_override_existing_content_length(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('prepareHeaders');
        $method->setAccessible(true);

        $headers = ['content-type' => 'application/json', 'content-length' => '100'];
        $body = '{"message":"test"}';
        $result = $method->invoke(null, $headers, $body, 200);

        $this->assertEquals('100', $result['content-length']);
    }

    public function test_response_emitter_format_header_name_converts_to_title_case(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('formatHeaderName');
        $method->setAccessible(true);

        $this->assertEquals('Content-Type', $method->invoke(null, 'content-type'));
        $this->assertEquals('X-Custom-Header', $method->invoke(null, 'x-custom-header'));
        $this->assertEquals('Authorization', $method->invoke(null, 'authorization'));
        $this->assertEquals('Cache-Control', $method->invoke(null, 'cache-control'));
        $this->assertEquals('WWW-Authenticate', $method->invoke(null, 'www-authenticate'));
    }

    public function test_response_emitter_sanitize_header_value_removes_control_characters(): void
    {
        $reflection = new \ReflectionClass(ResponseEmitter::class);
        $method = $reflection->getMethod('sanitizeHeaderValue');
        $method->setAccessible(true);

        // Test removal of control characters
        $this->assertEquals('safe-value', $method->invoke(null, "safe-value\x00"));
        $this->assertEquals('safe-value', $method->invoke(null, "safe\x0A-value"));
        $this->assertEquals('safe-value', $method->invoke(null, "safe\x0D-value"));
        $this->assertEquals('safe-value', $method->invoke(null, "safe\x7F-value"));

        // Test normal values remain unchanged
        $this->assertEquals('application/json', $method->invoke(null, 'application/json'));
        $this->assertEquals('Bearer token123', $method->invoke(null, 'Bearer token123'));
    }

    public function test_response_without_body_method(): void
    {
        $response = Response::json(['message' => 'test'])->withoutBody();

        $this->assertEquals('', $response->getBody());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        // Clean up $_SERVER for consistent tests
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_CONTENT_TYPE'],
            $_SERVER['HTTP_X_CUSTOM_HEADER'],
            $_SERVER['CONTENT_TYPE']
        );

        // Clean up $_GET
        $_GET = [];
    }
}

