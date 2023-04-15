# LeanPHP - Tiny, Fast PHP 8.2 REST API Microframework

A minimalist, high-performance PHP framework designed for building secure JSON REST APIs. LeanPHP focuses on simplicity, security, and speed while providing all the essential features needed for modern API development.

## ✨ Features

- **🚀 Fast & Lightweight**: Minimal overhead with OpCache optimization
- **🔒 Security First**: JWT authentication, rate limiting, CORS, input validation
- **📝 JSON-Only**: Stateless design, consistent error format (RFC 7807 Problem Details)
- **🛣️ Smart Routing**: Path parameters with constraints, route groups, middleware
- **⚡ High Performance**: Route caching, ETags, conditional requests
- **🔧 Developer Friendly**: Great error messages, debugging support
- **📊 Production Ready**: Logging, monitoring, error handling

## 🚀 Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Optional: APCu extension (for better rate limiting performance)

### Installation

```bash
# Clone the repository
git clone <your-repo-url> lean-php
cd lean-php

# Install dependencies
composer install

# Set up environment
cp .env.example .env
# Edit .env with your configuration

# Initialize database
php scripts/seed.php

# Start development server
php -S localhost:8000 -t public public/router.php
```

### Test the API

```bash
# Health check
curl http://localhost:8000/health

# Login to get a token
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@local","password":"secret"}'

# Use the token for authenticated requests
curl http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 📋 Environment Configuration

Create a `.env` file in the root directory with these variables:

| Variable | Default | Description |
|----------|---------|-------------|
| **Application** |
| `APP_ENV` | `development` | Environment: `development` or `production` |
| `APP_DEBUG` | `true` | Show detailed errors and stack traces |
| `APP_URL` | `http://localhost:8000` | Base URL of your API |
| `APP_TIMEZONE` | `UTC` | Application timezone |
| `LOG_PATH` | `storage/logs/app.log` | Log file path |
| **CORS Settings** |
| `CORS_ALLOW_ORIGINS` | `*` | Allowed origins (comma-separated) |
| `CORS_ALLOW_METHODS` | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | Allowed HTTP methods |
| `CORS_ALLOW_HEADERS` | `Authorization,Content-Type` | Allowed request headers |
| `CORS_MAX_AGE` | `600` | Preflight cache duration (seconds) |
| `CORS_ALLOW_CREDENTIALS` | `false` | Allow credentials in CORS requests |
| **Authentication** |
| `AUTH_JWT_CURRENT_KID` | `main` | Current key ID for JWT signing |
| `AUTH_JWT_KEYS` | `main:base64urlsecret` | JWT keys (KID:base64url pairs) |
| `AUTH_TOKEN_TTL` | `900` | Token expiration time (seconds) |
| **Rate Limiting** |
| `RATE_LIMIT_STORE` | `apcu` | Storage: `apcu` or `file` |
| `RATE_LIMIT_DEFAULT` | `60` | Default requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Rate limit window (seconds) |
| **Database** |
| `DB_DSN` | `sqlite:storage/database.sqlite` | Database connection string |
| `DB_USER` | *(empty)* | Database username (not needed for SQLite) |
| `DB_PASSWORD` | *(empty)* | Database password (not needed for SQLite) |
| `DB_ATTR_PERSISTENT` | `false` | Use persistent connections |

### Example Production .env

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_TIMEZONE=UTC
LOG_PATH=storage/logs/app.log

CORS_ALLOW_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-Request-Id
CORS_MAX_AGE=3600
CORS_ALLOW_CREDENTIALS=false

AUTH_JWT_CURRENT_KID=prod-2024
AUTH_JWT_KEYS=prod-2024:your-random-base64url-secret,prod-2023:old-key-for-rotation
AUTH_TOKEN_TTL=900

RATE_LIMIT_STORE=apcu
RATE_LIMIT_DEFAULT=100
RATE_LIMIT_WINDOW=60

DB_DSN=mysql:host=localhost;dbname=api;charset=utf8mb4
DB_USER=api_user
DB_PASSWORD=secure_password
DB_ATTR_PERSISTENT=false
```

## 📚 API Examples

### Authentication

#### Login
```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "demo@local",
    "password": "secret"
  }'
```

**Success Response (200):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImtpZCI6Im1haW4ifQ...",
  "token_type": "Bearer",
  "expires_in": 900
}
```

**Error Response (401):**
```json
{
  "type": "/problems/unauthorized",
  "title": "Unauthorized",
  "status": 401,
  "detail": "Invalid credentials"
}
```

### User Management

#### List Users (with authentication)
```bash
curl http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Success Response (200):**
```json
[
  {"id": 1, "name": "Demo User", "email": "demo@local"},
  {"id": 2, "name": "Jane Doe", "email": "jane@example.com"}
]
```

#### Get User by ID
```bash
curl http://localhost:8000/v1/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Create User
```bash
curl -X POST http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New User",
    "email": "new@example.com",
    "password": "secure123"
  }'
```

**Success Response (201):**
```json
{
  "id": 3,
  "name": "New User",
  "email": "new@example.com"
}
```

### Error Handling

#### Validation Error (422)
```bash
curl -X POST http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "", "email": "invalid-email"}'
```

