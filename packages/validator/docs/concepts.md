# Core Concepts

Understanding the core concepts of Lalaz Validator will help you build robust validation logic. This guide explains the fundamental building blocks.

## Overview

Lalaz Validator is built around four main concepts:

1. **Validator** - The engine that validates data
2. **Rules** - Definitions of what constitutes valid data
3. **RuleParser** - Converts string rules to normalized format
4. **Validatable** - Trait for adding validation to classes

Let's explore each one.

---

## Validator

### What is the Validator?

The **Validator** is the main class responsible for validating data against rules. It takes an array of data and an array of rules, and returns any validation errors.

Think of the Validator as a checkpoint inspector:
- It receives data to inspect
- It checks each field against its rules
- It reports any violations found

### Basic Usage

```php
use Lalaz\Validator\Validator;

$validator = new Validator();

// Validate data and get errors
$errors = $validator->validateData($data, $rules);

// Empty array = all valid
if (empty($errors)) {
    echo "All data is valid!";
}
```

### Validator Methods

```php
// Primary validation method - returns errors array
public function validateData(array $data, array $rules): array;

// Validates and throws exception on failure (for ORM)
public function validate(object $model, array $data, array $rules, string $operation): void;

// Alias for validate()
public function validateModel(object $model, array $data, array $rules, string $operation): void;
```

### Error Format

Errors are returned as an associative array:

```php
$errors = [
    'email' => ['email'],           // Failed email rule
    'age' => ['min:18'],            // Failed min rule
    'password' => ['required', 'min:8'], // Failed multiple rules
];
```

---

## Rules

### What are Rules?

**Rules** define what constitutes valid data for each field. They can be specified in three formats:

1. **String format** - Pipe-separated rule names
2. **Array format** - Array of rule definitions
3. **Rule builder** - Fluent API objects

### String Rules

The simplest way to define rules:

```php
$rules = [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => 'int|min:18',
];
```

**Format:** `rule1|rule2:param|rule3:param1,param2`

### Array Rules

More explicit format:

```php
$rules = [
    'name' => ['required', ['min' => 2], ['max' => 100]],
    'email' => ['required', 'email'],
];
```

### Rule Builder

Fluent API for building rules:

```php
use Lalaz\Validator\Rule;

$rules = [
    'email' => Rule::create()->required()->email(),
    'age' => Rule::create()->required()->int()->min(18)->max(120),
    'role' => Rule::create()->required()->in('admin', 'editor'),
];
```

### Available Rules

| Rule | Parameters | Description |
|------|------------|-------------|
| `required` | - | Field must not be empty |
| `email` | - | Valid email format |
| `url` | - | Valid URL format |
| `ip` | - | Valid IP address |
| `domain` | - | Valid domain name |
| `int` / `integer` | - | Integer value |
| `decimal` / `float` | - | Decimal number |
| `boolean` / `bool` | - | Boolean value |
| `min` | `n` | Minimum value (numbers) or length (strings) |
| `max` | `n` | Maximum value (numbers) or length (strings) |
| `match` | `field` | Must match another field's value |
| `confirmed` | - | Must have `{field}_confirmation` match |
| `in` | `a,b,c` | Must be one of the values |
| `not_in` | `a,b,c` | Must not be one of the values |
| `regex` | `pattern` | Must match regex pattern |
| `date` | - | Valid date string |
| `date_format` | `format` | Must match PHP date format |
| `json` | - | Valid JSON string |
| `custom` | `callback` | Custom validation function |

### Rule Behavior

**Required vs Optional:**
- Fields with `required` rule must have a non-empty value
- Fields without `required` are optional - validation skips empty values

```php
$rules = [
    'name' => 'required|min:2',  // Must exist and be >= 2 chars
    'website' => 'url',          // Optional, but if provided must be valid URL
];

// This passes:
$data = ['name' => 'John', 'website' => ''];

// This fails:
$data = ['name' => '', 'website' => 'https://example.com'];
```

---

## Rule Builder

### What is the Rule Builder?

The **Rule** class provides a fluent interface for building validation rules. It's an alternative to string rules that provides:

- Better IDE autocomplete
- Type safety
- Method documentation
- Complex rule composition

### Creating Rules

