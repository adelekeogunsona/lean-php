# Database System

LeanPHP provides a simple yet powerful database abstraction layer built on top of PDO. The system emphasizes security, simplicity, and performance while providing essential database operations with proper error handling and transaction support.

## Overview

The database system consists of:

- **DB Class**: Static interface for database operations
- **PDO Integration**: Full PDO compatibility with enhanced security
- **Connection Management**: Automatic connection handling and configuration
- **Transaction Support**: Safe transaction management with automatic rollback
- **Environment Configuration**: Flexible database configuration via environment variables

## DB Class (`src/DB/DB.php`)

The `DB` class provides a static interface for all database operations, wrapping PDO with additional security and convenience features.

### Connection Management

The database connection is managed automatically and configured through environment variables:

```bash
# SQLite (default)
DB_DSN="sqlite:storage/database.sqlite"

# MySQL
DB_DSN="mysql:host=localhost;dbname=myapp;charset=utf8mb4"
DB_USER="username"
DB_PASSWORD="password"

# PostgreSQL
DB_DSN="pgsql:host=localhost;dbname=myapp"
DB_USER="username"
DB_PASSWORD="password"

# Connection options
DB_ATTR_PERSISTENT=false  # Enable persistent connections
```

### Connection Features

1. **Lazy Connection**: Connection is established only when needed
2. **Error Mode**: Always uses `PDO::ERRMODE_EXCEPTION` for consistent error handling
3. **Fetch Mode**: Defaults to `PDO::FETCH_ASSOC` for associative arrays
4. **Prepared Statements**: All queries use prepared statements (no emulation)
5. **SQLite Optimization**: Automatically enables foreign key constraints for SQLite

### Database Operations

#### Select Queries

The `select()` method executes SELECT queries and returns all results as associative arrays:

```php
use LeanPHP\DB\DB;

// Simple select
$users = DB::select('SELECT * FROM users');
// Returns: [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']]

// Select with parameters (positional)
$users = DB::select('SELECT * FROM users WHERE age > ?', [18]);

// Select with named parameters
$users = DB::select('SELECT * FROM users WHERE status = :status AND age > :age', [
    ':status' => 'active',
    ':age' => 18
]);

// Single user by ID
$users = DB::select('SELECT * FROM users WHERE id = ?', [123]);
$user = $users[0] ?? null; // Get first result or null
```

#### Execute Queries (INSERT, UPDATE, DELETE)

The `execute()` method handles queries that modify data and returns the number of affected rows:

```php
// Insert new record
$affectedRows = DB::execute('
    INSERT INTO users (name, email, password, created_at)
    VALUES (?, ?, ?, ?)
', ['John Doe', 'john@example.com', $hashedPassword, date('Y-m-d H:i:s')]);

// Update existing record
$affectedRows = DB::execute('
    UPDATE users SET name = ?, updated_at = ?
    WHERE id = ?
', ['John Smith', date('Y-m-d H:i:s'), 123]);

// Delete record
$affectedRows = DB::execute('DELETE FROM users WHERE id = ?', [123]);

// Bulk operations
$affectedRows = DB::execute('UPDATE users SET status = ? WHERE last_login < ?', [
    'inactive',
    date('Y-m-d H:i:s', strtotime('-90 days'))
]);
```

#### Transactions

The `transaction()` method provides safe transaction handling with automatic rollback on exceptions:

```php
use LeanPHP\DB\DB;

try {
    $result = DB::transaction(function () {
        // Insert user
        DB::execute('
            INSERT INTO users (name, email, password)
            VALUES (?, ?, ?)
        ', ['John Doe', 'john@example.com', $hashedPassword]);

        // Get the user ID (SQLite)
        $users = DB::select('SELECT last_insert_rowid() as id');
        $userId = $users[0]['id'];

        // Insert user profile
        DB::execute('
            INSERT INTO user_profiles (user_id, bio, website)
            VALUES (?, ?, ?)
        ', [$userId, 'Software Developer', 'https://johndoe.com']);

        // Insert user permissions
        DB::execute('
            INSERT INTO user_permissions (user_id, permission)
            VALUES (?, ?)
        ', [$userId, 'users.read']);

        return $userId;
    });

    echo "User created with ID: $result";
} catch (\Exception $e) {
    // Transaction was automatically rolled back
    echo "Failed to create user: " . $e->getMessage();
}
```

