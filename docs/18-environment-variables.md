# Environment Variables

LeanPHP follows the [Twelve-Factor App](https://12factor.net/config) methodology for configuration management, using environment variables exclusively for all configuration settings. This approach ensures clean separation between code and configuration, making deployments secure and environment-agnostic.

## 🏗️ Architecture Overview

### Environment Helper Functions

The framework provides three core helper functions located in `src/Support/env.php` for type-safe environment variable access:

```php
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
```

### Variable Resolution Priority

The helper functions check for environment variables in this specific order:

1. **`$_ENV`** superglobal (highest priority)
2. **`$_SERVER`** superglobal
3. **`getenv()`** function
4. **Default value** (lowest priority)

This ensures compatibility across different PHP configurations and deployment scenarios.

## 📋 Configuration Categories

### Application Settings

Core application behavior is controlled by these variables:

#### `APP_ENV`
- **Type**: String
- **Default**: `development`
- **Values**: `development`, `production`, `testing`
- **Purpose**: Controls environment-specific behavior and optimizations
- **Implementation**: Used in `config/app.php` and throughout the application

**Impact on Framework Behavior:**
- `production`: Enables route caching, hides detailed error messages
- `development`: Disables route caching, shows detailed errors
- `testing`: Uses in-memory database, minimal logging

#### `APP_DEBUG`
- **Type**: Boolean
- **Default**: `true`
- **Purpose**: Controls error detail visibility and debugging features
- **Implementation**: Used by `ErrorHandler` middleware to determine error response detail level

**Debug Mode Effects:**
- `true`: Shows exception messages, stack traces, and debug information
- `false`: Returns generic error messages for security

#### `APP_URL`
- **Type**: String
- **Default**: `http://localhost:8000`
- **Purpose**: Base URL for the application
- **Usage**: Used for generating absolute URLs and in deployment configurations

#### `APP_TIMEZONE`
- **Type**: String
- **Default**: `UTC`
- **Purpose**: Sets PHP's default timezone
- **Values**: Any valid PHP timezone identifier (e.g., `America/New_York`, `Europe/London`)

#### `LOG_PATH`
- **Type**: String
- **Default**: `storage/logs/app.log`
- **Purpose**: Application log file location
- **Special Values**:
  - `php://stderr`: Container-friendly logging to stderr
  - `php://stdout`: Logging to stdout
  - File paths: Regular file logging

### Database Configuration

Database connection is configured through these variables:

#### `DB_DSN`
- **Type**: String
- **Default**: `sqlite:storage/database.sqlite`
- **Purpose**: Database connection string
- **Examples**:
  ```bash
  # SQLite (development default)
  DB_DSN=sqlite:storage/database.sqlite

  # MySQL/MariaDB
  DB_DSN=mysql:host=localhost;dbname=leanphp;charset=utf8mb4

  # PostgreSQL
  DB_DSN=pgsql:host=localhost;dbname=leanphp

  # In-memory SQLite (testing)
  DB_DSN=sqlite::memory:
  ```

#### `DB_USER`, `DB_PASSWORD`
- **Type**: String
- **Default**: Empty string
- **Purpose**: Database authentication credentials
- **Note**: Not needed for SQLite

#### `DB_ATTR_PERSISTENT`
- **Type**: Boolean
- **Default**: `false`
- **Purpose**: Enable/disable persistent database connections
- **Usage**: Controls PDO connection persistence for performance optimization

### JWT Authentication

JSON Web Token configuration for API authentication:

#### `AUTH_JWT_CURRENT_KID`
- **Type**: String
- **Default**: None (required for JWT operations)
- **Purpose**: Current JWT key identifier for key rotation
- **Implementation**: Used by `Token` class to select appropriate signing key

#### `AUTH_JWT_KEYS`
- **Type**: String
- **Default**: None (required for JWT operations)
- **Format**: `key_id:base64url_encoded_secret[,key_id2:secret2]`
- **Purpose**: JWT signing keys with rotation support
- **Example**: `main:dGVzdC1rZXktZm9yLWp3dA,backup:YW5vdGhlci1qd3Qta2V5`

**Key Rotation Strategy:**
```bash
# Single key (development)
AUTH_JWT_CURRENT_KID=main
AUTH_JWT_KEYS=main:SECURE_BASE64URL_ENCODED_SECRET

# Key rotation (production)
AUTH_JWT_CURRENT_KID=new_key_2024_09
AUTH_JWT_KEYS=old_key_2024_08:OLD_SECRET,new_key_2024_09:NEW_SECRET
```

#### `AUTH_TOKEN_TTL`
- **Type**: Integer
- **Default**: `900` (15 minutes)
- **Purpose**: JWT token time-to-live in seconds
- **Range**: Typically 300-3600 seconds (5 minutes to 1 hour)

### CORS Configuration

Cross-Origin Resource Sharing settings for browser security:

#### `CORS_ALLOW_ORIGINS`
- **Type**: String (comma-separated)
- **Default**: `*`
- **Purpose**: Allowed request origins
- **Examples**:
  ```bash
  # Development (allow all)
  CORS_ALLOW_ORIGINS=*

  # Production (specific origins)
  CORS_ALLOW_ORIGINS=https://app.example.com,https://admin.example.com

  # Multiple environments
  CORS_ALLOW_ORIGINS=http://localhost:3000,https://staging.example.com
  ```

#### `CORS_ALLOW_METHODS`
- **Type**: String (comma-separated)
- **Default**: `GET,POST,PUT,PATCH,DELETE,OPTIONS`
- **Purpose**: Allowed HTTP methods for CORS requests

#### `CORS_ALLOW_HEADERS`
- **Type**: String (comma-separated)
- **Default**: `Authorization,Content-Type`
- **Purpose**: Allowed request headers in CORS requests

#### `CORS_MAX_AGE`
- **Type**: Integer
- **Default**: `600` (10 minutes)
- **Purpose**: Preflight cache duration in seconds

#### `CORS_ALLOW_CREDENTIALS`
- **Type**: Boolean
- **Default**: `false`
- **Purpose**: Whether to allow credentials (cookies, auth headers) in CORS requests
- **Security Note**: When `true`, `CORS_ALLOW_ORIGINS` cannot be `*`

### Rate Limiting

API rate limiting configuration:

#### `RATE_LIMIT_STORE`
- **Type**: String
- **Default**: `file`
- **Values**: `file`, `apcu`
- **Purpose**: Storage backend for rate limit data
- **Implementation**:
  - `file`: Uses `FileStore` class, stores data in `storage/ratelimit/`
  - `apcu`: Uses `ApcuStore` class (requires APCu extension)

#### `RATE_LIMIT_DEFAULT`
- **Type**: Integer
- **Default**: `60`
- **Purpose**: Default rate limit (requests per window)

#### `RATE_LIMIT_WINDOW`
- **Type**: Integer
- **Default**: `60` (1 minute)
- **Purpose**: Rate limiting time window in seconds

## 🔧 Environment File Management

### `.env` File Structure

LeanPHP uses the `vlucas/phpdotenv` package for loading environment variables from `.env` files:

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

**Loading Priority:**
1. System environment variables (highest priority)
2. `.env` file variables
3. Default values in code (lowest priority)

### Environment File Security

**Development:**
- Use `.env` file for local configuration
- Include `.env.example` with safe defaults
- Add `.env` to `.gitignore` to prevent committing secrets

**Production:**
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

**Features:**
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

**Features:**
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

**Features:**
- Error details hidden for security
- Production database (MySQL/PostgreSQL)
- Secure JWT keys with rotation strategy
- Specific CORS origins
- APCu for high-performance rate limiting
- Route caching enabled automatically

## 🚀 Deployment Configurations

### Container Deployments

**Docker Environment Variables:**
```dockerfile
# Dockerfile
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DB_DSN=mysql:host=db;dbname=api;charset=utf8mb4
```

**docker-compose.yml:**
```yaml
services:
  api:
    environment:
      - APP_ENV=production
      - DB_DSN=mysql:host=db;dbname=api;charset=utf8mb4
      - AUTH_JWT_KEYS=${JWT_KEYS}  # From .env file
```

### Cloud Platform Examples

**Heroku:**
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

**Kubernetes:**
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

## 🛠️ Development Tools

### Configuration Debugging

**View Current Configuration:**
```php
// Add to any controller for debugging
$config = require __DIR__ . '/../../config/app.php';
var_dump($config);
```

**Environment Variable Inspection:**
```bash
# Check specific variables
echo $APP_ENV
echo $AUTH_JWT_CURRENT_KID

# View all environment variables
printenv | grep -E "(APP_|AUTH_|DB_|CORS_|RATE_)"
```

**Testing Environment Variables:**
```php
// Test env functions with defaults
require_once __DIR__ . '/../../src/Support/env.php';

$env = env_string('APP_ENV', 'development');
$debug = env_bool('APP_DEBUG', true);
$ttl = env_int('AUTH_TOKEN_TTL', 900);
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

## 🔒 Security Best Practices

### JWT Key Management

```bash
# Generate secure JWT keys
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='

# Key rotation strategy
AUTH_JWT_CURRENT_KID=new_key_$(date +%Y_%m)
AUTH_JWT_KEYS=old_key:OLD_SECRET,new_key_$(date +%Y_%m):NEW_SECRET
```

### Production Secrets

- Use environment variable injection from secure stores
- Rotate JWT keys regularly (monthly/quarterly)
- Use strong, unique passwords for database access
- Never commit sensitive values to version control
- Use specific CORS origins instead of `*` in production

### Environment Isolation

- Use different databases for each environment
- Separate JWT keys per environment
- Use environment-specific logging configurations
- Test configurations in staging before production deployment
