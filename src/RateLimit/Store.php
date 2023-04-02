<?php

declare(strict_types=1);

namespace LeanPHP\RateLimit;

interface Store
{
    /**
     * Record a hit for the given key and return remaining count and reset time
     *
     * @param string $key Unique identifier for the rate limit (user ID, IP, etc.)
     * @param int $limit Maximum number of hits allowed in the window
     * @param int $window Time window in seconds
     * @return array{remaining: int, resetAt: int} Remaining hits and Unix timestamp when window resets
     */
    public function hit(string $key, int $limit, int $window): array;

    /**
     * Check if a key is allowed to make a request without incrementing the counter
     *
     * @param string $key Unique identifier for the rate limit
     * @param int $limit Maximum number of hits allowed in the window
     * @param int $window Time window in seconds
     * @return bool True if the request is allowed
     */
    public function allow(string $key, int $limit, int $window): bool;

    /**
     * Get the number of seconds to wait before retrying
     *
     * @param string $key Unique identifier for the rate limit
     * @param int $limit Maximum number of hits allowed in the window
     * @param int $window Time window in seconds
     * @return int Seconds to wait before retrying, or 0 if allowed
     */
    public function retryAfter(string $key, int $limit, int $window): int;
}