### Parameter Binding

The DB class automatically handles parameter binding with proper type detection:

#### Supported Parameter Types

```php
// String parameters
DB::select('SELECT * FROM users WHERE name = ?', ['John Doe']);

// Integer parameters
DB::select('SELECT * FROM users WHERE age = ?', [25]);

// Boolean parameters
DB::select('SELECT * FROM users WHERE active = ?', [true]); // Becomes 1
DB::select('SELECT * FROM users WHERE active = ?', [false]); // Becomes 0

// Null parameters
DB::select('SELECT * FROM users WHERE deleted_at IS ?', [null]); // Becomes NULL

// Mixed parameters
DB::execute('
    INSERT INTO users (name, age, active, notes)
    VALUES (?, ?, ?, ?)
', ['John', 25, true, null]);
```

#### Named Parameters

```php
// Named parameters provide better readability
DB::select('
    SELECT * FROM users
    WHERE status = :status
    AND created_at BETWEEN :start_date AND :end_date
    ORDER BY :sort_column
', [
    ':status' => 'active',
    ':start_date' => '2023-01-01',
    ':end_date' => '2023-12-31',
    ':sort_column' => 'name' // Note: Column names should be whitelisted in real applications
]);
```

### Advanced Usage

#### Direct PDO Access

For advanced operations, you can access the underlying PDO connection:

```php
$pdo = DB::getPdo();

// Use PDO directly
$statement = $pdo->prepare('SELECT COUNT(*) FROM users');
$statement->execute();
$count = $statement->fetchColumn();

// Use PDO-specific features
$pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
```

#### Connection Reset

Useful for testing or when you need to reset the connection:

```php
// Reset connection (useful for testing)
DB::resetConnection();

// Next DB operation will create a new connection
$users = DB::select('SELECT * FROM users');
```

## Database Configuration

### Environment Variables

The database system uses environment variables for configuration:

```bash
# Database DSN (Data Source Name)
DB_DSN="sqlite:storage/database.sqlite"

# Database credentials (for MySQL/PostgreSQL)
DB_USER="username"
DB_PASSWORD="password"

# Connection options
DB_ATTR_PERSISTENT=false
```

### DSN Examples

**SQLite (File-based):**
```bash
DB_DSN="sqlite:storage/database.sqlite"
DB_DSN="sqlite:/absolute/path/to/database.sqlite"
```

**SQLite (In-memory):**
```bash
DB_DSN="sqlite::memory:"
```

**MySQL:**
```bash
DB_DSN="mysql:host=localhost;dbname=myapp;charset=utf8mb4"
DB_USER="myuser"
DB_PASSWORD="mypassword"
```

**PostgreSQL:**
```bash
DB_DSN="pgsql:host=localhost;port=5432;dbname=myapp"
DB_USER="myuser"
DB_PASSWORD="mypassword"
```

### Configuration Helper (`src/Support/env.php`)

The environment helper functions provide type-safe access to configuration:

```php
// Get string value with default
$dsn = env_string('DB_DSN', 'sqlite::memory:');

// Get boolean value with default
$persistent = env_bool('DB_ATTR_PERSISTENT', false);

// Get integer value with default
$timeout = env_int('DB_TIMEOUT', 30);
```

## Database Schema and Migrations

### User Table Schema

