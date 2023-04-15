<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use LeanPHP\DB\DB;

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

echo "🌱 Seeding database...\n";

try {
    // Create users table
    echo "Creating users table...\n";
    DB::execute("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            scopes TEXT DEFAULT 'users.read',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Check if demo user already exists
    $existingUser = DB::select("SELECT id FROM users WHERE email = :email", [':email' => 'demo@example.com']);

    if (!empty($existingUser)) {
        echo "Demo user already exists, skipping...\n";
    } else {
        // Create demo user with Argon2id password hashing
        echo "Creating demo user...\n";

        $hashedPassword = password_hash('secret', PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,        // 4 iterations
            'threads' => 3,          // 3 threads
        ]);

        DB::execute("
            INSERT INTO users (name, email, password, scopes)
            VALUES (:name, :email, :password, :scopes)
        ", [
            ':name' => 'Demo User',
            ':email' => 'demo@example.com',
            ':password' => $hashedPassword,
            ':scopes' => 'users.read,users.write'
        ]);

        echo "Demo user created successfully!\n";
    }

    // Display user count
    $userCount = DB::select("SELECT COUNT(*) as count FROM users");
    echo "Total users in database: {$userCount[0]['count']}\n";

    echo "✅ Database seeded successfully!\n";
    echo "\nDemo user credentials:\n";
    echo "Email: demo@example.com\n";
    echo "Password: secret\n\n";

} catch (\Exception $e) {
    echo "❌ Error seeding database: " . $e->getMessage() . "\n";
    echo "Make sure you have set up your .env file with the correct database configuration.\n";
    exit(1);
}
