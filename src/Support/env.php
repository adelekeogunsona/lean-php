<?php

declare(strict_types=1);

/**
 * Get an environment variable as a string.
 */
function env_string(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false) {
        return $default;
    }

    return (string) $value;
}

/**
 * Get an environment variable as a boolean.
 */
function env_bool(string $key, ?bool $default = null): ?bool
{
    $value = env_string($key);

    if ($value === null) {
        return $default;
    }

    return match (strtolower($value)) {
        'true', '1', 'yes', 'on' => true,
        'false', '0', 'no', 'off', '' => false,
        default => $default,
    };
}

/**
 * Get an environment variable as an integer.
 */
function env_int(string $key, ?int $default = null): ?int
{
    $value = env_string($key);

    if ($value === null || $value === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}
