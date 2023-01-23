<?php

declare(strict_types=1);

/**
 * Router script for PHP built-in server.
 *
 * Usage: php -S localhost:8000 -t public public/router.php
 *
 * This script handles routing for the PHP built-in development server.
 * All requests are routed through index.php unless they're for static files.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';

// Serve static files directly if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let the built-in server handle static files
}

// Route all other requests through index.php
require_once __DIR__ . '/index.php';
