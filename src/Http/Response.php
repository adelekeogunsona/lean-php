<?php

declare(strict_types=1);

namespace LeanPHP\Http;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    private function __construct()
    {
    }

    /**
     * Create a JSON response.
     */
    public static function json(array|object $data, int $status = 200): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['content-type'] = 'application/json';
        $response->body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    /**
     * Create a text response.
     */
    public static function text(string $body, int $status = 200): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['content-type'] = 'text/plain; charset=utf-8';
        $response->body = $body;

        return $response;
    }

    /**
     * Create a no-content response (204).
     */
    public static function noContent(): self
    {
        $response = new self();
        $response->statusCode = 204;
        $response->body = '';

        return $response;
    }

    /**
     * Set the status code.
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set a header.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;
        return $this;
    }

    /**
     * Get the status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the response body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Set the response body directly.
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
