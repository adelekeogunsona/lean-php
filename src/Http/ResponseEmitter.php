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

        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        // Set status code
        http_response_code($statusCode);

        // Prepare headers with proper Content-Length
        $headers = self::prepareHeaders($response->getHeaders(), $body, $statusCode);

        // Set headers with proper formatting and sanitization
        foreach ($headers as $name => $value) {
            $formattedName = self::formatHeaderName($name);
            $sanitizedValue = self::sanitizeHeaderValue($value);
            header(sprintf('%s: %s', $formattedName, $sanitizedValue));
        }

        // Send body only for responses that should have a body
        if (!self::shouldOmitBody($statusCode)) {
            echo $body;
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

    /**
     * Prepare headers, adding Content-Length if appropriate.
     */
    private static function prepareHeaders(array $headers, string $body, int $statusCode): array
    {
        // Don't add Content-Length for responses that shouldn't have a body
        if (self::shouldOmitBody($statusCode)) {
            return $headers;
        }

        // Don't override existing Content-Length header
        if (isset($headers['content-length'])) {
            return $headers;
        }

        // Add Content-Length for responses with a body
        $headers['content-length'] = (string) strlen($body);

        return $headers;
    }

    /**
     * Format header name to proper HTTP header case (Title-Case).
     */
    private static function formatHeaderName(string $name): string
    {
        // Special case mappings for common headers that need specific formatting
        $specialCases = [
            'www-authenticate' => 'WWW-Authenticate',
            'content-md5' => 'Content-MD5',
            'etag' => 'ETag',
            'te' => 'TE',
        ];

        $lowerName = strtolower($name);
        if (isset($specialCases[$lowerName])) {
            return $specialCases[$lowerName];
        }

        // Convert to title case: content-type -> Content-Type
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
    }

    /**
     * Sanitize header value to prevent header injection attacks.
     */
    private static function sanitizeHeaderValue(string $value): string
    {
        // Remove control characters that could lead to header injection
        return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
    }
}
