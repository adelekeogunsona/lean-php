<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;

class JsonBodyParser
{
    private array $methodsRequiringJson = ['POST', 'PUT', 'PATCH'];
    private array $methodsAllowedWithoutJson = ['GET', 'HEAD', 'OPTIONS', 'DELETE'];

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        $contentType = $request->header('content-type');

        // Skip validation for methods that don't typically have bodies
        if (in_array($method, $this->methodsAllowedWithoutJson)) {
            return $next($request);
        }

        // For methods that typically have bodies, check content type
        if (in_array($method, $this->methodsRequiringJson)) {
            // Check if request has a body (Content-Length > 0 or Transfer-Encoding present)
            $contentLength = (int) ($request->header('content-length') ?? 0);
            $hasTransferEncoding = $request->header('transfer-encoding') !== null;

            // If there's a body, validate content type
            if ($contentLength > 0 || $hasTransferEncoding) {
                if (!$contentType || !str_contains($contentType, 'application/json')) {
                    return Problem::make(
                        415,
                        'Unsupported Media Type',
                        'Content-Type must be application/json',
                        '/problems/unsupported-media-type'
                    );
                }

                // Validate JSON format by attempting to parse
                $body = $this->getRequestBody();
                if ($body !== '') {
                    // Clear any previous JSON errors
                    json_encode(null); // Reset json_last_error()

                    // Try to decode the JSON
                    json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return Problem::make(
                            400,
                            'Bad Request',
                            'Invalid JSON format: ' . json_last_error_msg(),
                            '/problems/invalid-json'
                        );
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Get the raw request body.
     */
    private function getRequestBody(): string
    {
        static $body = null;

        if ($body === null) {
            $body = file_get_contents('php://input') ?: '';
        }

        return $body;
    }
}
