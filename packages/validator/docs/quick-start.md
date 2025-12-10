# Quick Start Guide

Get data validation working in your Lalaz application in under 5 minutes.

---

## Prerequisites

Before you begin, make sure you have:

- A Lalaz Framework project (v1.0+)
- PHP 8.2 or higher

---

## Step 1: Install the Package

```bash
php lalaz package:add lalaz/validator
```

This will:
- Download the validator package
- Register the service provider
- Make the Validator available for use

---

## Step 2: Basic Validation

The simplest way to validate data:

```php
<?php

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
    echo "All data is valid!";
} else {
    foreach ($errors as $field => $messages) {
        echo "$field: " . implode(', ', $messages) . "\n";
    }
}
```

---

## Step 3: Use the Fluent Rule Builder

For more complex rules, use the Rule builder:

```php
<?php

use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

$validator = new Validator();

$errors = $validator->validateData($data, [
    'email' => Rule::create()->required()->email(),
    'password' => Rule::create()->required()->min(8)->confirmed(),
    'role' => Rule::create()->required()->in('admin', 'editor', 'viewer'),
    'website' => Rule::create()->url(), // optional field
]);
```

---

## Step 4: Add Validation to Models

Use the `Validatable` trait to add validation to any class:

```php
<?php

use Lalaz\Validator\Concerns\Validatable;

class User
{
    use Validatable;

    public string $name = '';
    public string $email = '';
    public int $age = 0;

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'required|int|min:18',
        ];
    }
}
```

Then use it:

```php
$user = new User();
$user->fill([
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => 25,
]);

if ($user->isValid()) {
    // Save user
} else {
    // Handle errors
    $errors = $user->errors();
}
```

---

## Step 5: Validate in Controllers

Here's how to use validation in a typical controller:

```php
<?php

namespace App\Controllers;

use Lalaz\Http\Request;
use Lalaz\Http\Response;
use Lalaz\Validator\Validator;
use Lalaz\Validator\ValidationException;

class UserController
{
    public function store(Request $request, Response $response): Response
    {
        $validator = new Validator();
        
        $errors = $validator->validateData($request->all(), [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        
        if (!empty($errors)) {
            return $response->json(['errors' => $errors], 422);
        }
        
        // Create user...
        
        return $response->json(['message' => 'User created'], 201);
    }
}
```

---

## You're Done! 🎉

Your application now has:

- ✅ Data validation
- ✅ String and fluent rules
- ✅ Model validation with trait
- ✅ Controller integration

---

## What's Next?

<table>
<tr>
<td width="50%">

### Add More Features

- [Custom Validation Rules](#custom-rules)
- [Partial Validation](#partial-validation)
- [ORM Integration](#orm-integration)

</td>
<td width="50%">

### Learn More

- [Core Concepts](./concepts.md) — Understand Validator architecture
- [API Reference](./api-reference.md) — Complete method documentation
- [Glossary](./glossary.md) — Validation terminology

</td>
</tr>
</table>

---

## Common Issues

### "Required field error for empty string"

The `required` rule fails for both `null` and empty strings `''`.

```php
// This will fail required
$data = ['name' => ''];

// This will pass required
$data = ['name' => 'John'];
```

### "Validation not working for optional fields"

Optional fields only validate when a value is provided:

```php
$rules = [
    'website' => 'url', // Only validates if not empty
];

// These both pass:
$data = ['website' => ''];                    // Empty, skipped
$data = ['website' => 'https://example.com']; // Valid URL
```

### "Confirmed rule not finding confirmation field"

The `confirmed` rule looks for `{field}_confirmation`:

```php
$data = [
    'password' => 'secret123',
    'password_confirmation' => 'secret123', // Must match this exact name
];

$rules = [
    'password' => 'confirmed',
];
```

### "Custom callback not receiving all data"

Custom callbacks receive two arguments:

```php
Rule::create()->custom(function ($value, $allData) {
    // $value - the field value
    // $allData - all submitted data
    return $value !== $allData['forbidden_value'];
});
```

---

## Complete Example

Here's a complete registration form validation:

```php
<?php

use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

$validator = new Validator();

$data = [
    'username' => 'johndoe',
    'email' => 'john@example.com',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
    'age' => 25,
    'terms' => true,
];

$rules = [
    'username' => Rule::create()
        ->required()
        ->min(3)
        ->max(20)
        ->regex('/^[a-z0-9_]+$/')
        ->message('Username can only contain lowercase letters, numbers, and underscores'),
        
    'email' => 'required|email',
    
    'password' => Rule::create()
        ->required()
        ->min(8)
        ->confirmed()
        ->custom(fn($v) => preg_match('/[A-Z]/', $v) && preg_match('/[0-9]/', $v))
        ->message('Password must contain uppercase letter and number'),
        
    'age' => 'required|int|min:18|max:120',
    
    'terms' => Rule::create()
        ->required()
        ->boolean()
        ->custom(fn($v) => $v === true)
        ->message('You must accept the terms'),
];

$errors = $validator->validateData($data, $rules);

if (empty($errors)) {
    // Registration successful!
} else {
    // Show errors to user
}
```

---

<p align="center">
  <a href="./installation.md">← Installation</a> •
  <a href="./concepts.md">Core Concepts →</a>
</p>
