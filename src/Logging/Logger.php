<?php

declare(strict_types=1);

namespace LeanPHP\Logging;

class Logger
{
    private string $logPath;
    private array $context = [];

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;

        // Ensure log directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Add context that will be included in all log entries.
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Log an info message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log an error message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a warning message.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Write a log entry to the file.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Merge context with global context
        $fullContext = array_merge($this->context, $context);

        // Redact sensitive information
        $fullContext = $this->redactSensitiveData($fullContext);

        // Format the log entry
        $logEntry = sprintf(
            "[%s] %s: %s%s\n",
            $timestamp,
            $level,
            $message,
            empty($fullContext) ? '' : ' ' . json_encode($fullContext, JSON_UNESCAPED_SLASHES)
        );

        // Write to file (with locking for thread safety)
        file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Redact sensitive information from context data.
     */
    private function redactSensitiveData(array $context): array
    {
        $redacted = $context;

        // Redact authorization headers and tokens
        $sensitiveKeys = ['authorization', 'bearer', 'token', 'password', 'secret', 'key'];

        foreach ($redacted as $key => $value) {
            $keyLower = strtolower((string) $key);

            // Check if this key contains sensitive information
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    $redacted[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively redact arrays
            if (is_array($value)) {
                $redacted[$key] = $this->redactSensitiveData($value);
            }
        }

        return $redacted;
    }

    /**
     * Get a singleton instance of the logger.
     */
    public static function getInstance(): self
    {
        static $instance = null;

        if ($instance === null) {
            $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs/app.log';
            $instance = new self($logPath);
        }

        return $instance;
    }
}
