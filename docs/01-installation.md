# Installation Guide

LeanPHP is a minimalist, high-performance PHP microframework designed for building secure JSON REST APIs. This guide will walk you through the complete installation and setup process, explaining each component and how they work together.

## 🎯 Overview

LeanPHP follows a clean architecture with these core principles:
- **Stateless Design**: No sessions, only JWT-based authentication
- **JSON-First**: All requests and responses use JSON format
- **Security by Default**: Built-in authentication, CORS, rate limiting, and input validation
- **High Performance**: Route caching, ETags, OpCache optimization
- **Developer Experience**: Clear error messages, debugging support, comprehensive testing

## 📋 Prerequisites

Before installing LeanPHP, ensure your system meets these requirements:

### System Requirements
- **PHP 8.2 or higher** (with strict types support)
- **Composer** (dependency manager)
- **Web server** (Apache, Nginx, or PHP built-in server for development)

### Required PHP Extensions
- `json` - For JSON encoding/decoding
- `pdo` - Database abstraction layer
- `pdo_sqlite` - SQLite database support (default)
- `openssl` - For JWT token generation and validation

### Optional PHP Extensions (Recommended)
- `apcu` - For high-performance rate limiting storage
- `opcache` - For better performance in production
- `pdo_mysql` - If you plan to use MySQL instead of SQLite

### Verify Prerequisites

Check your PHP version and extensions:
```bash
# Check PHP version
php --version

# Check installed extensions
php -m | grep -E "(json|pdo|openssl|apcu|opcache)"

# Check if Composer is installed
composer --version
```

## 🚀 Installation Steps

### Step 1: Clone or Download the Project

```bash
# Clone the repository
git clone <your-repo-url> lean-php
cd lean-php

# Or download and extract the ZIP file
```

### Step 2: Install Dependencies

LeanPHP uses Composer for dependency management. The framework has minimal dependencies:

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# For development (includes testing and analysis tools)
composer install
```

**What gets installed:**
- **Runtime Dependencies:**
  - PHP 8.2+ (framework requirement)

- **Development Dependencies:**
  - `vlucas/phpdotenv` - Environment variable management
  - `phpunit/phpunit` - Unit testing framework
  - `phpstan/phpstan` - Static analysis tool

### Step 3: Environment Configuration

LeanPHP uses environment variables for configuration, following the twelve-factor app methodology.

```bash
# Copy the example environment file
cp .env.example .env

# Edit the environment file with your settings
nano .env  # or use your preferred editor
```

**Environment Configuration Breakdown:**

#### Application Settings
```bash
# Application environment (development, production, testing)
APP_ENV=development

# Enable detailed error messages (disable in production)
APP_DEBUG=true

# Your application URL
APP_URL=http://localhost:8000

# Timezone for timestamps
APP_TIMEZONE=UTC

# Log file location
LOG_PATH=storage/logs/app.log
```

#### Database Configuration
```bash
# SQLite (default - recommended for development)
DB_DSN=sqlite:storage/database.sqlite
DB_USER=
DB_PASSWORD=
DB_ATTR_PERSISTENT=false

# MySQL alternative (uncomment and configure if needed)
# DB_DSN=mysql:host=localhost;dbname=leanphp;charset=utf8mb4
# DB_USER=your_username
# DB_PASSWORD=your_password
```

#### JWT Authentication
```bash
# Current key identifier for JWT signing
AUTH_JWT_CURRENT_KID=main

# JWT signing keys (CRITICAL: Change in production!)
# Format: key_id:base64url_encoded_secret
AUTH_JWT_KEYS=main:CHANGE_ME_TO_SECURE_BASE64URL_SECRET

# Token time-to-live in seconds (900 = 15 minutes)
AUTH_TOKEN_TTL=900
```

**🔒 Security Note:** Generate secure JWT keys for production:
```bash
# Generate a secure base64url-encoded secret
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='
```

#### CORS Configuration
```bash
# Allowed origins (* for all, comma-separated for specific)
CORS_ALLOW_ORIGINS=*

# Allowed HTTP methods
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS

# Allowed headers
CORS_ALLOW_HEADERS=Authorization,Content-Type

# Preflight cache duration in seconds
CORS_MAX_AGE=600

# Allow credentials (cookies, auth headers)
CORS_ALLOW_CREDENTIALS=false
```

#### Rate Limiting
```bash
# Storage backend (file or apcu)
RATE_LIMIT_STORE=file

# Default requests per window
RATE_LIMIT_DEFAULT=60

# Time window in seconds
RATE_LIMIT_WINDOW=60
```

### Step 4: Directory Structure Setup

LeanPHP creates several directories for storage and caching:

```bash
# Create required directories (if they don't exist)
mkdir -p storage/logs
mkdir -p storage/cache/routes
mkdir -p storage/cache/phpstan
mkdir -p storage/cache/phpunit
mkdir -p storage/ratelimit

