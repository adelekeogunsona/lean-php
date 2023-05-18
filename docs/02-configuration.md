# Configuration System

LeanPHP uses a robust, environment-based configuration system that follows the [Twelve-Factor App](https://12factor.net/config) methodology. All configuration is handled through environment variables, making it easy to deploy across different environments without code changes.

## 🎯 Configuration Philosophy

LeanPHP's configuration system is built on these principles:

- **Environment Variables Only**: No configuration files to manage across environments
- **Type Safety**: Built-in type conversion with safe defaults
- **Runtime Flexibility**: Configuration can be changed without code modifications
- **Security First**: Sensitive data (JWT keys, DB passwords) never stored in code
- **Development Friendly**: Sensible defaults for quick setup

## 🏗️ Architecture Overview

### Environment Helper Functions

LeanPHP provides three core helper functions for accessing configuration:

```php
// Get string values
function env_string(string $key, ?string $default = null): ?string

// Get boolean values
function env_bool(string $key, ?bool $default = null): ?bool

// Get integer values
function env_int(string $key, ?int $default = null): ?int
```

**Location**: `src/Support/env.php`

These functions check for environment variables in this order:
1. `$_ENV` superglobal
2. `$_SERVER` superglobal
3. `getenv()` function
4. Return default value if not found

### Configuration Loading

The main application configuration is centralized in `config/app.php`:

```php
<?php
return [
    'env' => env_string('APP_ENV', 'development'),
    'debug' => env_bool('APP_DEBUG', true),
    'url' => env_string('APP_URL', 'http://localhost:8000'),
    'timezone' => env_string('APP_TIMEZONE', 'UTC'),
    'log_path' => env_string('LOG_PATH', 'storage/logs/app.log'),
];
```

This configuration array is loaded once during bootstrap and used throughout the application.

## 📋 Configuration Categories

### Application Settings

Core application behavior configuration:

#### `APP_ENV`
- **Type**: String
- **Default**: `development`
- **Values**: `development`, `production`, `testing`
- **Purpose**: Controls environment-specific behavior
- **Impact**:
  - Production mode enables route caching
  - Development mode shows detailed errors
  - Testing mode uses in-memory database

```bash
# Development (default)
APP_ENV=development

# Production
APP_ENV=production

# Testing
APP_ENV=testing
```

#### `APP_DEBUG`
- **Type**: Boolean
- **Default**: `true`
- **Purpose**: Controls error detail visibility
- **Security**: Must be `false` in production

```bash
# Show detailed errors (development)
APP_DEBUG=true

# Hide error details (production)
APP_DEBUG=false
```

**Boolean Value Parsing**:
- `true`: `"true"`, `"1"`, `"yes"`, `"on"`
- `false`: `"false"`, `"0"`, `"no"`, `"off"`, `""`

#### `APP_URL`
- **Type**: String
- **Default**: `http://localhost:8000`
- **Purpose**: Base URL for the application
- **Usage**: URL generation, CORS origins

```bash
# Development
APP_URL=http://localhost:8000

# Production
APP_URL=https://api.example.com
```

#### `APP_TIMEZONE`
- **Type**: String
- **Default**: `UTC`
- **Purpose**: Sets PHP default timezone
- **Values**: Any valid PHP timezone identifier

```bash
# UTC (recommended for APIs)
APP_TIMEZONE=UTC

# Regional examples
APP_TIMEZONE=America/New_York
APP_TIMEZONE=Europe/London
APP_TIMEZONE=Asia/Tokyo
```

#### `LOG_PATH`
- **Type**: String
- **Default**: `storage/logs/app.log`
- **Purpose**: Application log file location
- **Special Values**: `php://stderr`, `php://stdout` for containerized environments

```bash
# File logging (default)
LOG_PATH=storage/logs/app.log

# Container logging
LOG_PATH=php://stderr
```

### Database Configuration

Database connection settings using PDO:

#### `DB_DSN`
- **Type**: String
- **Default**: `sqlite:storage/database.sqlite`
- **Purpose**: PDO Data Source Name
- **Formats**: Various database types supported

```bash
# SQLite (default for development)
DB_DSN=sqlite:storage/database.sqlite

# In-memory SQLite (testing)
DB_DSN=sqlite::memory:

# MySQL
DB_DSN=mysql:host=localhost;dbname=leanphp;charset=utf8mb4

# PostgreSQL
DB_DSN=pgsql:host=localhost;dbname=leanphp;charset=utf8
```

#### `DB_USER` & `DB_PASSWORD`
- **Type**: String
- **Default**: Empty strings
- **Purpose**: Database authentication credentials
- **Security**: Use strong passwords in production

```bash
# SQLite (no credentials needed)
DB_USER=
DB_PASSWORD=

# MySQL/PostgreSQL
DB_USER=api_user
DB_PASSWORD=secure_random_password_here
```

#### `DB_ATTR_PERSISTENT`
- **Type**: Boolean
- **Default**: `false`
- **Purpose**: Enable persistent database connections
- **Performance**: Can improve performance but uses more memory

```bash
# Standard connections (default)
DB_ATTR_PERSISTENT=false

# Persistent connections (production optimization)
DB_ATTR_PERSISTENT=true
```

### JWT Authentication

JSON Web Token configuration for stateless authentication:

#### `AUTH_JWT_CURRENT_KID`
- **Type**: String
- **Required**: Yes
- **Purpose**: Key identifier for token signing
- **Security**: Enables key rotation without breaking existing tokens

```bash
# Current signing key identifier
AUTH_JWT_CURRENT_KID=main
```

#### `AUTH_JWT_KEYS`
- **Type**: String (comma-separated key:value pairs)
- **Required**: Yes
- **Format**: `kid1:secret1,kid2:secret2`
- **Purpose**: JWT signing keys with rotation support
- **Security**: Use base64url-encoded secrets

```bash
# Single key
AUTH_JWT_KEYS=main:dGVzdC1rZXktZm9yLWp3dC1zaWduaW5nLTEyMzQ1Njc4OTA

# Multiple keys for rotation
AUTH_JWT_KEYS=main:new_secret_key,old:previous_secret_key
```

**Key Generation**:
```bash
# Generate secure base64url-encoded secret
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='
```

#### `AUTH_TOKEN_TTL`
- **Type**: Integer
- **Default**: `900` (15 minutes)
- **Purpose**: Token time-to-live in seconds
- **Security**: Shorter TTL = better security, more frequent renewals needed

```bash
# 15 minutes (default)
AUTH_TOKEN_TTL=900

# 1 hour
AUTH_TOKEN_TTL=3600

# 24 hours (not recommended for production)
AUTH_TOKEN_TTL=86400
```

### CORS Configuration

Cross-Origin Resource Sharing settings for web browser security:

#### `CORS_ALLOW_ORIGINS`
- **Type**: String (comma-separated)
- **Default**: `*`
- **Purpose**: Allowed request origins
- **Security**: Use specific origins in production

```bash
# Allow all origins (development only)
CORS_ALLOW_ORIGINS=*

# Specific origins (production)
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com

# Multiple environments
CORS_ALLOW_ORIGINS=http://localhost:3000,https://staging.example.com
```

#### `CORS_ALLOW_METHODS`
- **Type**: String (comma-separated)
- **Default**: `GET,POST,PUT,PATCH,DELETE,OPTIONS`
- **Purpose**: Allowed HTTP methods

```bash
# Full REST API (default)
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS

# Read-only API
CORS_ALLOW_METHODS=GET,OPTIONS

# Custom methods
CORS_ALLOW_METHODS=GET,POST,PATCH,OPTIONS
```

#### `CORS_ALLOW_HEADERS`
- **Type**: String (comma-separated)
- **Default**: `Authorization,Content-Type`
- **Purpose**: Allowed request headers

```bash
# Standard API headers (default)
CORS_ALLOW_HEADERS=Authorization,Content-Type

# Extended headers
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-Requested-With,Accept

# Custom headers
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-API-Key,X-Client-Version
```

#### `CORS_MAX_AGE`
- **Type**: Integer
- **Default**: `600` (10 minutes)
- **Purpose**: Preflight cache duration in seconds
- **Performance**: Longer cache = fewer preflight requests

```bash
# 10 minutes (default)
CORS_MAX_AGE=600

# 1 hour
CORS_MAX_AGE=3600

# 24 hours (maximum recommended)
CORS_MAX_AGE=86400
```

#### `CORS_ALLOW_CREDENTIALS`
- **Type**: Boolean
- **Default**: `false`
- **Purpose**: Allow cookies and authorization headers
- **Security**: Requires specific origins (not `*`)

```bash
# No credentials (default, more secure)
CORS_ALLOW_CREDENTIALS=false

# Allow credentials (requires specific origins)
CORS_ALLOW_CREDENTIALS=true
```

### Rate Limiting

Request rate limiting configuration to prevent abuse:

#### `RATE_LIMIT_STORE`
- **Type**: String
- **Default**: `file`
- **Values**: `file`, `apcu`
- **Purpose**: Storage backend for rate limit data
- **Performance**: APCu is faster but requires extension

```bash
# File storage (default, no dependencies)
RATE_LIMIT_STORE=file

# APCu storage (faster, requires apcu extension)
RATE_LIMIT_STORE=apcu
```

**Storage Backend Selection Logic**:
```php
// Automatic fallback if APCu not available
return match ($storeType) {
    'apcu' => extension_loaded('apcu') ? new ApcuStore() : new FileStore(),
    'file' => new FileStore(),
    default => throw new \InvalidArgumentException("Unsupported store: {$storeType}")
};
```

#### `RATE_LIMIT_DEFAULT`
- **Type**: Integer
- **Default**: `60`
- **Purpose**: Default requests per time window
- **Scope**: Applied to all rate-limited endpoints

```bash
# 60 requests per window (default)
RATE_LIMIT_DEFAULT=60

# More restrictive
RATE_LIMIT_DEFAULT=30

# More permissive
RATE_LIMIT_DEFAULT=120
```

#### `RATE_LIMIT_WINDOW`
- **Type**: Integer
- **Default**: `60` (1 minute)
- **Purpose**: Time window in seconds for rate limiting
- **Algorithm**: Sliding window implementation

```bash
# 1 minute window (default)
RATE_LIMIT_WINDOW=60

# 5 minute window
RATE_LIMIT_WINDOW=300

# 1 hour window
RATE_LIMIT_WINDOW=3600
```

**Rate Limiting Logic**:
- Authenticated users: Limited by user ID (`user:{user_id}`)
- Anonymous users: Limited by IP address (`ip:{ip_address}`)
- Sliding window algorithm with automatic cleanup
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

## 🔧 Environment File Management

### `.env` File Structure

LeanPHP uses the `vlucas/phpdotenv` package to load environment variables from `.env` files:

```bash
# Application Settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
LOG_PATH=storage/logs/app.log

# Database Configuration
DB_DSN=sqlite:storage/database.sqlite
DB_USER=
DB_PASSWORD=
DB_ATTR_PERSISTENT=false

# JWT Authentication
AUTH_JWT_CURRENT_KID=main
AUTH_JWT_KEYS=main:CHANGE_ME_TO_SECURE_BASE64URL_SECRET
AUTH_TOKEN_TTL=900

# CORS Configuration
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type
CORS_MAX_AGE=600
CORS_ALLOW_CREDENTIALS=false

# Rate Limiting
RATE_LIMIT_STORE=file
RATE_LIMIT_DEFAULT=60
RATE_LIMIT_WINDOW=60
```

### Environment Loading Logic

Environment variables are loaded during application bootstrap in `public/index.php`:

```php
// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}
```

**Loading Priority**:
1. System environment variables (highest priority)
2. `.env` file variables
3. Default values in code (lowest priority)

### Environment File Security

**Development**:
- Use `.env` file for local configuration
- Include `.env.example` with safe defaults
- Add `.env` to `.gitignore` to prevent committing secrets

**Production**:
- Use system environment variables instead of `.env` files
- Store secrets in secure configuration management systems
- Never commit production secrets to version control

## 🏭 Environment-Specific Configurations

### Development Environment

Optimized for developer experience and debugging:

```bash
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_DSN=sqlite:storage/database.sqlite
AUTH_JWT_KEYS=main:development_key_not_for_production
CORS_ALLOW_ORIGINS=*
RATE_LIMIT_DEFAULT=1000
```

**Features**:
- Detailed error messages
- SQLite database for simplicity
- Permissive CORS settings
- High rate limits
- Route caching disabled for live reloading

### Testing Environment

Configured for automated testing and CI/CD:

```bash
APP_ENV=testing
APP_DEBUG=true
DB_DSN=sqlite::memory:
LOG_PATH=php://stderr
AUTH_JWT_CURRENT_KID=test
AUTH_JWT_KEYS=test:dGVzdC1rZXktZm9yLWp3dC1zaWduaW5nLTEyMzQ1Njc4OTA
RATE_LIMIT_DEFAULT=5
```

**Features**:
- In-memory database for speed
- Logging to stderr for test output
- Fixed JWT keys for predictable tests
- Lower rate limits to test limiting behavior

### Production Environment

Optimized for security, performance, and stability:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_DSN=mysql:host=db-host;dbname=api_production;charset=utf8mb4
DB_USER=api_production_user
DB_PASSWORD=complex_secure_password_here
AUTH_JWT_CURRENT_KID=prod_2024_09
AUTH_JWT_KEYS=prod_2024_09:securely_generated_base64url_key
AUTH_TOKEN_TTL=900
CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_CREDENTIALS=false
RATE_LIMIT_STORE=apcu
RATE_LIMIT_DEFAULT=60
```

**Features**:
- Error details hidden for security
- Production database (MySQL/PostgreSQL)
- Secure JWT keys with rotation strategy
- Specific CORS origins
- APCu for high-performance rate limiting
- Route caching enabled automatically

## 🔍 Configuration Validation

### Runtime Validation

LeanPHP validates critical configuration at runtime:

**JWT Configuration**:
```php
// Token.php
$currentKid = \env_string('AUTH_JWT_CURRENT_KID');
if (!$currentKid) {
    throw new RuntimeException('AUTH_JWT_CURRENT_KID environment variable not set');
}

$keys = self::parseJwtKeys();
if (!isset($keys[$currentKid])) {
    throw new RuntimeException("Current JWT key '$currentKid' not found in AUTH_JWT_KEYS");
}
```

**Database Configuration**:
```php
// DB.php
$dsn = \env_string('DB_DSN', 'sqlite::memory:') ?? 'sqlite::memory:';
// PDO constructor will throw exception for invalid DSN
```

### Configuration Testing

Test configuration handling with PHPUnit:

```php
// tests/Unit/ConfigTest.php
public function test_config_loads_environment_values(): void
{
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['APP_DEBUG'] = 'false';

    $config = require __DIR__ . '/../../config/app.php';

    $this->assertEquals('testing', $config['env']);
    $this->assertFalse($config['debug']);
}
```

## ⚡ Performance Considerations

### Configuration Caching

**Route Caching** (Production Only):
- Enabled automatically when `APP_ENV=production`
- Routes compiled to PHP array for faster loading
- Cache location: `storage/cache/routes.php`
- Generated via: `php bin/route_cache.php`

**OpCache Integration**:
```ini
; php.ini - Production optimization
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Disable in production
```

### Memory Usage

**APCu vs File Storage**:
- **APCu**: Faster, in-memory, but requires extension
- **File**: Slower, disk-based, but no dependencies
- **Automatic Fallback**: Uses file storage if APCu unavailable

**Persistent Connections**:
```bash
# Enable for high-traffic production
DB_ATTR_PERSISTENT=true
```

## 🔒 Security Best Practices

### Secret Management

**Development**:
```bash
# Use weak secrets for development (never in production)
AUTH_JWT_KEYS=main:development_secret_change_in_production
```

**Production**:
```bash
# Generate cryptographically secure secrets
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='
```

### Key Rotation Strategy

**Graceful Key Rotation**:
```bash
# Step 1: Add new key while keeping old
AUTH_JWT_CURRENT_KID=new_key_2024_09
AUTH_JWT_KEYS=new_key_2024_09:new_secret,old_key_2024_08:old_secret

# Step 2: After token expiry, remove old key
AUTH_JWT_KEYS=new_key_2024_09:new_secret
```

### CORS Security

**Development** (Permissive):
```bash
CORS_ALLOW_ORIGINS=*
CORS_ALLOW_CREDENTIALS=false
```

**Production** (Restrictive):
```bash
CORS_ALLOW_ORIGINS=https://app.example.com
CORS_ALLOW_CREDENTIALS=true  # Only with specific origins
```

## 🛠️ Development Tools

### Configuration Debugging

**View Current Configuration**:
```php
// Add to any controller for debugging
$config = require __DIR__ . '/../../config/app.php';
var_dump($config);
```

**Environment Variable Inspection**:
```bash
# Check specific variables
echo $APP_ENV
echo $AUTH_JWT_CURRENT_KID

# View all environment variables
printenv | grep -E "(APP_|AUTH_|DB_|CORS_|RATE_)"
```

### Validation Scripts

**Create configuration validator**:
```php
// scripts/validate-config.php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$required = ['AUTH_JWT_CURRENT_KID', 'AUTH_JWT_KEYS'];
$missing = [];

foreach ($required as $key) {
    if (!env_string($key)) {
        $missing[] = $key;
    }
}

if ($missing) {
    echo "Missing required environment variables: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "Configuration validation passed!\n";
```

## 🚀 Deployment Configurations

### Container Deployments

**Docker Environment Variables**:
```dockerfile
# Dockerfile
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DB_DSN=mysql:host=db;dbname=api;charset=utf8mb4
```

**docker-compose.yml**:
```yaml
services:
  api:
    environment:
      - APP_ENV=production
      - DB_DSN=mysql:host=db;dbname=api;charset=utf8mb4
      - AUTH_JWT_KEYS=${JWT_KEYS}  # From .env file
```

### Cloud Platform Examples

**Heroku**:
```bash
heroku config:set APP_ENV=production
heroku config:set AUTH_JWT_CURRENT_KID=prod_main
heroku config:set AUTH_JWT_KEYS="prod_main:$(openssl rand -base64 32 | tr '+/' '-_' | tr -d '=')"
```

**AWS Lambda** (serverless.yml):
```yaml
provider:
  environment:
    APP_ENV: production
    AUTH_JWT_CURRENT_KID: ${env:JWT_CURRENT_KID}
    AUTH_JWT_KEYS: ${env:JWT_KEYS}
```

**Kubernetes**:
```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: api-config
data:
  APP_ENV: "production"
  CORS_ALLOW_ORIGINS: "https://app.example.com"
---
apiVersion: v1
kind: Secret
metadata:
  name: api-secrets
data:
  AUTH_JWT_KEYS: <base64-encoded-jwt-keys>
```

## 📚 Configuration Reference

### Complete Environment Variable List

| Variable | Type | Default | Required | Purpose |
|----------|------|---------|----------|---------|
| `APP_ENV` | string | `development` | No | Environment mode |
| `APP_DEBUG` | boolean | `true` | No | Debug mode |
| `APP_URL` | string | `http://localhost:8000` | No | Base URL |
| `APP_TIMEZONE` | string | `UTC` | No | Timezone |
| `LOG_PATH` | string | `storage/logs/app.log` | No | Log file path |
| `DB_DSN` | string | `sqlite:storage/database.sqlite` | No | Database DSN |
| `DB_USER` | string | `""` | No | Database user |
| `DB_PASSWORD` | string | `""` | No | Database password |
| `DB_ATTR_PERSISTENT` | boolean | `false` | No | Persistent connections |
| `AUTH_JWT_CURRENT_KID` | string | - | **Yes** | Current JWT key ID |
| `AUTH_JWT_KEYS` | string | - | **Yes** | JWT signing keys |
| `AUTH_TOKEN_TTL` | integer | `900` | No | Token TTL (seconds) |
| `CORS_ALLOW_ORIGINS` | string | `*` | No | Allowed origins |
| `CORS_ALLOW_METHODS` | string | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | No | Allowed methods |
| `CORS_ALLOW_HEADERS` | string | `Authorization,Content-Type` | No | Allowed headers |
| `CORS_MAX_AGE` | integer | `600` | No | Preflight cache (seconds) |
| `CORS_ALLOW_CREDENTIALS` | boolean | `false` | No | Allow credentials |
| `RATE_LIMIT_STORE` | string | `file` | No | Storage backend |
| `RATE_LIMIT_DEFAULT` | integer | `60` | No | Default limit |
| `RATE_LIMIT_WINDOW` | integer | `60` | No | Time window (seconds) |

### Environment-Specific Defaults

| Setting | Development | Testing | Production |
|---------|-------------|---------|------------|
| `APP_DEBUG` | `true` | `true` | `false` |
| `DB_DSN` | SQLite file | SQLite memory | MySQL/PostgreSQL |
| `CORS_ALLOW_ORIGINS` | `*` | `*` | Specific domains |
| `RATE_LIMIT_DEFAULT` | High (1000+) | Low (5-10) | Moderate (60) |
| `RATE_LIMIT_STORE` | `file` | `file` | `apcu` |
| Route Caching | Disabled | Disabled | Enabled |
