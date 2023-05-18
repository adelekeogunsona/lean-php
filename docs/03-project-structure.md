# Project Structure

LeanPHP follows a clean, organized directory structure that separates concerns and makes the codebase easy to understand and maintain. This guide explains the purpose of every directory and file, along with the architectural principles behind the organization.

## 🏗️ Architecture Overview

LeanPHP uses a **layered architecture** with clear separation between:

- **Framework Core** (`src/`) - Reusable framework components
- **Application Layer** (`app/`) - Your application-specific code
- **Configuration** (`config/`) - Application settings
- **Entry Points** (`public/`, `routes/`) - Web server interface and routing
- **Infrastructure** (`storage/`, `scripts/`) - Data storage and utilities
- **Development** (`tests/`, `docs/`) - Testing and documentation

## 📁 Complete Directory Structure

```
lean-php/
├── 📂 app/                     # Application layer (your code)
│   ├── 📂 Controllers/         # HTTP controllers
│   │   ├── 📄 AuthController.php
│   │   ├── 📄 HealthController.php
│   │   └── 📄 UserController.php
│   └── 📂 Middleware/          # Application-specific middleware
│       ├── 📄 AuthBearer.php
│       ├── 📄 Cors.php
│       ├── 📄 ErrorHandler.php
│       ├── 📄 ETag.php
│       ├── 📄 JsonBodyParser.php
│       ├── 📄 RateLimiter.php
│       ├── 📄 RequestId.php
│       ├── 📄 RequireScopes.php
│       └── 📄 TestCounter.php
│
├── 📂 bin/                     # Executable scripts
│   └── 📄 route_cache.php      # Route cache generator
│
├── 📂 config/                  # Configuration files
│   └── 📄 app.php              # Main application config
│
├── 📂 docs/                    # Documentation
│   ├── 📄 01-installation.md
│   ├── 📄 02-configuration.md
│   ├── 📄 03-project-structure.md
│   ├── 📄 ...                  # Additional documentation
│   └── 📄 README.md
│
├── 📂 public/                  # Web server document root
│   ├── 📄 index.php            # Main application entry point
│   └── 📄 router.php           # Development server router
│
├── 📂 routes/                  # Route definitions
│   └── 📄 api.php              # API route definitions
│
├── 📂 scripts/                 # Utility scripts
│   └── 📄 seed.php             # Database seeder
│
├── 📂 src/                     # Framework core (LeanPHP namespace)
│   ├── 📂 Auth/                # Authentication system
│   │   └── 📄 Token.php        # JWT token handling
│   ├── 📂 DB/                  # Database layer
│   │   └── 📄 DB.php           # Database abstraction
│   ├── 📂 Http/                # HTTP foundation
│   │   ├── 📄 MiddlewareRunner.php
│   │   ├── 📄 Problem.php      # RFC 7807 Problem Details
│   │   ├── 📄 Request.php      # HTTP request object
│   │   ├── 📄 Response.php     # HTTP response object
│   │   └── 📄 ResponseEmitter.php
│   ├── 📂 Logging/             # Logging system
│   │   └── 📄 Logger.php       # Simple file logger
│   ├── 📂 RateLimit/           # Rate limiting
│   │   ├── 📄 ApcuStore.php    # APCu storage backend
│   │   ├── 📄 FileStore.php    # File storage backend
│   │   └── 📄 Store.php        # Storage interface
│   ├── 📂 Routing/             # Routing system
│   │   └── 📄 Router.php       # Route dispatcher
│   ├── 📂 Support/             # Utility functions
│   │   └── 📄 env.php          # Environment helpers
│   └── 📂 Validation/          # Input validation
│       ├── 📄 ValidationException.php
│       └── 📄 Validator.php    # Validation engine
│
├── 📂 storage/                 # Data storage (writable)
│   ├── 📂 cache/               # Application caches
│   │   ├── 📂 phpstan/         # PHPStan cache
│   │   ├── 📂 phpunit/         # PHPUnit cache
│   │   └── 📄 routes.php       # Compiled routes (production)
│   ├── 📂 logs/                # Log files
│   │   ├── 📄 app.log          # Application logs
│   │   └── 📄 test.log         # Test logs
│   ├── 📂 ratelimit/           # Rate limiting data
│   └── 📄 database.sqlite      # SQLite database
│
├── 📂 tests/                   # Test suite
│   ├── 📂 Integration/         # Integration tests
│   │   └── 📄 ApiFlowTest.php
│   ├── 📂 Unit/                # Unit tests
│   │   ├── 📄 AuthBearerTest.php
│   │   ├── 📄 ConfigTest.php
│   │   ├── 📄 ...              # More unit tests
│   │   └── 📄 TokenTest.php
│   └── 📄 bootstrap.php        # Test bootstrap
│
├── 📂 vendor/                  # Composer dependencies
│
├── 📄 .env.example             # Environment template
├── 📄 .gitignore               # Git ignore rules
├── 📄 composer.json            # Composer configuration
├── 📄 composer.lock            # Dependency lock file
├── 📄 phpstan.neon             # PHPStan configuration
├── 📄 phpunit.xml              # PHPUnit configuration
└── 📄 README.md                # Project overview
```

