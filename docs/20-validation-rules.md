# Validation Rules

LeanPHP provides a comprehensive validation system with a rich set of built-in rules for validating user input, API data, and form submissions. The validation system is designed to be type-safe, flexible, and easy to use while providing clear error messages and excellent performance.

## 🏗️ Validation Architecture

### Core Components

The validation system consists of three main components:

1. **Validator Class** (`src/Validation/Validator.php`): Core validation engine
2. **ValidationException** (`src/Validation/ValidationException.php`): Exception for validation failures
3. **Error Handler Integration**: Automatic conversion to HTTP Problem responses

### Validator Class Structure

```php
class Validator
{
    private array $data;            // Data to validate
    private array $rules;           // Validation rules
    private array $errors = [];     // Validation errors
    private array $customMessages = []; // Custom error messages

    public static function make(array $data, array $rules, array $customMessages = []): self
    public function passes(): bool
    public function fails(): bool
    public function validate(): void
    public function errors(): array
}
```

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

## 📋 Complete Validation Rules Reference

### Required Rules

#### `required`
Field must be present and not empty.

```php
$rules = ['name' => 'required'];

// Valid: "John", "0", 0, false
// Invalid: null, "", [], empty array
```

**Implementation:**
```php
protected function validateRequired(string $field, mixed $value, array $parameters): bool
{
    if ($value === null || $value === '') {
        return false;
    }

    if (is_array($value) && empty($value)) {
        return false;
    }

    return true;
}
```

**Use Cases:**
- Required form fields
- Mandatory API parameters
- Essential configuration values

### Type Validation Rules

#### `string`
Field must be a string.

```php
$rules = ['name' => 'string'];

// Valid: "John", ""
// Invalid: 123, true, []
```

**Implementation:**
```php
protected function validateString(string $field, mixed $value, array $parameters): bool
{
    return $value === null || is_string($value);
}
```

#### `int`
Field must be an integer or numeric string.

```php
$rules = ['age' => 'int'];

// Valid: 25, "25" (numeric string)
// Invalid: "abc", 25.5, true
```

**Implementation:**
```php
protected function validateInt(string $field, mixed $value, array $parameters): bool
{
    return $value === null || is_int($value) || (is_string($value) && ctype_digit($value));
}
```

#### `numeric`
Field must be numeric (integer or float).

```php
$rules = ['price' => 'numeric'];

// Valid: 25, 25.50, "25", "25.50"
// Invalid: "abc", true, []
```

**Implementation:**
```php
protected function validateNumeric(string $field, mixed $value, array $parameters): bool
{
    return $value === null || is_numeric($value);
}
```

#### `boolean`
Field must be a boolean value or boolean-equivalent.

```php
$rules = ['active' => 'boolean'];

// Valid: true, false, 1, 0, "1", "0", "true", "false"
// Invalid: "yes", 2, "maybe"
```

**Implementation:**
```php
protected function validateBoolean(string $field, mixed $value, array $parameters): bool
{
    $acceptable = [true, false, 0, 1, '0', '1', 'true', 'false'];
    return $value === null || in_array($value, $acceptable, true);
}
```

#### `array`
Field must be an array.

```php
$rules = ['tags' => 'array'];

// Valid: [], ["tag1", "tag2"]
// Invalid: "tag1", 123, null
```

**Implementation:**
```php
protected function validateArray(string $field, mixed $value, array $parameters): bool
{
    return $value === null || is_array($value);
}
```

### Format Validation Rules

#### `email`
Field must be a valid email address.

```php
$rules = ['email' => 'email'];

// Valid: "user@example.com", "test+tag@domain.co.uk"
// Invalid: "invalid-email", "user@", "@domain.com"
```

**Implementation:**
```php
protected function validateEmail(string $field, mixed $value, array $parameters): bool
{
    return $value === null || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}
```

**Use Cases:**
- User registration forms
- Contact information
- API user creation

#### `url`
Field must be a valid URL.

```php
$rules = ['website' => 'url'];

// Valid: "https://example.com", "http://localhost:8000"
// Invalid: "invalid-url", "example", "ftp://invalid"
```