```php
use Lalaz\Validator\Rule;

// Start with create()
$rule = Rule::create();

// Chain methods
$rule = Rule::create()->required()->email();

// Complex rules
$rule = Rule::create()
    ->required()
    ->min(8)
    ->max(100)
    ->regex('/^[a-zA-Z]/')
    ->custom(fn($v) => strpos($v, 'admin') === false)
    ->message('Cannot contain "admin"');
```

### Rule Builder Methods

```php
// Basic rules
Rule::create()->required();
Rule::create()->email();
Rule::create()->url();
Rule::create()->ip();
Rule::create()->domain();
Rule::create()->date();
Rule::create()->json();

// Type rules
Rule::create()->int();       // or ->integer()
Rule::create()->decimal();   // or ->float()
Rule::create()->bool();      // or ->boolean()

// Size rules
Rule::create()->min(5);
Rule::create()->max(100);

// Comparison rules
Rule::create()->match('password');    // or ->same('password')
Rule::create()->confirmed();

// List rules
Rule::create()->in('a', 'b', 'c');
Rule::create()->notIn('x', 'y', 'z');

// Pattern rules
Rule::create()->regex('/^[A-Z]+$/');
Rule::create()->dateFormat('Y-m-d');

// Custom validation
Rule::create()->custom(function ($value, $allData) {
    return $value !== 'forbidden';
});

// Custom error message
Rule::create()->required()->message('This field is required');
```

### Rule Output

Rules can be converted to different formats:

```php
$rule = Rule::create()->required()->email()->min(5);

// As string
$string = (string) $rule;  // "required|email|min:5"
$string = $rule->build();  // Same

// As array
$array = $rule->buildArray();  // ['required', 'email', ['min' => 5]]
```

---

## RuleParser

### What is the RuleParser?

The **RuleParser** converts string rules into a normalized array format that the Validator can process. It handles:

- Splitting pipe-separated rules
- Parsing rule parameters
- Normalizing rule names
- Handling custom messages

### How It Works

```php
use Lalaz\Validator\RuleParser;

// Parse string rules
$parsed = RuleParser::parse('required|email|min:5');
// Result: [
//     'required',
//     'email',
//     ['name' => 'min', 'min' => 5]
// ]

// Parse array rules (returned as-is)
$parsed = RuleParser::parse(['required', 'email']);
// Result: ['required', 'email']
```

### Confirmed Rule Normalization

The parser has special handling for the `confirmed` rule:

```php
$rules = RuleParser::normalizeConfirmed('password', ['required', 'confirmed']);
// Result: [
//     'required',
//     ['name' => 'match', 'match' => 'password_confirmation']
// ]
```

---

## Validatable Trait

### What is the Validatable Trait?

The **Validatable** trait adds validation capabilities to any PHP class. It provides:

- Automatic property validation
- Fill method for data assignment
- Error handling and access
- Partial validation support

### Adding Validation to a Class

```php
use Lalaz\Validator\Concerns\Validatable;

class User
{
    use Validatable;

    public string $name = '';
    public string $email = '';
    public int $age = 0;

    // Define validation rules
    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'int|min:18',
        ];
    }
    
    // Optional: custom error messages
    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name',
            'email.email' => 'Please enter a valid email address',
        ];
    }
}
```

### Validatable Methods

```php
// Validation
$model->isValid();           // Returns bool
$model->validate();          // Returns $this or throws ValidationException
$model->isValidOnly(['name']); // Validate specific fields only
$model->isValidExcept(['age']); // Validate all except specific fields

// Error access
$model->errors();            // Get all errors ['field' => ['message', ...]]
$model->error('name');       // Get first error for field (or null)
$model->hasError('name');    // Check if field has error
$model->errorMessages();     // Get flat array of all messages
$model->firstError();        // Get first error message
$model->clearErrors();       // Clear all errors

// Data filling
$model->fill($data);         // Fill properties from array
$model->fill($data, true);   // Fill and validate (throws on failure)
$model->fillAndValidate($data); // Same as fill($data, true)

// Factory
User::createValidated($data); // Create instance and validate
```

### How Data is Extracted

The trait automatically extracts data for validation:

1. Public properties are collected
2. If `toArray()` method exists, it's used instead
3. If `getAttributes()` method exists, it's used (for ORM models)

