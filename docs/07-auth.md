# Authentication System

LeanPHP provides a robust JWT-based authentication system that handles token generation, verification, and scope-based authorization. The authentication system is designed to be secure, stateless, and follows industry best practices.

## Overview

The authentication system consists of several components:

- **Token Management**: JWT token creation and verification
- **Bearer Authentication Middleware**: Extracts and validates JWT tokens from requests
- **Scope-based Authorization**: Controls access to endpoints based on user permissions
- **Request Integration**: Seamless integration with the HTTP Request class

## JWT Token System

### Token Class (`src/Auth/Token.php`)

The `Token` class is the core of the authentication system, providing static methods for JWT token operations.

#### Key Features

- **HS256 Algorithm**: Uses HMAC SHA-256 for token signing
- **Key Rotation Support**: Supports multiple JWT keys with key IDs for rotation
- **Time-based Validation**: Implements `iat`, `nbf`, and `exp` claims with leeway
- **Unique Token IDs**: Each token gets a unique `jti` (JWT ID) claim

#### Environment Configuration

```bash
# Current key ID to use for signing new tokens
AUTH_JWT_CURRENT_KID=main

# Comma-separated key pairs (kid:base64url_secret)
AUTH_JWT_KEYS=main:dGVzdC1zZWNyZXQtbWFpbi1rZXk,old:dGVzdC1zZWNyZXQtb2xkLWtleQ

# Token time-to-live in seconds (default: 900 = 15 minutes)
AUTH_TOKEN_TTL=900
```

#### Token Issuance

```php
use LeanPHP\Auth\Token;

// Issue a token with custom claims
$claims = [
    'sub' => '123',                    // Subject (user ID)
    'email' => 'user@example.com',     // User email
    'scopes' => ['users.read', 'users.write'], // User permissions
];

$token = Token::issue($claims);
// or with custom TTL
$token = Token::issue($claims, 3600); // 1 hour
```

The issued token will automatically include:
- `iat` (issued at): Current timestamp
- `nbf` (not before): Current timestamp
- `exp` (expires at): Current timestamp + TTL
- `jti` (JWT ID): Unique identifier for this token

#### Token Verification

```php
try {
    $claims = Token::verify($bearerToken);

    // Access claims
    $userId = $claims['sub'];
    $email = $claims['email'];
    $scopes = $claims['scopes'];
} catch (InvalidArgumentException $e) {
    // Token is invalid, expired, or malformed
    echo $e->getMessage();
}
```

#### Security Features

1. **Time Leeway**: 30-second leeway for time-based claims to handle clock skew
2. **Signature Verification**: Uses constant-time comparison (`hash_equals`)
3. **Key Rotation**: Supports multiple keys for seamless key rotation
4. **Base64url Encoding**: Proper JWT-compliant encoding/decoding

#### JWT Structure

Each JWT consists of three parts:

**Header:**
```json
{
    "typ": "JWT",
    "alg": "HS256",
    "kid": "main"
}
```

**Payload (example):**
```json
{
    "sub": "123",
    "email": "user@example.com",
    "scopes": ["users.read", "users.write"],
    "iat": 1630000000,
    "nbf": 1630000000,
    "exp": 1630000900,
    "jti": "a1b2c3d4e5f6"
}
```

## Authentication Middleware

### AuthBearer Middleware (`app/Middleware/AuthBearer.php`)

The `AuthBearer` middleware handles JWT token extraction and verification for protected routes.

#### How It Works

1. **Token Extraction**: Extracts Bearer token from `Authorization` header
2. **Token Verification**: Uses `Token::verify()` to validate the JWT
3. **Claims Attachment**: Attaches decoded claims to the request object
4. **Error Handling**: Returns appropriate 401 responses for invalid tokens

#### Usage in Routes

```php
use App\Middleware\AuthBearer;

// Protect a single route
$router->get('/profile', [UserController::class, 'profile'], [
    AuthBearer::class
]);

// Protect multiple routes with middleware group
$router->group([AuthBearer::class], function($router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->post('/users', [UserController::class, 'create']);
});
```

#### Response Headers

The middleware sets proper authentication headers:

```http
WWW-Authenticate: Bearer realm="API"
```

#### Error Responses

**Missing Token:**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Missing or invalid Authorization header"
}
```

**Invalid Token:**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Invalid token: Token has expired"
}
```

### Request Integration

The middleware integrates with the `Request` class by setting JWT claims:

```php
// In your controller (after AuthBearer middleware)
public function profile(Request $request): Response
{
    // Check if authenticated
    if ($request->isAuthenticated()) {
        // Get all claims
        $claims = $request->claims();

        // Get specific claim
        $userId = $request->claim('sub');
        $email = $request->claim('email', 'unknown@example.com');
        $scopes = $request->claim('scopes', []);
    }
}
```

## Authorization with Scopes

