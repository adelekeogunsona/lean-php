<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Logging\Logger;

class TestCounter
{
    private static int $count = 0;

    public function handle(Request $request, callable $next): Response
    {
        self::$count++;

        $logger = Logger::getInstance();
        $logger->info('TestCounter middleware executed', [
            'count' => self::$count,
            'path' => $request->path(),
        ]);

        $response = $next($request);

        // Add counter to response headers for testing
        return $response->header('X-Test-Counter', (string) self::$count);
    }

    public static function getCount(): int
    {
        return self::$count;
    }

    public static function reset(): void
    {
        self::$count = 0;
    }
}
