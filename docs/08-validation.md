# Validation System

LeanPHP provides a comprehensive validation system that ensures data integrity and provides clear error messages. The validation system is designed to be flexible, extensible, and easy to use while following HTTP standards for error responses.

## Overview

The validation system consists of:

- **Validator Class**: Core validation engine with built-in rules
- **ValidationException**: Specialized exception for validation failures
- **Error Handler Integration**: Automatic conversion to HTTP Problem responses
- **Custom Messages**: Support for custom validation error messages

## Validator Class (`src/Validation/Validator.php`)

The `Validator` class is the heart of the validation system, providing a fluent API for data validation.

### Basic Usage

```php
use LeanPHP\Validation\Validator;

// Create validator instance
$validator = Validator::make($data, $rules);

// Check if validation passes
if ($validator->passes()) {
    // Data is valid
    $cleanData = $data;
} else {
    // Get validation errors
    $errors = $validator->errors();
}

// Or validate and throw exception on failure
$validator->validate(); // Throws ValidationException if invalid
```

### Validation Rules

The validator supports a comprehensive set of validation rules:

#### Required Rules

**`required`** - Field must be present and not empty
```php
$rules = ['name' => 'required'];

// Valid: "John", "0", 0, false
// Invalid: null, "", [], empty array
```

#### Type Validation

**`string`** - Field must be a string
```php
$rules = ['name' => 'string'];
// Valid: "John", ""
// Invalid: 123, true, []
```

**`int`** - Field must be an integer
```php
$rules = ['age' => 'int'];
// Valid: 25, "25" (numeric string)
// Invalid: "abc", 25.5, true
```

**`numeric`** - Field must be numeric
```php
$rules = ['price' => 'numeric'];
// Valid: 25, 25.50, "25", "25.50"
// Invalid: "abc", true, []
```

**`boolean`** - Field must be a boolean value
```php
$rules = ['active' => 'boolean'];
// Valid: true, false, 1, 0, "1", "0", "true", "false"
// Invalid: "yes", 2, "maybe"
```

**`array`** - Field must be an array
```php
$rules = ['tags' => 'array'];
// Valid: [], ["tag1", "tag2"]
// Invalid: "tag1", 123, null
```

#### Format Validation

**`email`** - Field must be a valid email address
```php
$rules = ['email' => 'email'];
// Valid: "user@example.com", "test+tag@domain.co.uk"
// Invalid: "invalid-email", "user@", "@domain.com"
```

**`url`** - Field must be a valid URL
```php
$rules = ['website' => 'url'];
// Valid: "https://example.com", "http://localhost:8000"
// Invalid: "invalid-url", "example", "ftp://invalid"
```

**`regex:pattern`** - Field must match regular expression
```php
$rules = [
    'phone' => 'regex:/^\+?[1-9]\d{1,14}$/',     // International phone
    'slug' => 'regex:/^[a-z0-9-]+$/',           // URL slug
];
```

#### Size Validation

**`min:value`** - Field must have minimum value/length/count
```php
$rules = [
    'password' => 'min:8',      // String: minimum 8 characters
    'age' => 'numeric|min:18',  // Number: minimum value 18
    'tags' => 'array|min:1',    // Array: minimum 1 element
];
```

**`max:value`** - Field must not exceed maximum value/length/count
```php
$rules = [
    'name' => 'max:100',        // String: maximum 100 characters
    'age' => 'numeric|max:120', // Number: maximum value 120
    'tags' => 'array|max:10',   // Array: maximum 10 elements
];
```

**`between:min,max`** - Field must be between min and max values
```php
$rules = [
    'username' => 'between:3,20',    // String: 3-20 characters
    'rating' => 'numeric|between:1,5', // Number: 1-5 range
    'items' => 'array|between:1,10',   // Array: 1-10 elements
];
```

#### Choice Validation

**`in:value1,value2,value3`** - Field must be one of the specified values
```php
$rules = [
    'status' => 'in:active,inactive,pending',
    'role' => 'in:admin,user,moderator',
];
```

**`not_in:value1,value2,value3`** - Field must not be one of the specified values
```php
$rules = [
    'username' => 'not_in:admin,root,administrator',
];
```

#### Date Validation

**`date`** - Field must be a valid date
```php
$rules = ['birthday' => 'date'];
// Valid: "2023-01-01", "January 1, 2023", "2023/01/01"
// Invalid: "invalid-date", "2023-13-01", "abc"
```

**`before:date`** - Field must be a date before the specified date
```php
$rules = [
    'start_date' => 'date|before:2024-01-01',
    'birthday' => 'date|before:today',
];
```

**`after:date`** - Field must be a date after the specified date
```php
$rules = [
    'end_date' => 'date|after:2023-01-01',
    'future_date' => 'date|after:now',
];
```

### Rule Combinations

Rules can be combined using the pipe (`|`) separator:

```php
$rules = [
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|max:100',
    'age' => 'required|int|min:18|max:120',
    'tags' => 'array|min:1|max:5',
    'status' => 'required|in:active,inactive',
];
```