**Implementation:**
```php
protected function validateUrl(string $field, mixed $value, array $parameters): bool
{
    return $value === null || filter_var($value, FILTER_VALIDATE_URL) !== false;
}
```

#### `regex:pattern`
Field must match the specified regular expression.

```php
$rules = [
    'phone' => 'regex:/^\+?[1-9]\d{1,14}$/',     // International phone
    'slug' => 'regex:/^[a-z0-9-]+$/',            // URL slug
    'postal_code' => 'regex:/^\d{5}(-\d{4})?$/', // US ZIP code
];
```

**Implementation:**
```php
protected function validateRegex(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    if (!is_string($value)) {
        return false;
    }

    // Ensure the regex pattern has delimiters
    $pattern = $parameters[0];
    if (!str_starts_with($pattern, '/') && !str_starts_with($pattern, '#')) {
        $pattern = '/' . $pattern . '/';
    }

    return preg_match($pattern, $value) > 0;
}
```

**Common Patterns:**
```php
// Phone numbers
'phone' => 'regex:/^\+?[1-9]\d{1,14}$/'

// Usernames (alphanumeric + underscore)
'username' => 'regex:/^[a-zA-Z0-9_]{3,20}$/'

// Password strength (8+ chars, mixed case, numbers, symbols)
'password' => 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'

// Hexadecimal color codes
'color' => 'regex:/^#[a-fA-F0-9]{6}$/'
```

### Size Validation Rules

#### `min:value`
Field must have minimum value/length/count.

```php
$rules = [
    'password' => 'min:8',      // String: minimum 8 characters
    'age' => 'numeric|min:18',  // Number: minimum value 18
    'tags' => 'array|min:1',    // Array: minimum 1 element
];
```

**Implementation:**
```php
protected function validateMin(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    $min = (float) $parameters[0];

    if (is_numeric($value)) {
        return (float) $value >= $min;
    }

    if (is_string($value)) {
        return strlen($value) >= $min;
    }

    if (is_array($value)) {
        return count($value) >= $min;
    }

    return false;
}
```

#### `max:value`
Field must not exceed maximum value/length/count.

```php
$rules = [
    'name' => 'max:100',        // String: maximum 100 characters
    'age' => 'numeric|max:120', // Number: maximum value 120
    'tags' => 'array|max:10',   // Array: maximum 10 elements
];
```

**Implementation:**
```php
protected function validateMax(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    $max = (float) $parameters[0];

    if (is_numeric($value)) {
        return (float) $value <= $max;
    }

    if (is_string($value)) {
        return strlen($value) <= $max;
    }

    if (is_array($value)) {
        return count($value) <= $max;
    }

    return false;
}
```

#### `between:min,max`
Field must be between minimum and maximum values.

```php
$rules = [
    'username' => 'between:3,20',    // String: 3-20 characters
    'rating' => 'numeric|between:1,5', // Number: 1-5 range
    'items' => 'array|between:1,10',   // Array: 1-10 elements
];
```

**Implementation:**
```php
protected function validateBetween(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    $min = (float) $parameters[0];
    $max = (float) $parameters[1];

    if (is_numeric($value)) {
        $numValue = (float) $value;
        return $numValue >= $min && $numValue <= $max;
    }

    if (is_string($value)) {
        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    if (is_array($value)) {
        $count = count($value);
        return $count >= $min && $count <= $max;
    }

    return false;
}
```

### Choice Validation Rules

#### `in:value1,value2,value3`
Field must be one of the specified values.

```php
$rules = [
    'status' => 'in:active,inactive,pending',
    'role' => 'in:admin,user,moderator',
    'priority' => 'in:low,medium,high,urgent',
];
```

**Implementation:**
```php
protected function validateIn(string $field, mixed $value, array $parameters): bool
{
    return $value === null || in_array($value, $parameters, true);
}
```

**Use Cases:**
- Enum-like values
- Status fields
- Dropdown selections
- API parameter validation

#### `not_in:value1,value2,value3`
Field must not be one of the specified values.

```php
$rules = [
    'username' => 'not_in:admin,root,administrator',
    'password' => 'not_in:password,123456,qwerty',
];
```

**Implementation:**
```php
protected function validateNotIn(string $field, mixed $value, array $parameters): bool
{
    return $value === null || !in_array($value, $parameters, true);
}
```

