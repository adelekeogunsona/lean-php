<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\ErrorHandler;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Exception;

class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $errorHandler;
    private Request $request;

    protected function setUp(): void
    {
        $this->errorHandler = new ErrorHandler();

        // Create a mock request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test/path';
        $this->request = Request::fromGlobals();
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        unset($_ENV['APP_DEBUG'], $_SERVER['APP_DEBUG']);
        putenv('APP_DEBUG');
    }

    public function test_debug_mode_shows_exception_details(): void
    {
        $_ENV['APP_DEBUG'] = 'true';

        $exception = new Exception('Test exception message', 123);

        $response = $this->errorHandler->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);

        // Check basic problem format
        $this->assertEquals('/problems/internal-server-error', $body['type']);
        $this->assertEquals('Internal Server Error', $body['title']);
        $this->assertEquals(500, $body['status']);
        $this->assertEquals('Test exception message', $body['detail']);
        $this->assertEquals('/test/path', $body['instance']);

        // Check debug information is present
        $this->assertArrayHasKey('debug', $body);
        $this->assertEquals('Test exception message', $body['debug']['message']);
        $this->assertArrayHasKey('file', $body['debug']);
        $this->assertArrayHasKey('line', $body['debug']);
        $this->assertArrayHasKey('trace', $body['debug']);
        $this->assertEquals('Exception', $body['debug']['class']);
    }

    public function test_production_mode_hides_exception_details(): void
    {
        $_ENV['APP_DEBUG'] = 'false';

        $exception = new Exception('Sensitive internal error message', 123);

        $response = $this->errorHandler->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('content-type'));

        $body = json_decode($response->getBody(), true);

        // Check basic problem format
        $this->assertEquals('/problems/internal-server-error', $body['type']);
        $this->assertEquals('Internal Server Error', $body['title']);
        $this->assertEquals(500, $body['status']);
        $this->assertEquals('An unexpected error occurred', $body['detail']); // Generic message
        $this->assertEquals('/test/path', $body['instance']);

        // Check debug information is NOT present
        $this->assertArrayNotHasKey('debug', $body);

        // Ensure no sensitive information is leaked
        $bodyString = $response->getBody();
        $this->assertStringNotContainsString('Sensitive internal error message', $bodyString);
        $this->assertStringNotContainsString(__FILE__, $bodyString);
    }

    public function test_default_debug_mode_is_production(): void
    {
        // Clear APP_DEBUG completely
        unset($_ENV['APP_DEBUG'], $_SERVER['APP_DEBUG']);
        putenv('APP_DEBUG');

        $exception = new Exception('Should be hidden', 123);

        $response = $this->errorHandler->handle($this->request, function () use ($exception) {
            throw $exception;
        });

        $body = json_decode($response->getBody(), true);

        // Should use generic message
        $this->assertEquals('An unexpected error occurred', $body['detail']);
        $this->assertArrayNotHasKey('debug', $body);
    }

    public function test_validation_exception_gets_instance_path(): void
    {
        $_ENV['APP_DEBUG'] = 'false';

        // Create a proper ValidationException with a Problem response
        $errors = ['email' => ['Email is required']];
        $problemResponse = \LeanPHP\Http\Problem::validation($errors);
        $validationException = new ValidationException($problemResponse);

        $response = $this->errorHandler->handle($this->request, function () use ($validationException) {
            throw $validationException;
        });

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);

        // Check that instance path was added to validation error
        $this->assertEquals('/test/path', $body['instance']);
        $this->assertEquals('Unprocessable Content', $body['title']);
        $this->assertArrayHasKey('errors', $body);
        $this->assertEquals($errors, $body['errors']);
    }

    public function test_successful_request_passes_through(): void
    {
        $expectedResponse = Response::json(['success' => true]);

        $actualResponse = $this->errorHandler->handle($this->request, function () use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $actualResponse);
    }

    public function test_debug_trace_is_limited_in_depth(): void
    {
        $_ENV['APP_DEBUG'] = 'true';

        // Create a deep call stack
        $deepException = $this->createDeepException(15);

        $response = $this->errorHandler->handle($this->request, function () use ($deepException) {
            throw $deepException;
        });

        $body = json_decode($response->getBody(), true);

        // Check that trace is limited to 10 items
        $this->assertArrayHasKey('debug', $body);
        $this->assertArrayHasKey('trace', $body['debug']);
        $this->assertLessThanOrEqual(10, count($body['debug']['trace']));
    }

    private function createDeepException(int $depth): Exception
    {
        if ($depth <= 0) {
            return new Exception('Deep exception');
        }

        $innerException = $this->createDeepException($depth - 1);
        return new Exception('Level ' . $depth, 0, $innerException);
    }
}