Based on the codebase analysis, here's the standard user table schema:

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    scopes TEXT DEFAULT 'users.read',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_created_at ON users(created_at);
```

### Database Seeding

The framework includes a seeding script (`scripts/seed.php`) for setting up initial data:

```php
// Create demo user with secure password hashing
$hashedPassword = password_hash('secret', PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,  // 64 MB
    'time_cost' => 4,        // 4 iterations
    'threads' => 3,          // 3 threads
]);

DB::execute('
    INSERT INTO users (name, email, password, scopes)
    VALUES (?, ?, ?, ?)
', [
    'Demo User',
    'demo@example.com',
    $hashedPassword,
    'users.read,users.write'
]);
```

## Usage in Controllers

### Basic CRUD Operations

```php
class UserController
{
    public function index(Request $request): Response
    {
        // Get pagination parameters
        $limit = min((int) ($request->query('limit') ?? 20), 100);
        $offset = (int) ($request->query('offset') ?? 0);

        // Get total count for pagination
        $totalResult = DB::select('SELECT COUNT(*) as count FROM users');
        $total = (int) $totalResult[0]['count'];

        // Get paginated users
        $users = DB::select('
            SELECT id, name, email, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ', [$limit, $offset]);

        return Response::json([
            'users' => $users,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($users),
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->params()['id'];

        $users = DB::select('
            SELECT id, name, email, created_at
            FROM users
            WHERE id = ?
        ', [$id]);

        if (empty($users)) {
            return Problem::make(404, 'Not Found', "User {$id} not found");
        }

        return Response::json(['user' => $users[0]]);
    }

    public function create(Request $request): Response
    {
        $data = $request->json() ?? [];

        // Validate input (covered in validation docs)
        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        $validator->validate();

        // Check for duplicate email
        $existing = DB::select('SELECT id FROM users WHERE email = ?', [$data['email']]);
        if (!empty($existing)) {
            return Problem::make(422, 'Validation Failed', 'Email already exists');
        }

        // Hash password securely
        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);

        // Insert new user
        DB::execute('
            INSERT INTO users (name, email, password, scopes, created_at)
            VALUES (?, ?, ?, ?, datetime("now"))
        ', [
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['scopes'] ?? 'users.read',
        ]);

        // Get the created user
        $users = DB::select('
            SELECT id, name, email, scopes, created_at
            FROM users
            WHERE email = ?
        ', [$data['email']]);

        return Response::json(['user' => $users[0]], 201);
    }
}
```

### Authentication Integration

```php
class AuthController
{
    public function login(Request $request): Response
    {
        $data = $request->json() ?? [];

        // Find user by email
        $users = DB::select('SELECT * FROM users WHERE email = ?', [$data['email']]);

        if (empty($users)) {
            return Problem::make(401, 'Unauthorized', 'Invalid credentials');
        }

        $user = $users[0];

        // Verify password
        if (!password_verify($data['password'], $user['password'])) {
            return Problem::make(401, 'Unauthorized', 'Invalid credentials');
        }

        // Parse user scopes
        $scopes = !empty($user['scopes']) ? explode(',', $user['scopes']) : ['users.read'];

        // Generate JWT token (covered in auth docs)
        $claims = [
            'sub' => (string) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'scopes' => $scopes,
        ];

        $token = Token::issue($claims);

        return Response::json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'scopes' => $scopes,
            ],
        ]);
    }
}
```

### Complex Queries and Transactions

```php
class OrderController
{
    public function createOrder(Request $request): Response
    {
        $data = $request->json();
        $userId = $request->claim('sub');

        try {
            $orderId = DB::transaction(function () use ($data, $userId) {
                // Create order
                DB::execute('
                    INSERT INTO orders (user_id, total_amount, status, created_at)
                    VALUES (?, ?, ?, datetime("now"))
                ', [$userId, $data['total'], 'pending']);

                // Get order ID
                $orders = DB::select('SELECT last_insert_rowid() as id');
                $orderId = $orders[0]['id'];

                // Insert order items
                foreach ($data['items'] as $item) {
                    DB::execute('
                        INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (?, ?, ?, ?)
                    ', [$orderId, $item['product_id'], $item['quantity'], $item['price']]);

                    // Update product inventory
                    DB::execute('
                        UPDATE products
                        SET stock_quantity = stock_quantity - ?
                        WHERE id = ? AND stock_quantity >= ?
                    ', [$item['quantity'], $item['product_id'], $item['quantity']]);
                }

                return $orderId;
            });

            return Response::json(['order_id' => $orderId], 201);

        } catch (\Exception $e) {
            return Problem::make(500, 'Order Failed', 'Could not create order');
        }
    }
}
```

## Security Considerations

### SQL Injection Prevention

The DB class prevents SQL injection through several mechanisms:

1. **Prepared Statements**: All queries use prepared statements
2. **Parameter Binding**: Parameters are bound with appropriate types
3. **No String Interpolation**: Never interpolate user input directly into SQL

```php
// ✅ SAFE - Uses prepared statements
$users = DB::select('SELECT * FROM users WHERE name = ?', [$userName]);

// ❌ UNSAFE - Direct string interpolation
$users = DB::select("SELECT * FROM users WHERE name = '$userName'");

// ✅ SAFE - Named parameters
DB::execute('UPDATE users SET email = :email WHERE id = :id', [
    ':email' => $newEmail,
    ':id' => $userId
]);
```

### Data Type Safety

```php
// Automatic type detection and binding
DB::execute('
    INSERT INTO users (name, age, active, salary, notes)
    VALUES (?, ?, ?, ?, ?)
', [
    'John Doe',    // String -> PDO::PARAM_STR
    25,            // Integer -> PDO::PARAM_INT
    true,          // Boolean -> PDO::PARAM_BOOL
    50000.50,      // Float -> PDO::PARAM_STR (safe for decimal precision)
    null           // Null -> PDO::PARAM_NULL
]);
```

### Password Security

```php
// Use strong password hashing
$hashedPassword = password_hash($plainPassword, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,  // 64 MB
    'time_cost' => 4,        // 4 iterations
    'threads' => 3,          // 3 threads
]);

// Store hashed password
DB::execute('INSERT INTO users (email, password) VALUES (?, ?)', [
    $email,
    $hashedPassword
]);

// Verify password
$users = DB::select('SELECT password FROM users WHERE email = ?', [$email]);
if (!empty($users) && password_verify($inputPassword, $users[0]['password'])) {
    // Password is correct
}
```

## Error Handling

### Database Exceptions

```php
try {
    $users = DB::select('SELECT * FROM nonexistent_table');
} catch (PDOException $e) {
    // Handle database errors
    error_log('Database error: ' . $e->getMessage());
    return Problem::make(500, 'Database Error', 'An unexpected error occurred');
}

try {
    DB::execute('INSERT INTO users (email) VALUES (?)', ['duplicate@example.com']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // Integrity constraint violation
        return Problem::make(422, 'Validation Failed', 'Email already exists');
    }
    throw $e; // Re-throw other errors
}
```

### Connection Errors

```php
try {
    $users = DB::select('SELECT * FROM users');
} catch (RuntimeException $e) {
    // Handle connection errors
    error_log('Database connection failed: ' . $e->getMessage());
    return Problem::make(503, 'Service Unavailable', 'Database is temporarily unavailable');
}
```

## Performance Optimization

### Connection Pooling

```bash
# Enable persistent connections for better performance
DB_ATTR_PERSISTENT=true
```

### Query Optimization

```php
// Use indexes for WHERE clauses
DB::select('SELECT * FROM users WHERE email = ?', [$email]); // email should be indexed

// Limit result sets
DB::select('SELECT * FROM users ORDER BY created_at DESC LIMIT ?', [50]);

// Use covering indexes when possible
DB::select('SELECT id, email FROM users WHERE status = ?', ['active']);

// Avoid SELECT * in production
DB::select('SELECT id, name, email FROM users WHERE active = ?', [true]);
```

### Batch Operations

```php
// Batch inserts for better performance
DB::transaction(function () use ($userData) {
    foreach ($userData as $user) {
        DB::execute('INSERT INTO users (name, email) VALUES (?, ?)', [
            $user['name'],
            $user['email']
        ]);
    }
});
```

## Testing Database Operations

### Test Configuration

```php
class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        // Use in-memory SQLite for tests
        putenv('DB_DSN=sqlite::memory:');