**Use Cases:**
- Reserved usernames
- Blacklisted values
- Forbidden passwords

### Date Validation Rules

#### `date`
Field must be a valid date string.

```php
$rules = ['birthday' => 'date'];

// Valid: "2023-01-01", "January 1, 2023", "2023/01/01"
// Invalid: "invalid-date", "2023-13-01", "abc"
```

**Implementation:**
```php
protected function validateDate(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    if (!is_string($value)) {
        return false;
    }

    return strtotime($value) !== false;
}
```

#### `before:date`
Field must be a date before the specified date.

```php
$rules = [
    'start_date' => 'date|before:2024-01-01',
    'birthday' => 'date|before:today',
    'deadline' => 'date|before:2024-12-31',
];
```

**Implementation:**
```php
protected function validateBefore(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    $before = strtotime($parameters[0]);
    $valueTime = strtotime($value);

    return $valueTime !== false && $before !== false && $valueTime < $before;
}
```

#### `after:date`
Field must be a date after the specified date.

```php
$rules = [
    'end_date' => 'date|after:2023-01-01',
    'event_date' => 'date|after:now',
    'expiry_date' => 'date|after:today',
];
```

**Implementation:**
```php
protected function validateAfter(string $field, mixed $value, array $parameters): bool
{
    if ($value === null) {
        return true;
    }

    $after = strtotime($parameters[0]);
    $valueTime = strtotime($value);

    return $valueTime !== false && $after !== false && $valueTime > $after;
}
```

**Special Date Values:**
- `now`: Current timestamp
- `today`: Start of current day
- `tomorrow`: Start of next day
- `yesterday`: Start of previous day

## 🔗 Rule Combinations

### Pipe Separator Syntax

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

### Array Syntax

Rules can also be specified as arrays for more complex scenarios:

```php
$rules = [
    'email' => ['required', 'email', 'max:255'],
    'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/'],
];
```

### Common Rule Combinations

```php
// User registration
$userRules = [
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
    'age' => 'required|int|min:13|max:120',
    'terms' => 'required|boolean',
];

// API filters
$filterRules = [
    'limit' => 'int|min:1|max:100',
    'offset' => 'int|min:0',
    'sort' => 'in:name,email,created_at',
    'order' => 'in:asc,desc',
];

// File upload metadata
$fileRules = [
    'filename' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
    'file_type' => 'required|in:image/jpeg,image/png,application/pdf',
    'file_size' => 'required|int|max:5242880', // 5MB
];
```

## 💬 Custom Error Messages

### Field-Rule Specific Messages

```php
$messages = [
    'email.required' => 'Please provide your email address.',
    'email.email' => 'Please provide a valid email address.',
    'password.min' => 'Password must be at least :min characters.',
    'age.between' => 'Age must be between :min and :max years.',
];

$validator = Validator::make($data, $rules, $messages);
```

### Parameter Replacement

Error messages support parameter replacement using `:parameter` syntax:

```php
$messages = [
    'password.min' => 'Password must be at least :min characters long.',
    'age.between' => 'Age must be between :min and :max years old.',
    'rating.in' => 'Rating must be one of: :values.',
];
```

### Default Error Messages

The validator provides sensible default messages for all rules:

```php
private function getDefaultMessage(string $field, string $rule, array $parameters): string
{
    return match ($rule) {
        'required' => "The {$field} field is required.",
        'string' => "The {$field} must be a string.",
        'int' => "The {$field} must be an integer.",
        'numeric' => "The {$field} must be a number.",
        'boolean' => "The {$field} must be true or false.",
        'email' => "The {$field} must be a valid email address.",
        'url' => "The {$field} must be a valid URL.",
        'min' => "The {$field} must be at least {$parameters[0]}.",
        'max' => "The {$field} may not be greater than {$parameters[0]}.",
        'between' => "The {$field} must be between {$parameters[0]} and {$parameters[1]}.",
        'regex' => "The {$field} format is invalid.",
        'in' => "The selected {$field} is invalid.",
        'not_in' => "The selected {$field} is invalid.",
        'array' => "The {$field} must be an array.",
        'date' => "The {$field} is not a valid date.",
        'before' => "The {$field} must be a date before {$parameters[0]}.",
        'after' => "The {$field} must be a date after {$parameters[0]}.",
        default => "The {$field} is invalid.",
    };
}
```

