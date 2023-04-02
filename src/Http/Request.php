<?php

declare(strict_types=1);

namespace LeanPHP\Http;

class Request
{
    private string $method;
    private string $path;
    private array $headers;
    private array $query;
    private ?array $json;
    private array $params = [];
    private ?array $claims = null;

    private function __construct(
        string $method,
        string $path,
        array $headers,
        array $query,
        ?array $json
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->headers = $headers;
        $this->query = $query;
        $this->json = $json;
    }

    /**
     * Create a Request from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Extract headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerName)] = $value;
            }
        }

        // Add special headers that don't start with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        $query = $_GET;

        // Parse JSON body if present
        $json = null;
        $rawBody = file_get_contents('php://input');
        if ($rawBody && str_contains($headers['content-type'] ?? '', 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        return new self($method, $path, $headers, $query, $json);
    }

    /**
     * Get the HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the request path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get a header value.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Get a query parameter.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get a value from the JSON body.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $default;
    }

    /**
     * Get the decoded JSON body.
     */
    public function json(): ?array
    {
        return $this->json;
    }

    /**
     * Get the Bearer token from Authorization header.
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->header('authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return substr($authorization, 7);
    }

    /**
     * Get route parameters (set by the router).
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Set route parameters (used by the router).
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get JWT claims (set by AuthBearer middleware).
     */
    public function claims(): ?array
    {
        return $this->claims;
    }

    /**
     * Set JWT claims (used by AuthBearer middleware).
     */
    public function setClaims(array $claims): void
    {
        $this->claims = $claims;
    }

    /**
     * Get a specific claim value.
     */
    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }

    /**
     * Check if the request is authenticated (has valid JWT claims).
     */
    public function isAuthenticated(): bool
    {
        return $this->claims !== null;
    }

    /**
     * Get the client IP address.
     *
     * Checks X-Forwarded-For and X-Real-IP headers for proxy scenarios,
     * falls back to REMOTE_ADDR.
     */
    public function getClientIp(): string
    {
        // Check X-Forwarded-For (may contain comma-separated list)
        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded) {
            $ips = array_map('trim', explode(',', $forwarded));
            // Return the first (original client) IP
            return $ips[0];
        }

        // Check X-Real-IP
        $realIp = $this->header('x-real-ip');
        if ($realIp) {
            return $realIp;
        }

        // Fall back to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
