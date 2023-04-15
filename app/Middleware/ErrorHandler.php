<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;
use LeanPHP\Validation\ValidationException;
use Throwable;

class ErrorHandler
{
    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    private function handleException(Throwable $e, Request $request): Response
    {
        $debug = env_bool('APP_DEBUG', false);

        // Handle validation exceptions (they already have proper error format)
        if ($e instanceof ValidationException) {
            $response = $e->getResponse();
            // Add instance path to validation errors too
            $body = json_decode($response->getBody(), true);
            $body['instance'] = $request->path();
            $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $response->setBody($encodedBody ?: '{"error": "Encoding failed"}');
        }

        // Default to 500 Internal Server Error
        $status = 500;
        $title = 'Internal Server Error';
        $type = '/problems/internal-server-error';
        $instance = $request->path();

        // In debug mode, show the actual exception message
        // In production, use a generic message
        $detail = $debug ? $e->getMessage() : 'An unexpected error occurred';

        // Handle specific exception types if needed
        // For now, we'll handle all exceptions as 500 errors

        $problem = Problem::make($status, $title, $detail, $type, null, $instance);

        // Add debugging information ONLY if debug mode is enabled
        if ($debug) {
            $debugInfo = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 10), // Limit trace depth
                'class' => get_class($e),
            ];

            // Add debug info to the problem response
            $body = json_decode($problem->getBody(), true);
            $body['debug'] = $debugInfo;
            $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $problem = $problem->setBody($encodedBody ?: '{"error": "Failed to encode debug information"}');
        }

        return $problem;
    }
}
