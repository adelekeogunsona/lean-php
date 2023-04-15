<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\JsonBodyParser;
use LeanPHP\Http\Request;
use LeanPHP\Logging\Logger;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private string $tempLogFile;

    protected function setUp(): void
    {
        $this->tempLogFile = tempnam(sys_get_temp_dir(), 'test_log');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }

        // Clean up $_SERVER
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_CONTENT_TYPE'],
            $_SERVER['HTTP_CONTENT_LENGTH']
        );
    }

    public function test_logger_redacts_authorization_headers(): void
    {
        $logger = new Logger($this->tempLogFile);

        $sensitiveContext = [
            'authorization' => 'Bearer secret-token-123',
            'Authorization' => 'Bearer another-token',
            'AUTHORIZATION' => 'Basic dXNlcjpwYXNz',
            'user_id' => 123,
            'action' => 'login',
        ];

        $logger->info('Test message', $sensitiveContext);

        $logContent = file_get_contents($this->tempLogFile) ?: '';

        // Ensure authorization headers are redacted
        $this->assertStringContainsString('[REDACTED]', $logContent);
        $this->assertStringNotContainsString('secret-token-123', $logContent);
        $this->assertStringNotContainsString('another-token', $logContent);
        $this->assertStringNotContainsString('dXNlcjpwYXNz', $logContent);

        // Ensure non-sensitive data is still logged
        $this->assertStringContainsString('123', $logContent); // user_id
        $this->assertStringContainsString('login', $logContent); // action
        $this->assertStringContainsString('Test message', $logContent);
    }

    public function test_logger_redacts_bearer_tokens(): void
    {
        $logger = new Logger($this->tempLogFile);

        $context = [
            'bearer' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
            'bearer_token' => 'another-secret-token',
            'token' => 'yet-another-token',
        ];

        $logger->error('Token validation failed', $context);

        $logContent = file_get_contents($this->tempLogFile) ?: '';

        // All tokens should be redacted
        $this->assertStringNotContainsString('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9', $logContent);
        $this->assertStringNotContainsString('another-secret-token', $logContent);
        $this->assertStringNotContainsString('yet-another-token', $logContent);

        // Should contain redacted placeholders
        $this->assertStringContainsString('[REDACTED]', $logContent);
        $this->assertStringContainsString('Token validation failed', $logContent);
    }

    public function test_logger_redacts_passwords_and_secrets(): void
    {
        $logger = new Logger($this->tempLogFile);

        $context = [
            'password' => 'super-secret-password',
            'user_password' => 'another-password',
            'secret' => 'my-secret-key',
            'api_secret' => 'api-secret-value',
            'key' => 'encryption-key',
            'private_key' => 'private-key-data',
            'username' => 'john_doe', // This should NOT be redacted
        ];

        $logger->warning('Authentication attempt', $context);

        $logContent = file_get_contents($this->tempLogFile) ?: '';

        // Passwords and secrets should be redacted
        $this->assertStringNotContainsString('super-secret-password', $logContent);
        $this->assertStringNotContainsString('another-password', $logContent);
        $this->assertStringNotContainsString('my-secret-key', $logContent);
        $this->assertStringNotContainsString('api-secret-value', $logContent);
        $this->assertStringNotContainsString('encryption-key', $logContent);
        $this->assertStringNotContainsString('private-key-data', $logContent);

        // Username should remain
        $this->assertStringContainsString('john_doe', $logContent);
        $this->assertStringContainsString('[REDACTED]', $logContent);
    }

    public function test_logger_redacts_nested_sensitive_data(): void
    {
        $logger = new Logger($this->tempLogFile);

        $context = [
            'user' => [
                'id' => 123,
                'name' => 'John',
                'password' => 'secret-password',
            ],
            'request' => [
                'headers' => [
                    'authorization' => 'Bearer token123',
                    'content-type' => 'application/json',
                ],
                'body' => [
                    'action' => 'login',
                    'secret' => 'nested-secret',
                ],
            ],
        ];

        $logger->debug('Request processing', $context);

        $logContent = file_get_contents($this->tempLogFile) ?: '';

        // Nested sensitive data should be redacted
        $this->assertStringNotContainsString('secret-password', $logContent);
        $this->assertStringNotContainsString('Bearer token123', $logContent);
        $this->assertStringNotContainsString('nested-secret', $logContent);

        // Non-sensitive nested data should remain
        $this->assertStringContainsString('123', $logContent); // user id
        $this->assertStringContainsString('John', $logContent); // user name
        $this->assertStringContainsString('application/json', $logContent); // content type
        $this->assertStringContainsString('login', $logContent); // action
        $this->assertStringContainsString('[REDACTED]', $logContent);
    }

    public function test_json_body_parser_rejects_non_json_for_post(): void
    {
        $parser = new JsonBodyParser();

        // Mock POST request with wrong content type
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/plain';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '10';

        $request = Request::fromGlobals();

        $response = $parser->handle($request, function () {
            return \LeanPHP\Http\Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(415, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('Unsupported Media Type', $body['title']);
        $this->assertStringContainsString('application/json', $body['detail']);
    }

    public function test_json_body_parser_rejects_non_json_for_put(): void
    {
        $parser = new JsonBodyParser();

        // Mock PUT request with XML content
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/xml';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '50';

        $request = Request::fromGlobals();

        $response = $parser->handle($request, function () {
            return \LeanPHP\Http\Response::json(['should' => 'not reach here']);
        });

        $this->assertEquals(415, $response->getStatusCode());
    }

    public function test_json_body_parser_allows_get_without_json(): void
    {
        $parser = new JsonBodyParser();

        // Mock GET request without content type (normal for GET)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        unset($_SERVER['HTTP_CONTENT_TYPE']);
        unset($_SERVER['HTTP_CONTENT_LENGTH']);

        $request = Request::fromGlobals();

        $expectedResponse = \LeanPHP\Http\Response::json(['data' => 'test']);

        $response = $parser->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

    public function test_json_body_parser_allows_options_without_json(): void
    {
        $parser = new JsonBodyParser();

        // Mock OPTIONS request (for CORS preflight)
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';

        $request = Request::fromGlobals();

        $expectedResponse = \LeanPHP\Http\Response::noContent();

        $response = $parser->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

    public function test_json_body_parser_allows_delete_without_json(): void
    {
        $parser = new JsonBodyParser();

        // Mock DELETE request without body
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/api/test';

        $request = Request::fromGlobals();

        $expectedResponse = \LeanPHP\Http\Response::noContent();

        $response = $parser->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

    public function test_json_body_parser_allows_post_with_valid_json(): void
    {
        $parser = new JsonBodyParser();

        // Mock POST request with valid JSON
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '20';

        $request = Request::fromGlobals();

        $expectedResponse = \LeanPHP\Http\Response::json(['created' => true]);

        $response = $parser->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

    public function test_json_body_parser_allows_post_without_body(): void
    {
        $parser = new JsonBodyParser();

        // Mock POST request without body (Content-Length: 0)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_CONTENT_LENGTH'] = '0';

        $request = Request::fromGlobals();

        $expectedResponse = \LeanPHP\Http\Response::json(['accepted' => true]);

        $response = $parser->handle($request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
    }

}