# Set appropriate permissions (Linux/macOS)
chmod -R 755 storage/
chmod -R 777 storage/logs/
chmod -R 777 storage/cache/
chmod -R 777 storage/ratelimit/
```

**Directory Purpose:**
- `storage/logs/` - Application and test logs
- `storage/cache/` - Framework caches (routes, PHPStan, PHPUnit)
- `storage/ratelimit/` - Rate limiting data (when using file storage)
- `storage/` - SQLite database files

### Step 5: Database Initialization

LeanPHP includes a seeding script to set up the database schema and create demo data:

```bash
# Create SQLite database file
touch storage/database.sqlite

# Initialize database and create demo user
php scripts/seed.php
```

**What the seeder does:**
1. Creates a `users` table with proper schema
2. Creates a demo user account:
   - Email: `demo@example.com`
   - Password: `secret`
   - Scopes: `users.read,users.write` (full access)
3. Uses Argon2ID password hashing for security

**Database Schema Created:**
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,  -- Argon2ID hashed
    scopes TEXT DEFAULT 'users.read',  -- Comma-separated permissions
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Step 6: Web Server Configuration

#### Option A: PHP Built-in Server (Development)

The simplest option for development:

```bash
# Start the development server
php -S localhost:8000 -t public public/router.php

# Or use the Composer script
composer serve
```

#### Option B: Apache Configuration

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName lean-php.local
    DocumentRoot /path/to/lean-php/public

    <Directory /path/to/lean-php/public>
        AllowOverride All
        Require all granted

        # Enable URL rewriting
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
</VirtualHost>
```

#### Option C: Nginx Configuration

```nginx
server {
    listen 80;
    server_name lean-php.local;
    root /path/to/lean-php/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🧪 Verification and Testing

### Step 1: Test Basic Functionality

```bash
# Health check endpoint
curl http://localhost:8000/health

# Expected response:
# {
#   "status": "ok",
#   "timestamp": "2025-09-07T10:30:00+00:00",
#   "version": "1.0.0"
# }
```

### Step 2: Test Authentication

```bash
# Login with demo credentials
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"secret"}'

# Expected response includes JWT token:
# {
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
#   "expires_at": "2025-09-07T10:45:00+00:00",
#   "user": {...}
# }
```

### Step 3: Test Protected Endpoints

```bash
# Use the token from login response
export TOKEN="your-jwt-token-here"

# Access protected endpoint
curl http://localhost:8000/v1/users \
  -H "Authorization: Bearer $TOKEN"
```

### Step 4: Run Test Suite

```bash
# Run all tests
composer test

# Or run PHPUnit directly
./vendor/bin/phpunit

