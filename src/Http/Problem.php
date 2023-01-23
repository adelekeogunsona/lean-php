<?php

declare(strict_types=1);

namespace LeanPHP\Http;

class Problem
{
    /**
     * Create a Problem+JSON response.
     *
     * Implements RFC 7807 Problem Details for HTTP APIs.
     */
    public static function make(
        int $status,
        string $title,
        ?string $detail = null,
        string $type = '/problems/generic',
        ?array $errors = null,
        ?string $instance = null
    ): Response {
        $problem = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== null) {
            $problem['detail'] = $detail;
        }

        if ($instance !== null) {
            $problem['instance'] = $instance;
        }

        if ($errors !== null) {
            $problem['errors'] = $errors;
        }

        return Response::json($problem, $status)
            ->header('content-type', 'application/problem+json');
    }

    /**
     * Create a 400 Bad Request problem.
     */
    public static function badRequest(string $detail = 'The request is invalid'): Response
    {
        return self::make(400, 'Bad Request', $detail, '/problems/bad-request');
    }

    /**
     * Create a 401 Unauthorized problem.
     */
    public static function unauthorized(string $detail = 'Authentication is required'): Response
    {
        return self::make(401, 'Unauthorized', $detail, '/problems/unauthorized');
    }

    /**
     * Create a 403 Forbidden problem.
     */
    public static function forbidden(string $detail = 'Access is forbidden'): Response
    {
        return self::make(403, 'Forbidden', $detail, '/problems/forbidden');
    }

    /**
     * Create a 404 Not Found problem.
     */
    public static function notFound(string $detail = 'The requested resource was not found'): Response
    {
        return self::make(404, 'Not Found', $detail, '/problems/not-found');
    }

    /**
     * Create a 405 Method Not Allowed problem.
     */
    public static function methodNotAllowed(array $allowedMethods = []): Response
    {
        $response = self::make(405, 'Method Not Allowed', 'The HTTP method is not allowed for this resource', '/problems/method-not-allowed');

        if (!empty($allowedMethods)) {
            $response->header('allow', implode(', ', $allowedMethods));
        }

        return $response;
    }

    /**
     * Create a 415 Unsupported Media Type problem.
     */
    public static function unsupportedMediaType(string $detail = 'The media type is not supported'): Response
    {
        return self::make(415, 'Unsupported Media Type', $detail, '/problems/unsupported-media-type');
    }

    /**
     * Create a 422 Unprocessable Content problem with validation errors.
     */
    public static function validation(array $errors): Response
    {
        return self::make(
            422,
            'Unprocessable Content',
            'The request contains validation errors',
            '/problems/validation',
            $errors
        );
    }

    /**
     * Create a 429 Too Many Requests problem.
     */
    public static function tooManyRequests(string $detail = 'Too many requests', ?int $retryAfter = null): Response
    {
        $response = self::make(429, 'Too Many Requests', $detail, '/problems/too-many-requests');

        if ($retryAfter !== null) {
            $response->header('retry-after', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Create a 500 Internal Server Error problem.
     */
    public static function internalServerError(string $detail = 'An internal server error occurred'): Response
    {
        return self::make(500, 'Internal Server Error', $detail, '/problems/internal-server-error');
    }
}