**Response:**
```json
{
  "type": "/problems/validation",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "The request contains validation errors",
  "instance": "/v1/users",
  "errors": {
    "name": ["Name is required"],
    "email": ["Email must be a valid email address"]
  }
}
```

#### Rate Limiting (429)
```bash
# After exceeding rate limit
curl http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "type": "/problems/too-many-requests",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded"
}
```

**Headers:**
- `Retry-After: 30`
- `X-RateLimit-Limit: 60`
- `X-RateLimit-Remaining: 0`

### ETag/Conditional Requests

#### GET with ETag
```bash
curl -i http://localhost:8000/v1/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```
HTTP/1.1 200 OK
ETag: "a1b2c3d4e5f6"
Content-Type: application/json

{"id": 1, "name": "Demo User", "email": "demo@local"}
```

#### Conditional GET (304 Not Modified)
```bash
curl -i http://localhost:8000/v1/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "If-None-Match: \"a1b2c3d4e5f6\""
```

**Response:**
```
HTTP/1.1 304 Not Modified
ETag: "a1b2c3d4e5f6"
```

### CORS Preflight

```bash
curl -X OPTIONS http://localhost:8000/v1/users \
  -H "Origin: https://app.example.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Authorization,Content-Type"
```

**Response:**
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://app.example.com
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Authorization, Content-Type
Access-Control-Max-Age: 600
Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers
```

## 🏗️ Project Structure

```
lean-php/
├── app/
│   ├── Controllers/          # API controllers
│   │   ├── AuthController.php
│   │   ├── HealthController.php
│   │   └── UserController.php
│   └── Middleware/           # HTTP middleware
│       ├── AuthBearer.php
│       ├── Cors.php
│       ├── ErrorHandler.php
│       ├── ETag.php
│       ├── JsonBodyParser.php
│       ├── RateLimiter.php
│       ├── RequestId.php
│       └── RequireScopes.php
├── bin/
│   └── route_cache.php       # Route cache generator
├── config/
│   └── app.php               # Configuration settings
├── public/
│   ├── index.php             # Application entry point
│   └── router.php            # Development server router
├── routes/
│   └── api.php               # Route definitions
├── scripts/
│   └── seed.php              # Database seeding
├── src/                      # Core framework
│   ├── Auth/
│   │   └── Token.php         # JWT token handling
│   ├── DB/
│   │   └── DB.php            # PDO database wrapper
│   ├── Http/                 # HTTP foundation
│   │   ├── MiddlewareRunner.php
│   │   ├── Problem.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── ResponseEmitter.php
│   ├── Logging/
│   │   └── Logger.php        # File logger
│   ├── RateLimit/           # Rate limiting stores
│   │   ├── ApcuStore.php
│   │   ├── FileStore.php
│   │   └── Store.php
│   ├── Routing/
│   │   └── Router.php        # URL routing
│   ├── Support/
│   │   └── env.php           # Environment helpers
│   └── Validation/          # Input validation
│       ├── ValidationException.php
│       └── Validator.php
├── storage/                 # Generated files
│   ├── cache/               # Route cache
│   ├── database.sqlite      # SQLite database
│   ├── logs/                # Log files
│   └── ratelimit/          # File-based rate limit data
├── tests/                   # Test suite
│   ├── Integration/
│   └── Unit/
├── .env.example             # Environment template
├── composer.json
└── README.md
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test file
composer test tests/Unit/AuthTest.php

# Run static analysis
composer stan

# Generate route cache for production
php bin/route_cache.php
```

## 🚀 Production Deployment

### 1. Optimize for Production

```bash
# Set production environment
sed -i 's/APP_ENV=development/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Generate route cache
php bin/route_cache.php

# Ensure proper permissions
chmod -R 755 storage/
chown -R www-data:www-data storage/
```

### 2. Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/lean-php/public;
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

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
}
```

#### Apache Configuration (.htaccess in public/)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "no-referrer-when-downgrade"
```

### 3. Performance Optimization

- **Enable OpCache**: Add to php.ini:
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=4000
  opcache.validate_timestamps=0
  opcache.revalidate_freq=0
  ```

- **Install APCu**: For better rate limiting performance
  ```bash
  apt-get install php8.2-apcu
  ```

## 🔧 Advanced Usage

### Custom Middleware

```php
<?php

class CustomMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Pre-processing
        $start = microtime(true);

        $response = $next($request);

        // Post-processing
        $duration = microtime(true) - $start;
        $response->header('X-Response-Time', $duration);

        return $response;
    }
}
```

### Route Groups

```php
// routes/api.php
$router->group('/v1', ['middleware' => ['auth']], function ($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'store']);

    $router->group('/admin', ['middleware' => ['scopes:admin']], function ($router) {
        $router->delete('/users/{id}', [UserController::class, 'destroy']);
    });
});
```

### Custom Validation Rules

```php
$validator = Validator::make($data, [
    'email' => 'required|email|unique:users',
    'age' => 'required|int|between:18,100',
    'website' => 'url',
    'tags' => 'array',
    'birthday' => 'date|before:today'
]);

if (!$validator->passes()) {
    throw new ValidationException(
        Problem::validation($validator->errors())
    );
}
```

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📞 Support

- 📚 Documentation: Check this README and inline code comments
- 🐛 Issues: Open an issue on GitHub
- 💡 Feature Requests: Open a discussion on GitHub

---

**Built with ❤️ for modern PHP development**
