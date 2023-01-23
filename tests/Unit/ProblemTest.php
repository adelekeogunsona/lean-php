<?php

declare(strict_types=1);

namespace Tests\Unit;

use LeanPHP\Http\Problem;
use PHPUnit\Framework\TestCase;

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

    public function test_make_creates_problem_with_all_fields(): void
    {
        $errors = ['email' => ['Must be a valid email address']];
        $response = Problem::make(
            422,
            'Validation Failed',
            'The request contains validation errors',
            '/problems/validation',
            $errors,
            '/users/create'
        );

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/validation', $body['type']);
        $this->assertEquals('Validation Failed', $body['title']);
        $this->assertEquals(422, $body['status']);
        $this->assertEquals('The request contains validation errors', $body['detail']);
        $this->assertEquals('/users/create', $body['instance']);
        $this->assertEquals($errors, $body['errors']);
    }

    public function test_make_omits_null_optional_fields(): void
    {
        $response = Problem::make(500, 'Internal Server Error');

        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('type', $body);
        $this->assertArrayHasKey('title', $body);
        $this->assertArrayHasKey('status', $body);
        $this->assertArrayNotHasKey('detail', $body);
        $this->assertArrayNotHasKey('instance', $body);
        $this->assertArrayNotHasKey('errors', $body);
    }

    public function test_bad_request_creates_400_problem(): void
    {
        $response = Problem::badRequest('Invalid JSON body');

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/bad-request', $body['type']);
        $this->assertEquals('Bad Request', $body['title']);
        $this->assertEquals(400, $body['status']);
        $this->assertEquals('Invalid JSON body', $body['detail']);
    }

    public function test_unauthorized_creates_401_problem(): void
    {
        $response = Problem::unauthorized('Token expired');

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/unauthorized', $body['type']);
        $this->assertEquals('Unauthorized', $body['title']);
        $this->assertEquals('Token expired', $body['detail']);
    }

    public function test_forbidden_creates_403_problem(): void
    {
        $response = Problem::forbidden('Insufficient permissions');

        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/forbidden', $body['type']);
        $this->assertEquals('Forbidden', $body['title']);
        $this->assertEquals('Insufficient permissions', $body['detail']);
    }

    public function test_not_found_creates_404_problem(): void
    {
        $response = Problem::notFound('User not found');

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/not-found', $body['type']);
        $this->assertEquals('Not Found', $body['title']);
        $this->assertEquals('User not found', $body['detail']);
    }

    public function test_method_not_allowed_creates_405_problem_with_allow_header(): void
    {
        $response = Problem::methodNotAllowed(['GET', 'POST']);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET, POST', $response->getHeaders()['allow']);

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/method-not-allowed', $body['type']);
        $this->assertEquals('Method Not Allowed', $body['title']);
    }

    public function test_unsupported_media_type_creates_415_problem(): void
    {
        $response = Problem::unsupportedMediaType('Only JSON is supported');

        $this->assertEquals(415, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/unsupported-media-type', $body['type']);
        $this->assertEquals('Unsupported Media Type', $body['title']);
        $this->assertEquals('Only JSON is supported', $body['detail']);
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

    public function test_too_many_requests_creates_429_problem_with_retry_after(): void
    {
        $response = Problem::tooManyRequests('Rate limit exceeded', 60);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertEquals('60', $response->getHeaders()['retry-after']);

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/too-many-requests', $body['type']);
        $this->assertEquals('Too Many Requests', $body['title']);
        $this->assertEquals('Rate limit exceeded', $body['detail']);
    }

    public function test_internal_server_error_creates_500_problem(): void
    {
        $response = Problem::internalServerError('Database connection failed');

        $this->assertEquals(500, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('/problems/internal-server-error', $body['type']);
        $this->assertEquals('Internal Server Error', $body['title']);
        $this->assertEquals('Database connection failed', $body['detail']);
    }
}
