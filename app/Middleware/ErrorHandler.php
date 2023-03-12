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
        $debug = (bool) ($_ENV['APP_DEBUG'] ?? false);

        // Handle validation exceptions
        if ($e instanceof ValidationException) {
            return $e->getResponse();
        }

        // Default to 500 Internal Server Error
        $status = 500;
        $title = 'Internal Server Error';
        $detail = $debug ? $e->getMessage() : 'An unexpected error occurred';
        $type = '/problems/server-error';

        // Handle specific exception types if needed
        // For now, we'll handle all exceptions as 500 errors

        $problem = Problem::make($status, $title, $detail, $type);

        // Add debugging information if debug mode is enabled
        if ($debug) {
            $debugInfo = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];

            // Add debug info to the problem response
            $body = json_decode($problem->getBody(), true);
            $body['debug'] = $debugInfo;
            $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encodedBody === false) {
                $encodedBody = '{"error": "Failed to encode debug information"}';
            }
            $problem = $problem->setBody($encodedBody);
        }

        return $problem;
    }
}
