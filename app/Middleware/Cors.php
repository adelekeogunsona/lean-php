<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class Cors
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;
    private int $maxAge;
    private bool $allowCredentials;

    public function __construct()
    {
        $this->allowedOrigins = $this->parseOrigins($_ENV['CORS_ALLOW_ORIGINS'] ?? '*');
        $this->allowedMethods = $this->parseMethods($_ENV['CORS_ALLOW_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $this->allowedHeaders = $this->parseHeaders($_ENV['CORS_ALLOW_HEADERS'] ?? 'Authorization,Content-Type');
        $this->maxAge = (int) ($_ENV['CORS_MAX_AGE'] ?? 600);
        $this->allowCredentials = filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // Process the actual request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Handle preflight OPTIONS request.
     */
    private function handlePreflight(Request $request, ?string $origin): Response
    {
        $response = Response::noContent()->status(204);

        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $response; // No CORS headers for disallowed origins
        }

        $requestMethod = $request->header('access-control-request-method');
        $requestHeaders = $request->header('access-control-request-headers');

        // Check if method is allowed
        if ($requestMethod && !in_array(strtoupper($requestMethod), $this->allowedMethods)) {
            return $response; // Method not allowed
        }

        // Add preflight response headers
        $response = $response
            ->header('Access-Control-Allow-Origin', $this->getAllowOriginValue($origin))
            ->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->header('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->header('Access-Control-Allow-Credentials', 'true');
        }

        // Add Vary headers
        $response = $response
            ->header('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');

        return $response;
    }

    /**
     * Add CORS headers to a regular response.
     */
    private function addCorsHeaders(Response $response, ?string $origin): Response
    {
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $response->header('Vary', 'Origin'); // Still add Vary header
        }

        $response = $response
            ->header('Access-Control-Allow-Origin', $this->getAllowOriginValue($origin))
            ->header('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if the origin is allowed.
     */
    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        // Allow all origins if configured with *
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins);
    }

    /**
     * Get the appropriate Access-Control-Allow-Origin value.
     */
    private function getAllowOriginValue(?string $origin): string
    {
        // If allowing all origins and credentials are disabled, return *
        if (in_array('*', $this->allowedOrigins) && !$this->allowCredentials) {
            return '*';
        }

        // When credentials are enabled, we must return the specific origin (never *)
        // If no origin provided but we need a specific one, this is an error case
        return $origin ?? '';
    }

    /**
     * Parse origins from environment variable.
     */
    private function parseOrigins(string $origins): array
    {
        if ($origins === '*') {
            return ['*'];
        }

        return array_map('trim', explode(',', $origins));
    }

    /**
     * Parse methods from environment variable.
     */
    private function parseMethods(string $methods): array
    {
        return array_map(fn($method) => strtoupper(trim($method)), explode(',', $methods));
    }

    /**
     * Parse headers from environment variable.
     */
    private function parseHeaders(string $headers): array
    {
        return array_map('trim', explode(',', $headers));
    }
}
