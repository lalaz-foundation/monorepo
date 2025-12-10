# Glossary

A reference guide to validation terminology used throughout the Lalaz Validator package.

---

## Core Concepts

### Validation
The process of checking if data meets defined requirements. Validation ensures data integrity before processing or storing it.

> "Validation answers: *Is this data acceptable?*"

### Validator
The main class that performs validation. It takes data and rules, then returns any errors found.

```php
$validator = new Validator();
$errors = $validator->validateData($data, $rules);
```

### Rule
A single requirement that data must meet. Rules can be simple (like `required`) or parameterized (like `min:5`).

**Examples:** `required`, `email`, `min:18`, `in:admin,user`

---

## Rule Formats

### String Rules
Rules defined as a pipe-separated string. The most concise format.

```php
'name' => 'required|min:2|max:100'
```

### Array Rules
Rules defined as a PHP array. More explicit than strings.

```php
'name' => ['required', ['min' => 2], ['max' => 100]]
```

### Rule Builder
A fluent API for building rules programmatically.

```php
'name' => Rule::create()->required()->min(2)->max(100)
```

---

## Built-in Rules

### Required
The field must be present and not empty. Empty string `''` and `null` both fail.

```php
'name' => 'required'
```

### Email
Must be a valid email address format.

```php
'email' => 'email'
```

### URL
Must be a valid URL format.

```php
'website' => 'url'
```

### IP
Must be a valid IP address (IPv4 or IPv6).

```php
'server' => 'ip'
```

### Domain
Must be a valid domain name.

```php
'site' => 'domain'
```

### Integer (int)
Must be an integer value. String integers like `"25"` are accepted.

```php
'age' => 'int'
```

### Decimal (float)
Must be a decimal/floating-point number.

```php
'price' => 'decimal'
```

### Boolean (bool)
Must be a true boolean value (`true` or `false`). Note: strings like `"true"` fail.

```php
'active' => 'boolean'
```

### Min
Minimum value for numbers, or minimum length for strings.

```php
'age' => 'min:18'      // Number >= 18
'name' => 'min:2'      // String length >= 2
```

### Max
Maximum value for numbers, or maximum length for strings.

```php
'age' => 'max:120'     // Number <= 120
'bio' => 'max:500'     // String length <= 500
```

### Match
Field must have the same value as another field.

```php
'password_confirm' => 'match:password'
```

### Confirmed
Shorthand for matching a `{field}_confirmation` field.

```php
'password' => 'confirmed'
// Checks against 'password_confirmation' field
```

### In
Value must be one of the specified options.

```php
'role' => 'in:admin,editor,viewer'
```

### Not In
Value must NOT be one of the specified options.

```php
'username' => 'not_in:admin,root,system'
```

### Regex
Value must match a regular expression pattern.

```php
'code' => 'regex:/^[A-Z]{3}[0-9]{3}$/'
```

### Date
Must be a valid date string parseable by PHP.

```php
'birthday' => 'date'
```

### Date Format
Must match a specific PHP date format.

```php
'appointment' => 'date_format:Y-m-d'
'timestamp' => 'date_format:Y-m-d H:i:s'
```

### JSON
Must be a valid JSON string.

```php
'config' => 'json'
```

### Custom
A user-defined validation callback.

```php
'code' => Rule::create()->custom(fn($value) => strlen($value) === 6)
```

---

## Validation States

### Valid
Data that passes all validation rules. Returns an empty errors array.

```php
$errors = [];  // All valid
```

### Invalid
Data that fails one or more validation rules. Returns errors array with messages.

```php
$errors = ['email' => ['email']];  // Invalid
```

### Optional
A field without the `required` rule. Validation is skipped if the field is empty.

```php
'website' => 'url'  // Only validates if provided
```

---

## Classes

### Validator
The main validation engine. Processes data against rules.

```php
use Lalaz\Validator\Validator;

$validator = new Validator();
```

### Rule
Fluent builder for creating validation rules.

```php
use Lalaz\Validator\Rule;

$rule = Rule::create()->required()->email();
```

### RuleParser
Internal class that converts string rules to normalized format.

```php
use Lalaz\Validator\RuleParser;

$parsed = RuleParser::parse('required|email');
```

### ValidationException
Exception thrown when validation fails. Contains all errors.

```php
use Lalaz\Validator\ValidationException;

try {
    $user->validate();
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

### Validatable (Trait)
Trait that adds validation capabilities to any class.

```php
use Lalaz\Validator\Concerns\Validatable;

class User {
    use Validatable;
}
```

### OrmValidatorAdapter
Adapter that connects the Validator with ORM models.

```php
use Lalaz\Validator\Adapters\OrmValidatorAdapter;
```

---

## Error Handling

### Errors Array
The format returned by validation failures:

```php
[
    'field_name' => ['error_message_1', 'error_message_2'],
    'other_field' => ['error_message'],
]
```

### Error Message
A string describing what validation failed. Can be the rule name or a custom message.

```php
// Default message (rule name)
'email'

// Custom message
'Please enter a valid email address'
```

### Custom Message
A user-defined error message attached to a rule.

```php
Rule::create()->email()->message('Invalid email format')
```

---

## Validatable Methods

### isValid()
Check if the model data passes validation.

```php
if ($user->isValid()) {
    // Data is valid
}
```

### validate()
Validate and throw exception on failure. Returns `$this` on success.

```php
$user->validate();  // Throws if invalid
```

### fill()
Populate model properties from an array.

```php
$user->fill(['name' => 'John', 'email' => 'john@example.com']);
```

### fillAndValidate()
Populate and validate in one step. Throws on failure.

```php
$user->fillAndValidate($data);
```

### errors()
Get all validation errors after calling `isValid()`.

```php
$errors = $user->errors();
```

### error()
Get the first error message for a specific field.

```php
$message = $user->error('email');  // 'email' or null
```

### hasError()
Check if a specific field has validation errors.

```php
if ($user->hasError('email')) {
    // Email field has errors
}
```

### clearErrors()
Remove all stored validation errors.

```php
$user->clearErrors();
```

---

## Partial Validation

### isValidOnly()
Validate only specific fields, ignoring others.

```php
$user->isValidOnly(['name', 'email']);
```

### isValidExcept()
Validate all fields except specified ones.

```php
$user->isValidExcept(['password']);
```

---

## Type Casting

### Auto Casting
The `fill()` method automatically converts values to match property types:

| Property Type | Input | Result |
|--------------|-------|--------|
| `string` | `123` | `"123"` |
| `int` | `"25"` | `25` |
| `float` | `"3.14"` | `3.14` |
| `bool` | `"1"` | `true` |
| `bool` | `"true"` | `true` |
| `bool` | `"on"` | `true` |
| `bool` | `"0"` | `false` |

---

## See Also

- [Core Concepts](./concepts.md) — Detailed explanation of Validator architecture
- [Quick Start](./quick-start.md) — Get started in 5 minutes
- [API Reference](./api-reference.md) — Complete method documentation

---

<p align="center">
  <sub>Can't find a term? <a href="https://github.com/lalaz-foundation/framework/issues">Open an issue</a> and we'll add it!</sub>
</p>
