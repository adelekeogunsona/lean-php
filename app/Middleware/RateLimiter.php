<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;
use LeanPHP\RateLimit\Store;
use LeanPHP\RateLimit\ApcuStore;
use LeanPHP\RateLimit\FileStore;

class RateLimiter
{
    private Store $store;
    private int $defaultLimit;
    private int $defaultWindow;

    public function __construct(?Store $store = null)
    {
        $this->store = $store ?? $this->createStore();
        $this->defaultLimit = (int) env_string('RATE_LIMIT_DEFAULT', '60');
        $this->defaultWindow = (int) env_string('RATE_LIMIT_WINDOW', '60');
    }

    /**
     * Handle the request and apply rate limiting.
     */
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->getRateLimitKey($request);
        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        // Check if request is allowed before processing
        if (!$this->store->allow($key, $limit, $window)) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        // Record the hit and get current state
        $result = $this->store->hit($key, $limit, $window);
        $remaining = $result['remaining'];
        $resetAt = $result['resetAt'];

        // If we've exceeded the limit after this hit, return 429
        if ($remaining <= 0) {
            return $this->rateLimitExceeded($key, $limit, $window);
        }

        // Continue to next middleware/controller
        $response = $next($request);

        // Add rate limit headers to the response
        return $this->addRateLimitHeaders($response, $limit, $remaining, $resetAt);
    }

    /**
     * Create the appropriate store based on environment configuration.
     */
    private function createStore(): Store
    {
        $storeType = env_string('RATE_LIMIT_STORE', 'apcu');

        return match ($storeType) {
            'apcu' => extension_loaded('apcu') ? new ApcuStore() : new FileStore(),
            'file' => new FileStore(),
            default => throw new \InvalidArgumentException("Unsupported rate limit store: {$storeType}")
        };
    }

    /**
     * Get the rate limiting key for the request.
     * Uses user ID if authenticated, otherwise IP address.
     */
    private function getRateLimitKey(Request $request): string
    {
        // If user is authenticated, use their user ID (sub claim)
        if ($request->isAuthenticated()) {
            $userId = $request->claim('sub');
            if ($userId) {
                return "user:{$userId}";
            }
        }

        // Fall back to IP address
        $ip = $request->getClientIp();
        return "ip:{$ip}";
    }

    /**
     * Create a 429 Too Many Requests response with rate limit headers.
     */
    private function rateLimitExceeded(string $key, int $limit, int $window): Response
    {
        $retryAfter = $this->store->retryAfter($key, $limit, $window);

        // Add small jitter (1-5 seconds) to prevent thundering herd
        if ($retryAfter > 0) {
            $jitter = random_int(1, 5);
            $retryAfter += $jitter;
        }

        $response = Problem::tooManyRequests('Rate limit exceeded', $retryAfter);

        return $this->addRateLimitHeaders($response, $limit, 0, time() + $retryAfter);
    }

    /**
     * Add rate limiting headers to the response.
     */
    private function addRateLimitHeaders(Response $response, int $limit, int $remaining, int $resetAt): Response
    {
        return $response
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', (string) $remaining)
            ->header('X-RateLimit-Reset', (string) $resetAt);
    }
}