### Custom Error Messages

You can provide custom error messages for specific field-rule combinations:

```php
$data = ['email' => 'invalid-email'];
$rules = ['email' => 'required|email'];
$messages = [
    'email.required' => 'Please provide your email address.',
    'email.email' => 'Please provide a valid email address.',
];

$validator = Validator::make($data, $rules, $messages);
```

### Validation Methods

#### `make($data, $rules, $messages = [])`
Creates a new validator instance.

```php
$validator = Validator::make([
    'name' => 'John Doe',
    'email' => 'john@example.com',
], [
    'name' => 'required|string|max:100',
    'email' => 'required|email',
]);
```

#### `passes()` and `fails()`
Check validation status without throwing exceptions.

```php
if ($validator->passes()) {
    // Process valid data
    $user = createUser($data);
} else {
    // Handle validation errors
    $errors = $validator->errors();
    return Response::json(['errors' => $errors], 422);
}
```

#### `validate()`
Validates data and throws `ValidationException` on failure.

```php
try {
    $validator->validate();
    // Continue with valid data
} catch (ValidationException $e) {
    // Exception is automatically handled by ErrorHandler middleware
    throw $e;
}
```

#### `errors()`
Returns array of validation error messages.

```php
$errors = $validator->errors();
// Example output:
[
    'email' => [
        'The email field is required.',
        'The email must be a valid email address.'
    ],
    'password' => [
        'The password must be at least 8 characters.'
    ]
]
```

## ValidationException (`src/Validation/ValidationException.php`)

The `ValidationException` is a specialized exception that carries a formatted HTTP response.

### Features

- **HTTP Response**: Contains a properly formatted Problem response
- **Automatic Handling**: Caught and handled by the ErrorHandler middleware
- **Status Code**: Always returns 422 Unprocessable Content

### Structure

```php
class ValidationException extends Exception
{
    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
        parent::__construct('Validation failed', 422);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
```

### Error Response Format

When validation fails, the system returns an RFC 7807 Problem response:

```json
{
    "type": "/problems/validation",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "The given data was invalid.",
    "instance": "/v1/users",
    "errors": {
        "email": [
            "The email field is required.",
            "The email must be a valid email address."
        ],
        "password": [
            "The password must be at least 8 characters."
        ]
    }
}
```

## Integration with Controllers

### Basic Controller Validation

```php
use LeanPHP\Validation\Validator;

class UserController
{
    public function create(Request $request): Response
    {
        $data = $request->json() ?? [];

        // Validate input
        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'age' => 'int|min:18|max:120',
        ]);

        // This will throw ValidationException if invalid
        // ErrorHandler middleware will catch it and return proper response
        $validator->validate();

        // Process valid data
        $user = $this->createUser($data);
        return Response::json(['user' => $user], 201);
    }
}
```

### Advanced Validation with Custom Logic

```php
public function updateUser(Request $request): Response
{
    $data = $request->json() ?? [];
    $userId = $request->params()['id'];

    // Basic validation
    $validator = Validator::make($data, [
        'name' => 'string|min:2|max:100',
        'email' => 'email|max:255',
        'age' => 'int|min:18|max:120',
    ]);

    $validator->validate();

    // Custom business logic validation
    if (isset($data['email'])) {
        $existing = DB::select(
            'SELECT id FROM users WHERE email = ? AND id != ?',
            [$data['email'], $userId]
        );

        if (!empty($existing)) {
            return Problem::make(
                422,
                'Validation Failed',
                'Email already exists',
                '/problems/validation',
                ['email' => ['The email has already been taken.']]
            );
        }
    }

    // Update user...
}
```

### Conditional Validation

```php
public function createPost(Request $request): Response
{
    $data = $request->json() ?? [];

    // Base rules
    $rules = [
        'title' => 'required|string|max:200',
        'content' => 'required|string',
        'status' => 'required|in:draft,published,scheduled',
    ];

    // Conditional rules based on status
    if (($data['status'] ?? '') === 'scheduled') {
        $rules['publish_at'] = 'required|date|after:now';
    }

    $validator = Validator::make($data, $rules);
    $validator->validate();

    // Process post creation...
}
```

## Error Handler Integration

The validation system integrates seamlessly with LeanPHP's error handling:

```php
class ErrorHandler
{
    private function handleException(Throwable $e, Request $request): Response
    {
        // ValidationException gets special treatment
        if ($e instanceof ValidationException) {
            $response = $e->getResponse();

            // Add instance path
            $body = json_decode($response->getBody(), true);
            $body['instance'] = $request->path();

            return $response->setBody(json_encode($body));
        }

        // Handle other exceptions...
    }
}
```

## Validation Examples

### User Registration

