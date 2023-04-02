<?php

declare(strict_types=1);

namespace LeanPHP\RateLimit;

/**
 * APCu-based rate limit store.
 */

class ApcuStore implements Store
{
    public function hit(string $key, int $limit, int $window): array
    {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension is not loaded');
        }

        $now = time();
        $windowStart = $now - $window;

        // Use a sliding window approach
        $windowKey = $this->getWindowKey($key, $now, $window);
        $countKey = "count:{$windowKey}";
        $timestampKey = "ts:{$windowKey}";

        // Get current count and timestamp
        $success = false;
        $count = (int) apcu_fetch($countKey, $success);
        if (!$success) {
            $count = 0;
        }

        $success = false;
        $timestamp = (int) apcu_fetch($timestampKey, $success);
        if (!$success) {
            $timestamp = $now;
        }

        // If the window has expired, reset
        if ($timestamp < $windowStart) {
            $count = 0;
            $timestamp = $now;
        }

        // Increment the count
        $count++;

        // Store the updated values with TTL
        apcu_store($countKey, $count, $window);
        apcu_store($timestampKey, $timestamp, $window);

        $remaining = max(0, $limit - $count);
        $resetAt = $timestamp + $window;

        return ['remaining' => $remaining, 'resetAt' => $resetAt];
    }

    public function allow(string $key, int $limit, int $window): bool
    {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension is not loaded');
        }

        $now = time();
        $windowStart = $now - $window;

        $windowKey = $this->getWindowKey($key, $now, $window);
        $countKey = "count:{$windowKey}";
        $timestampKey = "ts:{$windowKey}";

        $success = false;
        $count = (int) apcu_fetch($countKey, $success);
        if (!$success) {
            $count = 0;
        }

        $success = false;
        $timestamp = (int) apcu_fetch($timestampKey, $success);
        if (!$success || $timestamp < $windowStart) {
            $count = 0;
        }

        return $count < $limit;
    }

    public function retryAfter(string $key, int $limit, int $window): int
    {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension is not loaded');
        }

        if ($this->allow($key, $limit, $window)) {
            return 0;
        }

        $now = time();
        $windowKey = $this->getWindowKey($key, $now, $window);
        $timestampKey = "ts:{$windowKey}";

        $success = false;
        $timestamp = (int) apcu_fetch($timestampKey, $success);
        if (!$success) {
            return 0;
        }

        $resetAt = $timestamp + $window;
        return max(0, $resetAt - $now);
    }

    /**
     * Generate a window key based on the current time window
     */
    private function getWindowKey(string $key, int $now, int $window): string
    {
        // Create a window key that groups requests into time buckets
        $bucket = floor($now / $window);
        return "{$key}:{$bucket}";
    }
}
