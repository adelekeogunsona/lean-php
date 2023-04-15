<?php

declare(strict_types=1);

namespace LeanPHP\Validation;

use LeanPHP\Http\Problem;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $customMessages = [];

    public function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    /**
     * Create a new validator instance.
     */
    public static function make(array $data, array $rules, array $customMessages = []): self
    {
        return new self($data, $rules, $customMessages);
    }

    /**
     * Determine if the data passes all validation rules.
     */
    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }

        return empty($this->errors);
    }

    /**
     * Determine if the data fails validation.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get the validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Validate and return 422 Problem response if validation fails.
     */
    public function validate(): void
    {
        if ($this->fails()) {
            $problem = Problem::make(
                422,
                'Unprocessable Content',
                'The given data was invalid.',
                '/problems/validation'
            );

            // Add errors to the problem response
            $body = json_decode($problem->getBody(), true);
            $body['errors'] = $this->errors();
            $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encodedBody === false) {
                $encodedBody = '{"error": "Failed to encode validation errors"}';
            }
            $problem = $problem->setBody($encodedBody);

            throw new ValidationException($problem);
        }
    }

    /**
     * Validate a single field against its rules.
     */
    private function validateField(string $field, string|array $ruleSet): void
    {
        $value = $this->getValue($field);
        $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Get a value from the data array.
     */
    private function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * Apply a validation rule to a field.
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        // Call the appropriate validation method
        $methodName = str_replace('_', '', ucfirst($ruleName));
        $method = 'validate' . $methodName;

        if (method_exists($this, $method)) {
            $passes = $this->$method($field, $value, $parameters);

            if (!$passes) {
                $this->addError($field, $ruleName, $parameters);
            }
        } else {
            throw new \InvalidArgumentException("Validation rule '{$ruleName}' does not exist.");
        }
    }

    /**
     * Add an error message for a field.
     */
    private function addError(string $field, string $rule, array $parameters = []): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $message = $this->getMessage($field, $rule, $parameters);
        $this->errors[$field][] = $message;
    }

    /**
     * Get error message for a rule.
     */
    private function getMessage(string $field, string $rule, array $parameters = []): string
    {
        // Check for custom message
        $customKey = "{$field}.{$rule}";
        if (isset($this->customMessages[$customKey])) {
            return $this->customMessages[$customKey];
        }

        // Default messages
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

    // Validation rule methods

    /**
     * Validate that a field is present and not empty.
     */
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

    /**
     * Validate that a field is a string.
     */
    protected function validateString(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || is_string($value);
    }

    /**
     * Validate that a field is an integer.
     */
    protected function validateInt(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || is_int($value) || (is_string($value) && ctype_digit($value));
    }

    /**
     * Validate that a field is numeric.
     */
    protected function validateNumeric(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || is_numeric($value);
    }

    /**
     * Validate that a field is a boolean.
     */
    protected function validateBoolean(string $field, mixed $value, array $parameters): bool
    {
        $acceptable = [true, false, 0, 1, '0', '1', 'true', 'false'];
        return $value === null || in_array($value, $acceptable, true);
    }

    /**
     * Validate that a field is a valid email address.
     */
    protected function validateEmail(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that a field is a valid URL.
     */
    protected function validateUrl(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate that a field has a minimum value.
     */
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

    /**
     * Validate that a field has a maximum value.
     */
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

    /**
     * Validate that a field is between two values.
     */
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

    /**
     * Validate that a field matches a regular expression.
     */
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

    /**
     * Validate that a field is in a list of values.
     */
    protected function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || in_array($value, $parameters, true);
    }

    /**
     * Validate that a field is not in a list of values.
     */
    protected function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || !in_array($value, $parameters, true);
    }

    /**
     * Validate that a field is an array.
     */
    protected function validateArray(string $field, mixed $value, array $parameters): bool
    {
        return $value === null || is_array($value);
    }

    /**
     * Validate that a field is a valid date.
     */
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

    /**
     * Validate that a field is a date before another date.
     */
    protected function validateBefore(string $field, mixed $value, array $parameters): bool
    {
        if ($value === null) {
            return true;
        }

        $before = strtotime($parameters[0]);
        $valueTime = strtotime($value);

        return $valueTime !== false && $before !== false && $valueTime < $before;
    }

    /**
     * Validate that a field is a date after another date.
     */
    protected function validateAfter(string $field, mixed $value, array $parameters): bool
    {
        if ($value === null) {
            return true;
        }

        $after = strtotime($parameters[0]);
        $valueTime = strtotime($value);

        return $valueTime !== false && $after !== false && $valueTime > $after;
    }
}
