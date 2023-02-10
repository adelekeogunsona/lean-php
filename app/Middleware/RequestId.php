<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Logging\Logger;

class RequestId
{
    public function handle(Request $request, callable $next): Response
    {
        // Get or generate request ID
        $requestId = $request->header('x-request-id') ?: $this->generateRequestId();

        // Set request ID in logger context
        $logger = Logger::getInstance();
        $logger->setContext(['request_id' => $requestId]);

        // Log the incoming request
        $logger->info('Incoming request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->header('user-agent'),
        ]);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response = $response->header('X-Request-Id', $requestId);

        // Log the response
        $logger->info('Outgoing response', [
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /**
     * Generate a unique request ID.
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4))
        );
    }
}