# Run static analysis
composer stan
```

## 🏗️ Architecture Overview

Understanding how LeanPHP works will help you extend and customize it:

### Request Lifecycle

1. **Entry Point** (`public/index.php`):
   - Loads Composer autoloader
   - Loads environment variables from `.env`
   - Loads application configuration
   - Creates HTTP Request object from PHP globals
   - Sets up middleware pipeline
   - Dispatches request through router

2. **Middleware Pipeline** (executed in order):
   - `ErrorHandler` - Catches exceptions and converts to JSON Problem responses
   - `RequestId` - Adds unique request ID for debugging
   - `Cors` - Handles CORS preflight and headers
   - `JsonBodyParser` - Parses JSON request bodies
   - Route-specific middleware (auth, rate limiting, etc.)

3. **Routing System**:
   - Pattern matching with parameter extraction
   - Parameter constraints (e.g., `{id:\d+}` for digits only)
   - Route groups with shared middleware
   - Route caching in production

4. **Response Handling**:
   - JSON-only responses with proper HTTP status codes
   - RFC 7807 Problem Details for errors
   - ETag generation for cacheable responses
   - CORS headers for cross-origin requests

### Core Components

#### HTTP Foundation
- **Request** (`src/Http/Request.php`): Immutable request object with helpers for headers, query params, JSON body, route parameters
- **Response** (`src/Http/Response.php`): Fluent response builder for JSON, text, and no-content responses
- **Problem** (`src/Http/Problem.php`): RFC 7807 Problem Details for consistent error responses

#### Routing System
- **Router** (`src/Routing/Router.php`): Pattern-based routing with middleware support and route caching
- Route parameters with optional constraints
- Route groups for shared configuration
- Automatic HEAD/OPTIONS method handling

#### Authentication & Authorization
- **Token** (`src/Auth/Token.php`): JWT implementation with HS256 signing and key rotation support
- **AuthBearer** middleware: Validates JWT tokens and extracts user claims
- **RequireScopes** middleware: Enforces role-based access control

#### Database Layer
- **DB** (`src/DB/DB.php`): Simple PDO wrapper with prepared statements and transaction support
- Environment-based configuration
- SQLite default with MySQL support

#### Validation System
- **Validator** (`src/Validation/Validator.php`): Fluent validation with built-in rules
- Automatic error responses with detailed field-level errors
- Custom validation messages and rules

#### Rate Limiting
- **Store** interface with File and APCu implementations
- **RateLimiter** middleware with configurable limits per endpoint
- Sliding window algorithm with proper headers

### Middleware System

LeanPHP uses a simple but powerful middleware system:

```php
// Middleware signature
public function handle(Request $request, callable $next): Response
{
    // Before logic
    $response = $next($request);  // Continue to next middleware/controller
    // After logic
    return $response;
}
```

**Built-in Middleware:**
- `ErrorHandler` - Exception handling and JSON error responses
- `RequestId` - Request tracking for debugging
- `Cors` - Cross-Origin Resource Sharing
- `JsonBodyParser` - JSON request body parsing
- `AuthBearer` - JWT authentication
- `RequireScopes` - Authorization based on token scopes
- `RateLimiter` - Request rate limiting
- `ETag` - HTTP caching with ETags

### Configuration System

LeanPHP uses environment variables for all configuration:

- **Environment Functions** (`src/Support/env.php`):
  - `env_string()` - Get string value with default
  - `env_bool()` - Get boolean value with default
  - `env_int()` - Get integer value with default

- **Configuration Loading** (`config/app.php`):
  - Centralized configuration using environment functions
  - Type-safe configuration values
  - Default values for development

## 🚀 Production Deployment

### Optimization Steps

1. **Install Production Dependencies:**
```bash
composer install --no-dev --optimize-autoloader
```

2. **Environment Configuration:**
```bash
# Set production environment
APP_ENV=production
APP_DEBUG=false

# Generate secure JWT keys
AUTH_JWT_KEYS=main:$(openssl rand -base64 32 | tr '+/' '-_' | tr -d '=')
```

3. **Enable OpCache:**
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; In production
```

4. **Generate Route Cache:**
```bash
php bin/route_cache.php
```

5. **File Permissions:**
```bash
# Secure file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 777 storage/
```

### Performance Considerations

- **Route Caching**: Automatically enabled in production (`APP_ENV=production`)
- **OpCache**: Highly recommended for production
- **APCu**: Use for rate limiting storage (`RATE_LIMIT_STORE=apcu`)
- **Database**: Consider MySQL/PostgreSQL for high-traffic applications
- **Web Server**: Use Apache/Nginx instead of PHP built-in server

## 🛠️ Development Tools

LeanPHP includes several development tools:

### Testing
```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration

# Test with coverage (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage/
```

### Static Analysis
```bash
# Run PHPStan (level 8 - strictest)
composer stan

# Check specific directory
./vendor/bin/phpstan analyse src/
```

### Development Server
```bash
# Start with automatic reloading
composer serve

# Custom host and port
php -S 0.0.0.0:8080 -t public public/router.php
```

## 🔧 Troubleshooting

### Common Issues

#### 1. "Class not found" errors
```bash
# Regenerate autoloader
composer dump-autoload
```

#### 2. Permission denied errors
```bash
# Fix storage permissions
chmod -R 777 storage/
```

#### 3. JWT verification failures
```bash
# Check JWT configuration
echo $AUTH_JWT_CURRENT_KID
echo $AUTH_JWT_KEYS
```

#### 4. Database connection errors
```bash
# Check database file permissions
ls -la storage/database.sqlite

# Recreate database
rm storage/database.sqlite
php scripts/seed.php
```

### Debug Mode

Enable debug mode for detailed error messages:
```bash
APP_DEBUG=true
```

### Logging

Check application logs for errors:
```bash
tail -f storage/logs/app.log
```

## 📚 Next Steps

After successful installation:

1. **Read the Documentation**: Explore the `docs/` directory for detailed guides
2. **Examine Examples**: Check `routes/api.php` for example endpoints
3. **Run Tests**: Study the test suite in `tests/` for usage examples
4. **Build Your API**: Start adding your own controllers and routes

### Recommended Reading Order:
1. [Project Structure](02-configuration.md) - Understanding the codebase organization
2. [HTTP Foundation](04-http-foundation.md) - Request/Response handling
3. [Routing](05-routing.md) - URL routing and middleware
4. [Authentication](07-auth.md) - JWT-based authentication
5. [Validation](08-validation.md) - Input validation and error handling