```php
$validator = Validator::make($request->json(), [
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|max:100',
    'confirm_password' => 'required|string',
    'age' => 'required|int|min:18|max:120',
    'terms' => 'required|boolean',
], [
    'name.required' => 'Please provide your full name.',
    'email.email' => 'Please provide a valid email address.',
    'password.min' => 'Password must be at least 8 characters long.',
    'terms.required' => 'You must accept the terms and conditions.',
]);

$validator->validate();

// Additional custom validation
if ($data['password'] !== $data['confirm_password']) {
    return Problem::make(422, 'Validation Failed', 'Password confirmation does not match', '/problems/validation', [
        'confirm_password' => ['Password confirmation does not match.']
    ]);
}
```

### API Filter Validation

```php
// GET /users?limit=20&offset=0&status=active&sort=name
$queryParams = $request->query();

$validator = Validator::make($queryParams, [
    'limit' => 'int|min:1|max:100',
    'offset' => 'int|min:0',
    'status' => 'in:active,inactive,pending',
    'sort' => 'in:name,email,created_at',
    'order' => 'in:asc,desc',
]);

$validator->validate();
```

### File Upload Validation

```php
// Validate file metadata
$validator = Validator::make($data, [
    'filename' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
    'file_type' => 'required|in:image/jpeg,image/png,image/gif,application/pdf',
    'file_size' => 'required|int|max:5242880', // 5MB in bytes
]);
```

### Nested Data Validation

```php
// For API endpoints that accept complex nested data
$data = [
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ],
    'address' => [
        'street' => '123 Main St',
        'city' => 'Springfield',
        'zip' => '12345'
    ]
];

// Flatten the validation rules
$validator = Validator::make([
    'user_name' => $data['user']['name'] ?? null,
    'user_email' => $data['user']['email'] ?? null,
    'address_street' => $data['address']['street'] ?? null,
    'address_city' => $data['address']['city'] ?? null,
    'address_zip' => $data['address']['zip'] ?? null,
], [
    'user_name' => 'required|string|max:100',
    'user_email' => 'required|email',
    'address_street' => 'required|string|max:200',
    'address_city' => 'required|string|max:100',
    'address_zip' => 'required|string|regex:/^\d{5}$/',
]);
```

## Extending the Validator

### Adding Custom Validation Rules

You can extend the `Validator` class to add custom validation rules:

```php
class CustomValidator extends Validator
{
    /**
     * Validate that a field is a strong password
     */
    protected function validateStrongPassword(string $field, mixed $value, array $parameters): bool
    {
        if ($value === null || !is_string($value)) {
            return true; // Let 'required' rule handle null/empty values
        }

        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 symbol
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value);
    }

    /**
     * Validate that a field is a valid phone number
     */
    protected function validatePhone(string $field, mixed $value, array $parameters): bool
    {
        if ($value === null) {
            return true;
        }

        // International phone number format
        return preg_match('/^\+?[1-9]\d{1,14}$/', $value);
    }

    /**
     * Override getMessage to add custom error messages
     */
    protected function getMessage(string $field, string $rule, array $parameters = []): string
    {
        return match ($rule) {
            'strong_password' => "The {$field} must contain at least 8 characters with uppercase, lowercase, number and symbol.",
            'phone' => "The {$field} must be a valid phone number.",
            default => parent::getMessage($field, $rule, $parameters),
        };
    }
}
```

Usage:

```php
$validator = CustomValidator::make($data, [
    'password' => 'required|strong_password',
    'phone' => 'required|phone',
]);
```

## Best Practices

### 1. Validate Early and Often

```php
// Validate input as soon as possible
public function handle(Request $request): Response
{
    $validator = Validator::make($request->json(), $this->rules());
    $validator->validate(); // Fail fast

    // Continue with business logic
}
```

### 2. Use Appropriate Rules

```php
// Good - specific and appropriate
$rules = [
    'email' => 'required|email|max:255',
    'age' => 'required|int|min:13|max:120',
    'status' => 'required|in:active,inactive',
];

// Avoid - too generic or inappropriate
$rules = [
    'email' => 'required', // No format validation
    'age' => 'numeric',    // Could accept decimals
    'status' => 'string',  // No value constraints
];
```

### 3. Provide Clear Error Messages

```php
$messages = [
    'email.required' => 'Email address is required.',
    'email.email' => 'Please provide a valid email address.',
    'password.min' => 'Password must be at least :min characters.',
    'age.between' => 'Age must be between :min and :max years.',
];
```

### 4. Separate Validation Logic

```php
class UserController
{
    private function getUserValidationRules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ];
    }

    private function getUserValidationMessages(): array
    {
        return [
            'name.required' => 'Please provide your full name.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    public function create(Request $request): Response
    {
        $validator = Validator::make(
            $request->json(),
            $this->getUserValidationRules(),
            $this->getUserValidationMessages()
        );

        $validator->validate();
        // ...
    }
}
```

### 5. Handle Validation in Middleware

For common validation patterns, consider creating validation middleware:

```php
class ValidateJsonMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $contentType = $request->header('content-type');

        if (str_contains($contentType, 'application/json')) {
            $json = $request->json();
            if ($json === null && $request->getBody() !== '') {
                return Problem::make(400, 'Bad Request', 'Invalid JSON payload');
            }
        }

        return $next($request);
    }
}
```
