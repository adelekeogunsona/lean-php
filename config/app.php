<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/env.php';

return [
    'env' => env_string('APP_ENV', 'development'),
    'debug' => env_bool('APP_DEBUG', true),
    'url' => env_string('APP_URL', 'http://localhost:8000'),
    'timezone' => env_string('APP_TIMEZONE', 'UTC'),
    'log_path' => env_string('LOG_PATH', 'storage/logs/app.log'),
];