## 🔍 Directory Details

### `/app` - Application Layer

**Purpose**: Contains your application-specific code that extends the framework.

**Namespace**: `App\` (PSR-4 autoloaded)

#### `/app/Controllers`
HTTP controllers that handle requests and return responses.

**Example Structure**:
```php
<?php
namespace App\Controllers;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class UserController
{
    public function index(Request $request): Response
    {
        // Controller logic
    }
}
```

**Key Files**:
- `AuthController.php` - Authentication endpoints (login, token refresh)
- `HealthController.php` - Health check and monitoring endpoints
- `UserController.php` - User management CRUD operations

**Conventions**:
- One class per file
- Methods return `Response` objects
- Controllers are stateless
- Business logic should be extracted to services

#### `/app/Middleware`
Application-specific middleware for request/response processing.

**Example Structure**:
```php
<?php
namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class CustomMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Before logic
        $response = $next($request);
        // After logic
        return $response;
    }
}
```

**Key Files**:
- `AuthBearer.php` - JWT token authentication
- `Cors.php` - Cross-origin resource sharing
- `ErrorHandler.php` - Exception handling and error responses
- `ETag.php` - HTTP caching with ETags
- `JsonBodyParser.php` - JSON request body parsing
- `RateLimiter.php` - Request rate limiting
- `RequestId.php` - Request tracking for debugging
- `RequireScopes.php` - Authorization based on token scopes

### `/bin` - Executable Scripts

**Purpose**: Command-line utilities and build tools.

**Files**:
- `route_cache.php` - Generates optimized route cache for production

**Usage**:
```bash
# Generate route cache
php bin/route_cache.php
```

### `/config` - Configuration

**Purpose**: Centralized application configuration.

**Files**:
- `app.php` - Main application configuration

**Structure**:
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

### `/docs` - Documentation

**Purpose**: Comprehensive framework and API documentation.

**Structure**:
- Numbered guides (01-22) for systematic learning
- `README.md` - Documentation index
- Covers installation, configuration, usage, and deployment

### `/public` - Web Server Document Root

**Purpose**: Web server entry point and static assets.

**Security**: Only directory exposed to web server.

**Files**:
- `index.php` - Main application entry point
- `router.php` - Development server routing script

**Web Server Configuration**:
```apache
# Apache
DocumentRoot /path/to/lean-php/public
```

```nginx
# Nginx
root /path/to/lean-php/public;
```

#### `index.php` - Application Bootstrap
```php
<?php
// 1. Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// 3. Load configuration
$config = require __DIR__ . '/../config/app.php';

// 4. Build request
$request = Request::fromGlobals();

// 5. Create router and load routes
$router = new Router();
require __DIR__ . '/../routes/api.php';

// 6. Set up middleware pipeline
$middlewareRunner = new MiddlewareRunner();
$middlewareRunner->add(new ErrorHandler());
$middlewareRunner->add(new RequestId());
$middlewareRunner->add(new Cors());
$middlewareRunner->add(new JsonBodyParser());

// 7. Handle request and emit response
$response = $middlewareRunner->handle($request, function ($request) use ($router) {
    return $router->dispatch($request);
});

ResponseEmitter::emit($response);
```

### `/routes` - Route Definitions

**Purpose**: HTTP route configuration and registration.

**Files**:
- `api.php` - API endpoint definitions

**Structure**:
```php
<?php
use LeanPHP\Routing\Router;

// Simple routes
$router->get('/health', [App\Controllers\HealthController::class, 'index']);
$router->post('/login', [App\Controllers\AuthController::class, 'login']);

// Routes with parameters
$router->get('/users/{id:\d+}', [App\Controllers\UserController::class, 'show']);

