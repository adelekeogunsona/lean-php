<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

// use .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}
