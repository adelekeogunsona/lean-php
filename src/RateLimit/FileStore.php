<?php

declare(strict_types=1);

namespace LeanPHP\RateLimit;

class FileStore implements Store
{
    private string $storageDir;

    public function __construct(string $storageDir = 'storage/ratelimit')
    {
        $this->storageDir = $storageDir;

        // Ensure the storage directory exists
        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
                throw new \RuntimeException("Failed to create rate limit storage directory: {$this->storageDir}");
            }
        }
    }

    public function hit(string $key, int $limit, int $window): array
    {
        $filename = $this->getFilename($key);
        $now = time();

        // Use flock for atomic updates
        $fp = fopen($filename, 'c+');
        if (!$fp) {
            throw new \RuntimeException("Failed to open rate limit file: {$filename}");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException("Failed to acquire lock on rate limit file: {$filename}");
            }

            // Read current data
            $data = $this->readData($fp);
            $windowStart = $now - $window;

            // Clean expired entries and count current hits
            $hits = array_filter($data['hits'] ?? [], fn($timestamp) => $timestamp > $windowStart);

            // Add current hit
            $hits[] = $now;

            // Calculate remaining and reset time
            $count = count($hits);
            $remaining = max(0, $limit - $count);
            $resetAt = min($hits) + $window;

            // Write updated data
            $newData = ['hits' => $hits, 'updated' => $now];
            $this->writeData($fp, $newData);

            return ['remaining' => $remaining, 'resetAt' => $resetAt];

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function allow(string $key, int $limit, int $window): bool
    {
        $filename = $this->getFilename($key);
        $now = time();

        // Read-only check doesn't need write lock
        if (!file_exists($filename)) {
            return true;
        }

        $fp = fopen($filename, 'r');
        if (!$fp) {
            return true; // Assume allowed if we can't read
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return true; // Assume allowed if we can't get shared lock
            }

            $data = $this->readData($fp);
            $windowStart = $now - $window;

            // Count current hits in window
            $hits = array_filter($data['hits'] ?? [], fn($timestamp) => $timestamp > $windowStart);

            return count($hits) < $limit;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function retryAfter(string $key, int $limit, int $window): int
    {
        if ($this->allow($key, $limit, $window)) {
            return 0;
        }

        $filename = $this->getFilename($key);
        $now = time();

        if (!file_exists($filename)) {
            return 0;
        }

        $fp = fopen($filename, 'r');
        if (!$fp) {
            return 0;
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return 0;
            }

            $data = $this->readData($fp);
            $windowStart = $now - $window;

            // Get valid hits in current window
            $hits = array_filter($data['hits'] ?? [], fn($timestamp) => $timestamp > $windowStart);

            if (count($hits) < $limit) {
                return 0;
            }

            // Find the oldest hit and calculate when it expires
            $oldestHit = min($hits);
            $resetAt = $oldestHit + $window;

            return (int) max(0, $resetAt - $now);

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function getFilename(string $key): string
    {
        // Use a safe filename based on the key
        $safeKey = preg_replace('/[^a-zA-Z0-9._-]/', '_', $key);
        return $this->storageDir . '/' . $safeKey . '.json';
    }

    /**
     * @param resource $fp
     */
    private function readData($fp): array
    {
        rewind($fp);
        $content = stream_get_contents($fp);

        if (empty($content)) {
            return ['hits' => [], 'updated' => 0];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return ['hits' => [], 'updated' => 0];
        }

        return $data;
    }

    /**
     * @param resource $fp
     */
    private function writeData($fp, array $data): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_THROW_ON_ERROR));
        fflush($fp);
    }
}