// Route groups with middleware
$router->group('/v1', ['middleware' => [App\Middleware\ETag::class]], function ($router) {
    $router->get('/users', [App\Controllers\UserController::class, 'index'], [
        App\Middleware\AuthBearer::class,
        new App\Middleware\RequireScopes('users.read'),
    ]);
});
```

### `/scripts` - Utility Scripts

**Purpose**: Database seeding, deployment tasks, and maintenance scripts.

**Files**:
- `seed.php` - Database schema and data seeding

**Usage**:
```bash
# Initialize database
php scripts/seed.php
```

### `/src` - Framework Core

**Purpose**: Reusable framework components that could be extracted into a separate package.

**Namespace**: `LeanPHP\` (PSR-4 autoloaded)

**Design Principles**:
- Framework-agnostic where possible
- Minimal dependencies
- Clear interfaces and contracts
- High test coverage

#### `/src/Auth` - Authentication System
JWT-based stateless authentication.

**Files**:
- `Token.php` - JWT token creation, verification, and key management

**Features**:
- HS256 signing algorithm
- Key rotation support
- Standard JWT claims (iat, nbf, exp, jti)
- Time-based validation with leeway

#### `/src/DB` - Database Layer
Simple PDO wrapper for database operations.

**Files**:
- `DB.php` - Database connection and query execution

**Features**:
- Environment-based configuration
- Prepared statements for security
- Transaction support
- SQLite and MySQL support

#### `/src/Http` - HTTP Foundation
Core HTTP request/response handling.

**Files**:
- `Request.php` - Immutable HTTP request object
- `Response.php` - HTTP response builder
- `Problem.php` - RFC 7807 Problem Details for errors
- `MiddlewareRunner.php` - Middleware execution pipeline
- `ResponseEmitter.php` - HTTP response output

**Features**:
- Immutable request objects
- Fluent response building
- Standardized error responses
- Middleware support

#### `/src/Logging` - Logging System
Simple file-based logging.

**Files**:
- `Logger.php` - Log file writer with levels and context

**Features**:
- Multiple log levels (debug, info, warning, error)
- Contextual logging
- Stream support (files, php://stderr)
- Thread-safe writing

#### `/src/RateLimit` - Rate Limiting
Request rate limiting with multiple storage backends.

**Files**:
- `Store.php` - Storage interface contract
- `FileStore.php` - File-based storage implementation
- `ApcuStore.php` - APCu-based storage implementation

**Features**:
- Sliding window algorithm
- Multiple storage backends
- Atomic operations
- Automatic cleanup

#### `/src/Routing` - Routing System
URL routing with pattern matching and middleware.

**Files**:
- `Router.php` - Route registration and dispatch

**Features**:
- Pattern-based routing with parameters
- Parameter constraints (e.g., `{id:\d+}`)
- Route groups with shared middleware
- Route caching for production
- Automatic HEAD/OPTIONS handling

#### `/src/Support` - Utility Functions
Helper functions and utilities.

**Files**:
- `env.php` - Environment variable helpers

**Functions**:
- `env_string()` - Get string environment variable
- `env_bool()` - Get boolean environment variable
- `env_int()` - Get integer environment variable

#### `/src/Validation` - Input Validation
Request data validation with error handling.

**Files**:
- `Validator.php` - Validation rule engine
- `ValidationException.php` - Validation error exception

**Features**:
- Fluent validation API
- Built-in validation rules
- Custom error messages
- Automatic error responses

### `/storage` - Data Storage

**Purpose**: Writable data storage for logs, cache, and databases.

**Permissions**: Must be writable by web server (755/777).

**Subdirectories**:
- `cache/` - Application caches (routes, PHPStan, PHPUnit)
- `logs/` - Application and test log files
- `ratelimit/` - Rate limiting data (file storage backend)
- SQLite database files

**Git Ignore**: All storage contents are ignored except directory structure.

### `/tests` - Test Suite

**Purpose**: Automated testing for quality assurance.

**Namespace**: `Tests\` (PSR-4 autoloaded)

**Structure**:
- `Unit/` - Unit tests for individual components
- `Integration/` - Integration tests for end-to-end workflows
- `bootstrap.php` - Test environment setup

**Test Categories**:
- **Unit Tests**: Test individual classes and methods in isolation
- **Integration Tests**: Test complete request/response flows
- **Feature Tests**: Test specific features end-to-end

### `/vendor` - Composer Dependencies

**Purpose**: Third-party packages managed by Composer.

**Contents**: Autoloaded packages and Composer's autoloader.

**Git Ignore**: Entire directory ignored, regenerated via `composer install`.

## 🔗 Autoloading Structure

LeanPHP uses **PSR-4 autoloading** for clean namespace organization:

```json
{
    "autoload": {
        "psr-4": {
            "LeanPHP\\": "src/",
            "App\\": "app/"
        },
        "files": [
            "src/Support/env.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

**Namespace Mapping**:
- `LeanPHP\*` → `src/*` (Framework core)
- `App\*` → `app/*` (Application code)
- `Tests\*` → `tests/*` (Test code, dev only)

**File Loading**:
- `src/Support/env.php` - Loaded automatically for global functions

**Class Examples**:
```php
// Framework core
LeanPHP\Http\Request         → src/Http/Request.php
LeanPHP\Routing\Router       → src/Routing/Router.php
LeanPHP\Auth\Token          → src/Auth/Token.php

// Application layer
App\Controllers\UserController → app/Controllers/UserController.php
App\Middleware\AuthBearer     → app/Middleware/AuthBearer.php

// Tests
Tests\Unit\TokenTest         → tests/Unit/TokenTest.php
Tests\Integration\ApiFlowTest → tests/Integration/ApiFlowTest.php
```

## 🎯 Design Patterns and Principles

### Single Responsibility Principle
Each class and directory has a single, well-defined purpose:
- Controllers handle HTTP requests only
- Middleware processes requests/responses
- Validators handle input validation
- Database layer manages data persistence

### Dependency Injection
Dependencies are injected rather than hard-coded:
```php
// Middleware accepts dependencies via constructor
public function __construct(?Store $store = null)
{
    $this->store = $store ?? $this->createStore();
}
```

### Interface Segregation
Small, focused interfaces for better testability:
```php
interface Store
{
    public function hit(string $key, int $limit, int $window): array;
    public function allow(string $key, int $limit, int $window): bool;
    public function retryAfter(string $key, int $limit, int $window): int;
}
```

### Immutable Objects
Request objects are immutable for thread safety:
```php
// Request properties are readonly after construction
private readonly string $method;
private readonly string $path;
private readonly array $headers;
```

### Convention over Configuration
Sensible defaults and standard patterns reduce configuration:
- Standard directory structure
- PSR-4 autoloading
- Environment-based configuration
- REST API conventions

## 📝 File Naming Conventions

### PHP Classes
- **PascalCase** for class names
- **One class per file**
- **File name matches class name**

```php
// ✅ Good
UserController.php → class UserController
AuthBearer.php → class AuthBearer
ValidationException.php → class ValidationException

// ❌ Bad
usercontroller.php
auth_bearer.php
validation-exception.php
```

### Configuration Files
- **lowercase** with **underscores**
- **Descriptive names**

```php
// ✅ Good
app.php
database.php
logging.php

// ❌ Bad
App.php
config.php
settings.php
```

### Scripts and Binaries
- **lowercase** with **underscores**
- **Action-oriented names**

```php
// ✅ Good
route_cache.php
seed.php
migrate.php

// ❌ Bad
RouteCache.php
database_seeder.php
```

## 🚀 Extension Points

### Adding New Features

#### 1. New Controller
```bash
# Create controller
touch app/Controllers/ProductController.php

# Add routes
# Edit routes/api.php
$router->get('/products', [App\Controllers\ProductController::class, 'index']);
```

#### 2. New Middleware
```bash
# Create middleware
touch app/Middleware/ApiKeyAuth.php

# Register in routes
$router->get('/secure', $handler, [App\Middleware\ApiKeyAuth::class]);
```

#### 3. New Framework Component
```bash
# Create in framework core
mkdir src/Email
touch src/Email/Mailer.php

# Follow LeanPHP namespace
namespace LeanPHP\Email;
```

#### 4. New Storage Backend
```bash
# Implement Store interface
touch src/RateLimit/RedisStore.php

# Implement required methods
class RedisStore implements Store { ... }
```

### Directory Structure Best Practices

1. **Keep related files together** - Group by feature, not by file type
2. **Use descriptive directory names** - Clear purpose and scope
3. **Maintain consistent depth** - Avoid deeply nested structures
4. **Separate concerns** - Framework code vs application code
5. **Follow PSR-4** - Namespace matches directory structure

## 🔧 Development Workflow

### Adding a New Feature

1. **Plan the structure** - Determine where files belong
2. **Create necessary directories** - Follow existing patterns
3. **Implement classes** - Use appropriate namespaces
4. **Add routes** - Register HTTP endpoints
5. **Write tests** - Unit and integration tests
6. **Update documentation** - Keep docs current

### Code Organization Checklist

- [ ] Files in correct directories
- [ ] Proper namespaces used
- [ ] PSR-4 autoloading works
- [ ] Dependencies properly injected
- [ ] Tests cover new functionality
- [ ] Documentation updated
