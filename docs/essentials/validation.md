# Validation

Lalaz provides a powerful validation system for validating arrays of data against defined rules. Use it to validate form submissions, API requests, or any data that needs to conform to specific constraints.

## Basic Usage

### Using the Validator Class

The simplest way to validate data:

```php
use Lalaz\Validator\Validator;

$validator = new Validator();

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
];

$rules = [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => 'required|int|min:18',
];

$errors = $validator->validateData($data, $rules);

if (empty($errors)) {
    // Validation passed
} else {
    // Handle errors
    // $errors = ['email' => ['email'], 'age' => ['min:18']]
}
```

### In Controllers

Validate request data in your controllers:

```php
use Lalaz\Validator\Validator;
use Lalaz\Web\Http\Request;

class UserController
{
    public function store(Request $request): array
    {
        $validator = new Validator();
        $errors = $validator->validateData($request->all(), [
            'name' => 'required|min:2',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if (!empty($errors)) {
            return json_error('Validation failed', 422, $errors);
        }

        // Create user...
        return json_success(['user' => $user], 'User created');
    }
}
```

## Available Rules

### Type Rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and not empty |
| `int` / `integer` | Must be a valid integer |
| `decimal` / `float` | Must be a valid decimal/float number |
| `boolean` / `bool` | Must be a boolean value |

```php
$rules = [
    'quantity' => 'required|int',
    'price' => 'required|decimal',
    'active' => 'boolean',
];
```

### Format Rules

| Rule | Description |
|------|-------------|
| `email` | Must be a valid email address |
| `url` | Must be a valid URL |
| `ip` | Must be a valid IP address |
| `domain` | Must be a valid domain name |
| `json` | Must be valid JSON |
| `date` | Must be a valid date (parseable by `strtotime`) |
| `date_format:format` | Must match the specified date format |

```php
$rules = [
    'email' => 'required|email',
    'website' => 'url',
    'server_ip' => 'ip',
    'hostname' => 'domain',
    'metadata' => 'json',
    'birth_date' => 'date',
    'event_date' => 'date_format:Y-m-d',
];
```

### Size Rules

| Rule | Description |
|------|-------------|
| `min:value` | Minimum value (for numbers) or length (for strings/arrays) |
| `max:value` | Maximum value (for numbers) or length (for strings/arrays) |

```php
$rules = [
    'name' => 'required|min:2|max:100',
    'age' => 'required|int|min:18|max:120',
    'bio' => 'max:500',
];
```

### Comparison Rules

| Rule | Description |
|------|-------------|
| `match:field` / `same:field` | Must match another field's value |
| `in:val1,val2,...` | Must be one of the specified values |
| `not_in:val1,val2,...` | Must NOT be one of the specified values |

```php
$rules = [
    'password' => 'required|min:8',
    'password_confirmation' => 'required|match:password',
    'status' => 'required|in:active,pending,disabled',
    'role' => 'not_in:admin,super_admin',
];
```

### Pattern Rules

| Rule | Description |
|------|-------------|
| `regex:pattern` | Must match the regex pattern |

```php
$rules = [
    'username' => 'required|regex:/^[a-z0-9_]+$/',
    'phone' => 'regex:/^\+?[0-9]{10,15}$/',
];
```

## The Rule Builder

For complex rules or better IDE support, use the fluent `Rule` builder:

```php
use Lalaz\Validator\Rule;

$rules = [
    'name' => Rule::create()->required()->min(2)->max(100),
    'email' => Rule::create()->required()->email(),
    'age' => Rule::create()->required()->int()->min(18),
    'status' => Rule::create()->required()->in('active', 'pending', 'disabled'),
];

$errors = $validator->validateData($data, $rules);
```

### Available Builder Methods

```php
Rule::create()
    // Type rules
    ->required()
    ->int() / ->integer()
    ->decimal() / ->float()
    ->bool() / ->boolean()

    // Format rules
    ->email()
    ->url()
    ->ip()
    ->domain()
    ->json()
    ->date()
    ->dateFormat('Y-m-d')

    // Size rules
    ->min(5)
    ->max(100)

    // Comparison rules
    ->match('password')   // or ->same('password')
    ->in('val1', 'val2', 'val3')
    ->notIn('val1', 'val2')

    // Pattern rules
    ->regex('/^[a-z]+$/')

    // Custom rules
    ->custom(fn($value, $data) => $value !== 'forbidden')

    // Custom messages
    ->message('Custom error message');
```

### Custom Error Messages

Add custom messages to specific rules:

```php
$rules = [
    'email' => Rule::create()
        ->required()
        ->message('Email is required')
        ->email()
        ->message('Please provide a valid email address'),

    'password' => Rule::create()
        ->required()
        ->min(8)
        ->message('Password must be at least 8 characters'),
];
```

### Custom Validation Rules

Use the `custom()` method for complex validation logic:

```php
$rules = [
    'username' => Rule::create()
        ->required()
        ->custom(function ($value, $data) {
            // Check if username is unique in database
            return !User::where('username', $value)->exists();
        })
        ->message('This username is already taken'),

    'discount_code' => Rule::create()
        ->custom(fn($value) => $this->isValidCoupon($value))
        ->message('Invalid discount code'),
];
```

### Building Rule Strings

Convert rules to string format:

```php
$rule = Rule::create()->required()->email()->min(5);

echo $rule->build(); // "required|email|min:5"
echo (string) $rule; // "required|email|min:5"

// Or get as array for more complex rules
$array = $rule->buildArray();
```

## The Validatable Trait

Add validation directly to your classes or models:

