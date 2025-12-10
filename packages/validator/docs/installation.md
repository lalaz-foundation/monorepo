# Installation & Configuration

This guide walks you through installing and configuring Lalaz Validator in your application.

## Requirements

Before installing, make sure you have:

- **PHP 8.2** or higher
- **Lalaz Framework 1.0** or higher
- **Composer** for package management

## Installation

### Step 1: Install the Package

Run the following command in your project root:

```bash
php lalaz package:add lalaz/validator
```

Or install via Composer directly:

```bash
composer require lalaz/validator
```

### Step 2: Verify Installation

Test the installation by creating a simple validation:

```php
<?php

use Lalaz\Validator\Validator;

$validator = new Validator();

$errors = $validator->validateData(
    ['email' => 'test@example.com'],
    ['email' => 'required|email']
);

echo empty($errors) ? "Validator is working!" : "Validation failed";
```

## Basic Usage

### Direct Validation

The simplest approach - create a Validator and validate data:

```php
<?php

use Lalaz\Validator\Validator;

$validator = new Validator();

$data = $_POST; // or $request->all()

$errors = $validator->validateData($data, [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => 'int|min:18',
]);

if (!empty($errors)) {
    // Handle errors
    foreach ($errors as $field => $messages) {
        foreach ($messages as $message) {
            echo "{$field}: {$message}<br>";
        }
    }
}
```

### Using the Rule Builder

For more complex rules or better IDE support:

```php
<?php

use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

$validator = new Validator();

$errors = $validator->validateData($data, [
    'email' => Rule::create()->required()->email(),
    'password' => Rule::create()->required()->min(8)->confirmed(),
    'role' => Rule::create()->required()->in('admin', 'editor', 'viewer'),
]);
```

### Adding Validation to Models

Use the `Validatable` trait to add validation capabilities to any class:

```php
<?php

use Lalaz\Validator\Concerns\Validatable;
use Lalaz\Validator\Rule;

class User
{
    use Validatable;

    public string $name = '';
    public string $email = '';
    public int $age = 0;
    public string $password = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'int|min:18',
            'password' => Rule::create()->required()->min(8),
        ];
    }

    protected function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address',
            'password.min' => 'Password must be at least 8 characters',
        ];
    }
}
```

Usage:

```php
$user = new User();
$user->fill($data);

if ($user->isValid()) {
    // Save user
} else {
    $errors = $user->errors();
}

// Or throw exception on failure
$user->fillAndValidate($data);
```

## ORM Integration

If you're using Lalaz ORM, the validator integrates seamlessly via the adapter:

```php
<?php

use Lalaz\Validator\Adapters\OrmValidatorAdapter;
use Lalaz\Validator\Validator;

// Create the adapter
$adapter = new OrmValidatorAdapter(new Validator());

// The adapter implements ModelValidatorInterface
// It's automatically used by ORM models with validation rules
```

### Model with ORM Validation

```php
<?php

use Lalaz\Orm\Model;
use Lalaz\Validator\Concerns\Validatable;

class Product extends Model
{
    use Validatable;

    protected static string $tableName = 'products';

    protected static array $fillable = ['name', 'price', 'quantity'];

    protected function rules(): array
    {
        return [
            'name' => 'required|min:3|max:200',
            'price' => 'required|decimal|min:0.01',
            'quantity' => 'required|int|min:0',
        ];
    }
}
```

## Troubleshooting

### Common Issues

**1. Class not found errors**

Make sure Composer autoload is up to date:

```bash
composer dump-autoload
```

**2. Validation not triggering**

Ensure you're calling `isValid()` or `validate()`:

```php
// Wrong - just fills data
$user->fill($data);

// Right - fills and checks validity
$user->fill($data);
if ($user->isValid()) { ... }

// Or use fillAndValidate
$user->fillAndValidate($data);
```

**3. Required rule passing for empty string**

Empty strings `''` fail the `required` rule. If you're seeing unexpected behavior, check your data:

```php
$data = ['name' => '']; // This fails required
$data = ['name' => ' ']; // This also fails required (trimmed)
```

**4. Optional fields validating when empty**

Fields without `required` only validate when a value is provided:

```php
$rules = ['website' => 'url'];

// These both pass:
$data = ['website' => ''];                    // Empty = skipped
$data = ['website' => 'https://example.com']; // Valid URL
```

**5. Confirmed rule not finding confirmation field**

The `confirmed` rule expects `{field}_confirmation`:

```php
$data = [
    'password' => 'secret123',
    'password_confirmation' => 'secret123',  // Must be this exact name
];

$rules = [
    'password' => 'confirmed',
];
```

## Next Steps

Now that you have Lalaz Validator installed:

1. Read [Core Concepts](./concepts.md) to understand the architecture
2. Explore the [Quick Start](./quick-start.md) guide for practical examples
3. Check the [API Reference](./api-reference.md) for complete documentation
4. Use the [Glossary](./glossary.md) as a reference for terminology