## 🛠️ Validation Methods

### Static Factory Method

```php
public static function make(array $data, array $rules, array $customMessages = []): self
```

Creates a new validator instance with the provided data, rules, and custom messages.

### Validation Status Methods

#### `passes()`
Returns `true` if all validation rules pass.

```php
if ($validator->passes()) {
    // All validation rules passed
    $user = createUser($data);
}
```

#### `fails()`
Returns `true` if any validation rules fail.

```php
if ($validator->fails()) {
    // Some validation rules failed
    return Response::json(['errors' => $validator->errors()], 422);
}
```

### Exception-Based Validation

#### `validate()`
Validates data and throws `ValidationException` on failure.

```php
try {
    $validator->validate();
    // Continue with valid data
    $user = createUser($data);
} catch (ValidationException $e) {
    // Exception is automatically handled by ErrorHandler middleware
    throw $e;
}
```

### Error Retrieval

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

## 🔧 Extending the Validator

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
     * Validate that a field contains only alphanumeric characters
     */
    protected function validateAlphanumeric(string $field, mixed $value, array $parameters): bool
    {
        if ($value === null || !is_string($value)) {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    /**
     * Override getMessage to add custom error messages
     */
    protected function getMessage(string $field, string $rule, array $parameters = []): string
    {
        return match ($rule) {
            'strong_password' => "The {$field} must contain at least 8 characters with uppercase, lowercase, number and symbol.",
            'phone' => "The {$field} must be a valid phone number.",
            'alphanumeric' => "The {$field} may only contain letters and numbers.",
            default => parent::getMessage($field, $rule, $parameters),
        };
    }
}
```

### Usage of Custom Validator

```php
$validator = CustomValidator::make($data, [
    'password' => 'required|strong_password',
    'phone' => 'required|phone',
    'username' => 'required|alphanumeric|min:3|max:20',
]);

$validator->validate();
```

### Rule Method Naming Convention

Custom validation methods must follow this naming convention:
- Method name: `validate` + PascalCase rule name
- Rule `phone_number` → method `validatePhoneNumber`
- Rule `strong_password` → method `validateStrongPassword`

## 📝 Real-World Examples

### User Registration Validation

```php
public function register(Request $request): Response
{
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

    $data = $request->json();

    // Additional custom validation
    if ($data['password'] !== $data['confirm_password']) {
        return Problem::make(422, 'Validation Failed',
            'Password confirmation does not match', '/problems/validation', [
                'confirm_password' => ['Password confirmation does not match.']
            ]);
    }

    // Create user...
    return Response::json(['message' => 'User created successfully'], 201);
}
```

### API Filter Validation

```php
public function index(Request $request): Response
{
    // GET /users?limit=20&offset=0&status=active&sort=name&order=desc
    $queryParams = $request->query();

    $validator = Validator::make($queryParams, [
        'limit' => 'int|min:1|max:100',
        'offset' => 'int|min:0',
        'status' => 'in:active,inactive,pending',
        'sort' => 'in:name,email,created_at',
        'order' => 'in:asc,desc',
        'search' => 'string|max:255',
    ]);

    $validator->validate();

    // Apply filters and return users...
}
```

### File Upload Validation

```php
public function uploadFile(Request $request): Response
{
    $data = $request->json();

    // Validate file metadata
    $validator = Validator::make($data, [
        'filename' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
        'file_type' => 'required|in:image/jpeg,image/png,image/gif,application/pdf',
        'file_size' => 'required|int|max:5242880', // 5MB in bytes
        'description' => 'string|max:1000',
    ], [
        'filename.regex' => 'Filename can only contain letters, numbers, dots, underscores, and hyphens.',
        'file_type.in' => 'File type must be JPEG, PNG, GIF, or PDF.',
        'file_size.max' => 'File size cannot exceed 5MB.',
    ]);

    $validator->validate();

    // Process file upload...
}
```

### Nested Data Validation

```php
public function createOrder(Request $request): Response
{
    $data = $request->json();

    // For API endpoints that accept complex nested data
    $flattenedData = [
        'customer_name' => $data['customer']['name'] ?? null,
        'customer_email' => $data['customer']['email'] ?? null,
        'customer_phone' => $data['customer']['phone'] ?? null,
        'shipping_address' => $data['shipping']['address'] ?? null,
        'shipping_city' => $data['shipping']['city'] ?? null,
        'shipping_zip' => $data['shipping']['zip'] ?? null,
        'items' => $data['items'] ?? null,
        'total_amount' => $data['total_amount'] ?? null,
    ];

    $validator = Validator::make($flattenedData, [
        'customer_name' => 'required|string|max:100',
        'customer_email' => 'required|email',
        'customer_phone' => 'required|regex:/^\+?[1-9]\d{1,14}$/',
        'shipping_address' => 'required|string|max:200',
        'shipping_city' => 'required|string|max:100',
        'shipping_zip' => 'required|string|regex:/^\d{5}$/',
        'items' => 'required|array|min:1',
        'total_amount' => 'required|numeric|min:0.01',
    ]);

    $validator->validate();

    // Create order...
}
```

## 🚀 Performance Considerations

### Validation Efficiency

- **Early Termination**: Validation stops at first failure per field
- **Null Handling**: Most rules return `true` for `null` values (handled by `required`)
- **Type Checking**: Fast type checks before expensive operations
- **Regex Compilation**: Patterns are compiled once per validation

### Memory Usage

- **Minimal State**: Validator stores only necessary data
- **Error Collection**: Errors collected efficiently in associative arrays
- **Parameter Parsing**: Rule parameters parsed on-demand

### Best Practices for Performance

```php
// Good - specific and fast
$rules = [
    'id' => 'required|int|min:1',
    'status' => 'required|in:active,inactive',
];

// Avoid - unnecessary complexity
$rules = [
    'id' => 'required|string|regex:/^\d+$/|min:1', // int rule is faster
    'status' => 'required|regex:/^(active|inactive)$/', // in rule is faster
];
```

## 🛡️ Security Considerations

### Input Sanitization

Validation rules help prevent various security issues:

```php
// Prevent XSS
'comment' => 'required|string|max:1000',

// Prevent SQL injection through type validation
'user_id' => 'required|int|min:1',

// Validate file types
'file_type' => 'required|in:image/jpeg,image/png,application/pdf',

// Validate URLs to prevent SSRF
'callback_url' => 'required|url|regex:/^https:\/\//',
```

### Data Validation Best Practices

1. **Validate Early**: Use validation middleware before controllers
2. **Type Safety**: Use specific type rules (`int`, `string`, `boolean`)
3. **Length Limits**: Always set reasonable `max` values
4. **Whitelist Approach**: Use `in` rules for known values
5. **Regex Validation**: Validate format for user input

### Secure Validation Patterns

```php
// Secure user input validation
$secureRules = [
    'username' => 'required|string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/',
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|max:255',
    'role' => 'required|in:user,moderator', // Never allow 'admin' here
    'age' => 'required|int|min:13|max:120',
    'website' => 'url|regex:/^https:\/\//', // Only HTTPS URLs
];
```

## 📊 Testing Validation Rules

### Unit Testing Custom Rules

```php
class CustomValidatorTest extends TestCase
{
    public function test_strong_password_validation(): void
    {
        $validator = CustomValidator::make(
            ['password' => 'Password123!'],
            ['password' => 'strong_password']
        );

        $this->assertTrue($validator->passes());

        $validator = CustomValidator::make(
            ['password' => 'weak'],
            ['password' => 'strong_password']
        );

        $this->assertTrue($validator->fails());
    }
}
```

### Integration Testing

```php
class UserControllerTest extends TestCase
{
    public function test_registration_validates_required_fields(): void
    {
        $response = $this->post('/api/register', [
            // Missing required fields
        ]);

        $response->assertStatus(422);
        $response->assertJsonHas('errors.name');
        $response->assertJsonHas('errors.email');
    }

    public function test_registration_validates_email_format(): void
    {
        $response = $this->post('/api/register', [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.email.0', 'The email must be a valid email address.');
    }
}
```