```php
use Lalaz\Validator\Concerns\Validatable;

class User
{
    use Validatable;

    public string $name;
    public string $email;
    public int $age;

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'required|int|min:18',
        ];
    }

    protected function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address',
            'age.min' => 'You must be at least 18 years old',
        ];
    }
}
```

### Using the Trait

```php
$user = new User();
$user->name = 'John';
$user->email = 'invalid-email';
$user->age = 15;

// Check if valid
if ($user->isValid()) {
    // Proceed
}

// Get errors
$errors = $user->validationErrors();
// ['email' => ['email'], 'age' => ['min:18']]

// Validate and throw exception on failure
try {
    $user->validate();
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

### Methods Available with Validatable

| Method | Description |
|--------|-------------|
| `isValid()` | Returns `true` if validation passes |
| `validate()` | Validates and throws `ValidationException` on failure |
| `validationErrors()` | Returns array of validation errors |
| `rules()` | Override to define validation rules |
| `messages()` | Override to define custom error messages |
| `validationData()` | Override to customize data being validated |

## Validation Exception

When validation fails and you need to stop execution:

```php
use Lalaz\Validator\ValidationException;

$errors = $validator->validateData($data, $rules);

if (!empty($errors)) {
    throw new ValidationException($errors, 'Validation failed');
}
```

Handling the exception:

```php
try {
    $this->validateRequest($request);
} catch (ValidationException $e) {
    return json_error($e->getMessage(), 422, $e->errors());
}
```

## Validation Patterns

### Form Request Validation

Create a reusable validation class:

```php
<?php declare(strict_types=1);

namespace App\Requests;

use Lalaz\Validator\Validator;
use Lalaz\Validator\ValidationException;
use Lalaz\Web\Http\Request;

abstract class FormRequest
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    abstract protected function rules(): array;

    public function validate(Request $request): array
    {
        $data = $request->all();
        $errors = $this->validator->validateData($data, $this->rules());

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $data;
    }
}
```

```php
<?php declare(strict_types=1);

namespace App\Requests;

class CreateUserRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|match:password',
        ];
    }
}
```

Usage:

```php
class UserController
{
    public function store(Request $request): array
    {
        $data = (new CreateUserRequest())->validate($request);

        $user = User::create($data);
        return json_success(['user' => $user], 'User created');
    }
}
```

### Conditional Validation

Validate fields based on other field values:

```php
$data = $request->all();
$rules = [
    'type' => 'required|in:individual,company',
    'name' => 'required|min:2',
];

// Add conditional rules
if ($data['type'] === 'company') {
    $rules['company_name'] = 'required|min:2';
    $rules['tax_id'] = 'required|regex:/^[0-9]{14}$/';
}

$errors = $validator->validateData($data, $rules);
```

### Array Validation

Validate arrays of data:

```php
$data = [
    'users' => [
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ],
];

// Validate each item
$errors = [];
foreach ($data['users'] as $index => $user) {
    $userErrors = $validator->validateData($user, [
        'name' => 'required|min:2',
        'email' => 'required|email',
    ]);

    if (!empty($userErrors)) {
        $errors["users.{$index}"] = $userErrors;
    }
}
```

### Update vs Create Rules

Different rules for create and update operations:

```php
class UserValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function forCreate(): array
    {
        return [
            'name' => 'required|min:2',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ];
    }

    public function forUpdate(): array
    {
        return [
            'name' => 'min:2',
            'email' => 'email',
            'password' => 'min:8',
        ];
    }

    public function validateCreate(array $data): array
    {
        return $this->validator->validateData($data, $this->forCreate());
    }

    public function validateUpdate(array $data): array
    {
        return $this->validator->validateData($data, $this->forUpdate());
    }
}
```

## Error Response Format

The validator returns errors in this format:

```php
[
    'field_name' => ['rule1', 'rule2'],
    'other_field' => ['rule1'],
]
```

For API responses, format errors appropriately:

```php
$errors = $validator->validateData($data, $rules);

if (!empty($errors)) {
    // Standard format
    return json_error('Validation failed', 422, $errors);

    // Or human-readable format
    $messages = [];
    foreach ($errors as $field => $fieldErrors) {
        $messages[$field] = $this->humanize($field, $fieldErrors);
    }
    return json_error('Validation failed', 422, $messages);
}
```

## Best Practices

### Keep Rules Readable

```php
// ✅ Good: Clear and organized
$rules = [
    'email' => Rule::create()->required()->email(),
    'password' => Rule::create()->required()->min(8),
];

// ❌ Avoid: Long string rules that are hard to read
$rules = [
    'email' => 'required|email|max:255|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
];
```

### Validate Early

Validate at the entry point of your application:

```php
public function store(Request $request): array
{
    // Validate first
    $errors = $this->validate($request);
    if (!empty($errors)) {
        return json_error('Invalid data', 422, $errors);
    }

    // Then process
    $user = $this->createUser($request->all());
    return json_success(['user' => $user]);
}
```

### Use Custom Rules for Business Logic

```php
$rules = [
    'coupon' => Rule::create()
        ->custom(fn($code) => $this->couponService->isValid($code))
        ->message('Invalid or expired coupon code'),

    'email' => Rule::create()
        ->required()
        ->email()
        ->custom(fn($email) => !$this->userExists($email))
        ->message('This email is already registered'),
];
```

### Sanitize After Validation

```php
// 1. Validate
$errors = $validator->validateData($data, $rules);
if (!empty($errors)) {
    throw new ValidationException($errors);
}

// 2. Sanitize
$data['email'] = strtolower(trim($data['email']));
$data['name'] = strip_tags($data['name']);

// 3. Process
$user = User::create($data);
```