        // Reset connection for each test
        DB::resetConnection();

        // Create test schema
        DB::execute('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL
            )
        ');
    }

    public function testUserCreation(): void
    {
        $affectedRows = DB::execute('
            INSERT INTO users (name, email) VALUES (?, ?)
        ', ['John Doe', 'john@example.com']);

        $this->assertEquals(1, $affectedRows);

        $users = DB::select('SELECT * FROM users WHERE email = ?', ['john@example.com']);
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]['name']);
    }
}
```

### Transaction Testing

```php
public function testTransactionRollback(): void
{
    try {
        DB::transaction(function () {
            DB::execute('INSERT INTO users (name, email) VALUES (?, ?)',
                ['User 1', 'user1@example.com']);

            // This will cause a constraint violation
            DB::execute('INSERT INTO users (name, email) VALUES (?, ?)',
                ['User 2', 'user1@example.com']); // Duplicate email
        });

        $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
        // Transaction should be rolled back
        $users = DB::select('SELECT COUNT(*) as count FROM users');
        $this->assertEquals(0, $users[0]['count']);
    }
}
```

## Best Practices

### 1. Use Prepared Statements Always

```php
// ✅ Always use parameter binding
$users = DB::select('SELECT * FROM users WHERE status = ?', [$status]);

// ❌ Never use direct string concatenation
$users = DB::select("SELECT * FROM users WHERE status = '$status'");
```

### 2. Handle Errors Appropriately

```php
try {
    $result = DB::transaction(function () {
        // Database operations
    });
} catch (PDOException $e) {
    // Log the error with context
    error_log('Transaction failed: ' . $e->getMessage(), 0);

    // Return user-friendly error
    return Problem::make(500, 'Operation Failed', 'Unable to complete request');
}
```

### 3. Use Transactions for Related Operations

```php
// ✅ Group related operations in transactions
DB::transaction(function () {
    DB::execute('INSERT INTO orders ...');
    DB::execute('INSERT INTO order_items ...');
    DB::execute('UPDATE inventory ...');
});

// ❌ Don't leave related operations unprotected
DB::execute('INSERT INTO orders ...');
DB::execute('INSERT INTO order_items ...'); // Could fail, leaving orphaned order
```

### 4. Optimize Query Performance

```php
// ✅ Select only needed columns
$users = DB::select('SELECT id, name, email FROM users WHERE active = ?', [true]);

// ✅ Use appropriate LIMIT clauses
$recentUsers = DB::select('SELECT * FROM users ORDER BY created_at DESC LIMIT ?', [10]);

// ✅ Use indexes for WHERE/ORDER BY clauses
$users = DB::select('SELECT * FROM users WHERE email = ? ORDER BY created_at', [$email]);
```

### 5. Separate Data Access Logic

```php
class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
        return $users[0] ?? null;
    }

    public function create(array $userData): int
    {
        DB::execute('
            INSERT INTO users (name, email, password, created_at)
            VALUES (?, ?, ?, ?)
        ', [
            $userData['name'],
            $userData['email'],
            $userData['password'],
            date('Y-m-d H:i:s')
        ]);

        $result = DB::select('SELECT last_insert_rowid() as id');
        return (int) $result[0]['id'];
    }
}
```
