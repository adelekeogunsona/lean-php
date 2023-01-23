<?php

declare(strict_types=1);

namespace LeanPHP\Http;

class ResponseEmitter
{
    /**
     * Emit a Response to the output buffer and browser.
     */
    public static function emit(Response $response): void
    {
        // Don't emit if headers already sent (for testing)
        if (headers_sent()) {
            return;
        }

        // Set status code
        http_response_code($response->getStatusCode());

        // Set headers
        foreach ($response->getHeaders() as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        // Send body only for responses that should have a body
        if (!self::shouldOmitBody($response->getStatusCode())) {
            echo $response->getBody();
        }
    }

    /**
     * Check if we should omit the response body based on status code.
     */
    private static function shouldOmitBody(int $statusCode): bool
    {
        // No body for 204 No Content, 304 Not Modified, or 1xx responses
        return $statusCode === 204
            || $statusCode === 304
            || ($statusCode >= 100 && $statusCode < 200);
    }
}