### RequireScopes Middleware (`app/Middleware/RequireScopes.php`)

The `RequireScopes` middleware provides fine-grained authorization based on user permissions.

#### How It Works

1. **Authentication Check**: Verifies the request is authenticated
2. **Scope Extraction**: Gets scopes from JWT claims
3. **Permission Check**: Ensures all required scopes are present
4. **Access Control**: Returns 403 Forbidden if scopes are missing

#### Usage Examples

```php
use App\Middleware\RequireScopes;

// Require single scope
$router->get('/admin', [AdminController::class, 'index'], [
    AuthBearer::class,
    RequireScopes::check('admin.read')
]);

// Require multiple scopes
$router->delete('/users/{id}', [UserController::class, 'delete'], [
    AuthBearer::class,
    RequireScopes::check('users.write,admin.delete')
]);

// Using constructor
$router->post('/posts', [PostController::class, 'create'], [
    AuthBearer::class,
    new RequireScopes('posts.write')
]);
```

#### Scope Format

Scopes are stored as arrays in JWT claims:

```json
{
    "scopes": ["users.read", "users.write", "admin.read"]
}
```

#### Error Responses

**Not Authenticated:**
```json
{
    "type": "/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "This endpoint requires authentication"
}
```

**Missing Scope:**
```json
{
    "type": "/problems/forbidden",
    "title": "Forbidden",
    "status": 403,
    "detail": "Required scope 'admin.write' is missing"
}
```

**Invalid Scopes:**
```json
{
    "type": "/problems/forbidden",
    "title": "Forbidden",
    "status": 403,
    "detail": "Token does not contain valid scopes"
}
```

## Authentication Flow Example

### Login Process (AuthController)

```php
class AuthController
{
    public function login(Request $request): Response
    {
        // 1. Validate credentials
        $validator = Validator::make($request->json(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        $validator->validate();

        // 2. Find user and verify password
        $users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
        if (empty($users) || !password_verify($password, $user['password'])) {
            return Problem::make(401, 'Unauthorized', 'Invalid credentials');
        }

        // 3. Extract user scopes
        $scopes = !empty($user['scopes'])
            ? explode(',', $user['scopes'])
            : ['users.read'];

        // 4. Issue JWT token
        $claims = [
            'sub' => (string) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'scopes' => $scopes,
        ];
        $token = Token::issue($claims);

        // 5. Return token response
        return Response::json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
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

### Protected Endpoint Usage

```php
// Client sends request with token
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImtpZCI6Im1haW4ifQ...

// In protected controller
public function profile(Request $request): Response
{
    $userId = $request->claim('sub');
    $userEmail = $request->claim('email');

    // Fetch user data
    $user = DB::select('SELECT * FROM users WHERE id = ?', [$userId]);

    return Response::json(['user' => $user[0]]);
}
```

## Security Best Practices

### Key Management

1. **Strong Secrets**: Use cryptographically strong secrets (at least 256 bits)
2. **Key Rotation**: Regularly rotate JWT signing keys
3. **Environment Variables**: Store keys in environment variables, never in code
4. **Base64url Encoding**: Properly encode secrets for the `AUTH_JWT_KEYS` format

### Token Security

1. **Short Expiration**: Use short TTL values (15-30 minutes recommended)
2. **HTTPS Only**: Always use HTTPS in production
3. **Refresh Tokens**: Implement refresh token mechanism for longer sessions
4. **Token Revocation**: Consider implementing a token blacklist for immediate revocation

### Scope Design

1. **Principle of Least Privilege**: Grant minimal required permissions
2. **Granular Scopes**: Use specific scopes like `users.read` vs generic `user`
3. **Resource-based**: Design scopes around resources and actions
4. **Hierarchical**: Consider hierarchical scope relationships

### Example Scope Hierarchy

```
admin.*          # Full admin access
admin.read       # Read admin data
admin.write      # Modify admin data

users.*          # Full user management
users.read       # View users
users.write      # Create/update users
users.delete     # Delete users

posts.*          # Full post management
posts.read       # View posts
posts.write      # Create/update posts
posts.delete     # Delete posts
```

## Error Handling

The authentication system integrates with LeanPHP's error handling:

1. **ValidationException**: Handled by ErrorHandler middleware
2. **InvalidArgumentException**: Converted to 401 Unauthorized responses
3. **Debug Mode**: Provides detailed error information in development

## Testing Authentication

```php
// Test token generation and verification
$token = Token::issue(['sub' => '123', 'scopes' => ['users.read']]);
$claims = Token::verify($token);
$this->assertEquals('123', $claims['sub']);

// Test middleware
$request = new Request('GET', '/profile', ['Authorization' => 'Bearer ' . $token]);
$response = $authBearerMiddleware->handle($request, $next);
$this->assertTrue($request->isAuthenticated());
$this->assertEquals('123', $request->claim('sub'));
```