### Type Casting

The `fill()` method automatically casts values to match property types:

```php
class User
{
    use Validatable;
    
    public string $name;
    public int $age;
    public bool $active;
}

$user = new User();
$user->fill([
    'name' => 123,      // Cast to "123"
    'age' => '25',      // Cast to 25
    'active' => '1',    // Cast to true
]);
```

---

## ValidationException

### What is ValidationException?

The **ValidationException** is thrown when validation fails. It contains all validation errors and can be caught to handle failures.

### When It's Thrown

```php
// Thrown by Validator::validate()
$validator->validate($model, $data, $rules, 'create');

// Thrown by Validatable::validate()
$user->validate();

// Thrown by Validatable::fillAndValidate()
$user->fillAndValidate($data);
```

### Catching Exceptions

```php
use Lalaz\Validator\ValidationException;

try {
    $user->fillAndValidate($request->all());
    // Success - save user
} catch (ValidationException $e) {
    $errors = $e->errors();
    // Handle validation failure
}
```

### Exception Methods

```php
$exception->getMessage();  // "Validation failed."
$exception->errors();      // ['field' => ['message', ...], ...]
```

---

## Request Flow

Here's how validation works during a typical request:

```
1. Request arrives
        │
        ▼
2. Data extracted from request
        │
        ▼
3. Rules defined (string, array, or builder)
        │
        ├─── String: "required|email"
        ├─── Array: ['required', 'email']
        └─── Builder: Rule::create()->required()->email()
                │
                ▼
4. RuleParser normalizes rules
        │
        ▼
5. Validator processes each field
        │
        ├─── Check required
        ├─── Skip empty if not required
        └─── Apply each rule
                │
                ▼
6. Collect errors (empty = valid)
        │
        ├─── []  → Success
        └─── [...] → Validation failed
```

---

## Putting It Together

Here's a complete example showing all concepts:

```php
<?php

use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Concerns\Validatable;

// === MODEL WITH VALIDATION ===
class RegistrationForm
{
    use Validatable;

    public string $username = '';
    public string $email = '';
    public string $password = '';
    public int $age = 0;

    protected function rules(): array
    {
        return [
            'username' => Rule::create()
                ->required()
                ->min(3)
                ->max(20)
                ->regex('/^[a-z0-9_]+$/'),
            'email' => 'required|email',
            'password' => Rule::create()
                ->required()
                ->min(8)
                ->confirmed(),
            'age' => 'required|int|min:18',
        ];
    }
}

// === CONTROLLER ===
class RegistrationController
{
    public function register($request, $response)
    {
        $form = new RegistrationForm();
        
        try {
            // Fill and validate in one step
            $form->fillAndValidate($request->all());
            
            // Validation passed - create user
            $user = User::create([
                'username' => $form->username,
                'email' => $form->email,
                'password' => password_hash($form->password, PASSWORD_DEFAULT),
                'age' => $form->age,
            ]);
            
            return $response->json(['user' => $user], 201);
            
        } catch (ValidationException $e) {
            // Validation failed - return errors
            return $response->json(['errors' => $e->errors()], 422);
        }
    }
}

// === ALTERNATIVE: Manual Validation ===
class ManualValidationController
{
    public function register($request, $response)
    {
        $validator = new Validator();
        
        $errors = $validator->validateData($request->all(), [
            'username' => 'required|min:3|max:20|regex:/^[a-z0-9_]+$/',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'age' => 'required|int|min:18',
        ]);
        
        if (!empty($errors)) {
            return $response->json(['errors' => $errors], 422);
        }
        
        // Create user...
    }
}
```

---

## Summary

| Concept | Purpose | Key Class/Trait |
|---------|---------|-----------------|
| **Validator** | Validates data against rules | `Validator` |
| **Rules** | Define valid data criteria | String, Array, or `Rule` builder |
| **RuleParser** | Normalize rule definitions | `RuleParser` |
| **Validatable** | Add validation to classes | `Validatable` trait |
| **ValidationException** | Report validation failures | `ValidationException` |

## Next Steps

- Explore all [Built-in Rules](#built-in-rules)
- Learn about [Custom Validation](#custom-rules)
- See the complete [API Reference](./api-reference.md)
