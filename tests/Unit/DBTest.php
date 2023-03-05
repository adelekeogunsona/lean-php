<?php

declare(strict_types=1);

use LeanPHP\DB\DB;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension is not available');
        }

        // Reset connection to ensure clean state for each test
        DB::resetConnection();

        // Set up in-memory SQLite for testing
        putenv('DB_DSN=sqlite::memory:');
        putenv('DB_USER=');
        putenv('DB_PASSWORD=');
        putenv('DB_ATTR_PERSISTENT=false');
    }

    protected function tearDown(): void
    {
        DB::resetConnection();
    }

    public function test_can_create_table_and_insert_data(): void
    {
        // Create a test table
        $createSql = "CREATE TABLE test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            age INTEGER
        )";

        $affected = DB::execute($createSql);
        $this->assertEquals(0, $affected); // CREATE TABLE returns 0 affected rows

        // Insert test data
        $insertSql = "INSERT INTO test_users (name, email, age) VALUES (:name, :email, :age)";
        $affected = DB::execute($insertSql, [
            ':name' => 'John Doe',
            ':email' => 'john@example.com',
            ':age' => 30
        ]);

        $this->assertEquals(1, $affected);
    }

    public function test_can_select_data(): void
    {
        // Set up test table and data
        DB::execute("CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");
        DB::execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ['Alice', 'alice@example.com']);
        DB::execute("INSERT INTO test_users (name, email) VALUES (?, ?)", ['Bob', 'bob@example.com']);

        // Select all users
        $users = DB::select("SELECT * FROM test_users ORDER BY name");

        $this->assertCount(2, $users);
        $this->assertEquals('Alice', $users[0]['name']);
        $this->assertEquals('alice@example.com', $users[0]['email']);
        $this->assertEquals('Bob', $users[1]['name']);
        $this->assertEquals('bob@example.com', $users[1]['email']);
    }

    public function test_can_select_with_where_clause(): void
    {
        // Set up test data
        DB::execute("CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)");
        DB::execute("INSERT INTO test_users (name, age) VALUES ('Alice', 25)");
        DB::execute("INSERT INTO test_users (name, age) VALUES ('Bob', 35)");
        DB::execute("INSERT INTO test_users (name, age) VALUES ('Charlie', 30)");

        // Select users over 30
        $users = DB::select("SELECT * FROM test_users WHERE age > :age ORDER BY name", [':age' => 30]);

        $this->assertCount(1, $users);
        $this->assertEquals('Bob', $users[0]['name']);
        $this->assertEquals(35, $users[0]['age']);
    }

    public function test_transaction_commit(): void
    {
        DB::execute("CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)");

        $result = DB::transaction(function () {
            DB::execute("INSERT INTO test_users (name) VALUES (?)", ['Alice']);
            DB::execute("INSERT INTO test_users (name) VALUES (?)", ['Bob']);
            return 'transaction_completed';
        });

        $this->assertEquals('transaction_completed', $result);

        $users = DB::select("SELECT COUNT(*) as count FROM test_users");
        $this->assertEquals(2, $users[0]['count']);
    }

    public function test_transaction_rollback_on_exception(): void
    {
        DB::execute("CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT UNIQUE)");

        try {
            DB::transaction(function () {
                DB::execute("INSERT INTO test_users (name) VALUES (?)", ['Alice']);
                // This should cause a constraint violation and rollback
                DB::execute("INSERT INTO test_users (name) VALUES (?)", ['Alice']);
            });
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected exception due to unique constraint
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }

        // Verify the transaction was rolled back
        $users = DB::select("SELECT COUNT(*) as count FROM test_users");
        $this->assertEquals(0, $users[0]['count']);
    }

    public function test_supports_positional_parameters(): void
    {
        DB::execute("CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT, price REAL)");

        $affected = DB::execute(
            "INSERT INTO test_items (name, price) VALUES (?, ?)",
            ['Widget', 19.99]
        );

        $this->assertEquals(1, $affected);

        $items = DB::select("SELECT * FROM test_items WHERE price > ?", [10.0]);
        $this->assertCount(1, $items);
        $this->assertEquals('Widget', $items[0]['name']);
        $this->assertEquals(19.99, $items[0]['price']);
    }

    public function test_supports_named_parameters(): void
    {
        DB::execute("CREATE TABLE test_products (id INTEGER PRIMARY KEY, name TEXT, category TEXT)");

        $affected = DB::execute(
            "INSERT INTO test_products (name, category) VALUES (:name, :category)",
            [':name' => 'Laptop', ':category' => 'Electronics']
        );

        $this->assertEquals(1, $affected);

        $products = DB::select(
            "SELECT * FROM test_products WHERE category = :category",
            [':category' => 'Electronics']
        );

        $this->assertCount(1, $products);
        $this->assertEquals('Laptop', $products[0]['name']);
    }

    public function test_handles_different_data_types(): void
    {
        DB::execute("CREATE TABLE test_data (
            id INTEGER PRIMARY KEY,
            text_val TEXT,
            int_val INTEGER,
            real_val REAL,
            bool_val INTEGER,
            null_val TEXT
        )");

        DB::execute(
            "INSERT INTO test_data (text_val, int_val, real_val, bool_val, null_val) VALUES (?, ?, ?, ?, ?)",
            ['Hello', 42, 3.14, true, null]
        );

        $rows = DB::select("SELECT * FROM test_data");
        $row = $rows[0];

        $this->assertEquals('Hello', $row['text_val']);
        $this->assertEquals(42, $row['int_val']);
        $this->assertEquals(3.14, $row['real_val']);
        $this->assertEquals(1, $row['bool_val']); // SQLite stores boolean as integer
        $this->assertNull($row['null_val']);
    }

    public function test_update_and_delete_operations(): void
    {
        DB::execute("CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)");
        DB::execute("INSERT INTO test_users (name, status) VALUES ('Alice', 'active')");
        DB::execute("INSERT INTO test_users (name, status) VALUES ('Bob', 'inactive')");

        // Update operation
        $updated = DB::execute("UPDATE test_users SET status = ? WHERE name = ?", ['active', 'Bob']);
        $this->assertEquals(1, $updated);

        // Verify update
        $users = DB::select("SELECT * FROM test_users WHERE status = 'active' ORDER BY name");
        $this->assertCount(2, $users);

        // Delete operation
        $deleted = DB::execute("DELETE FROM test_users WHERE name = ?", ['Alice']);
        $this->assertEquals(1, $deleted);

        // Verify delete
        $remaining = DB::select("SELECT COUNT(*) as count FROM test_users");
        $this->assertEquals(1, $remaining[0]['count']);
    }
}
