<p align="center">
  <img src="https://raw.githubusercontent.com/lalaz-foundation/art/main/packages/validator-logo.svg" width="120" alt="Lalaz Validator">
</p>

<h1 align="center">Lalaz Validator</h1>

<p align="center">
  <strong>Data validation made simple. Rules made powerful.</strong>
</p>

<p align="center">
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%5E8.3-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License"></a>
  <a href="https://packagist.org/packages/lalaz/validator"><img src="https://img.shields.io/badge/version-1.0.0-blue?style=flat-square" alt="Version"></a>
  <a href="https://github.com/lalaz-foundation/framework/actions"><img src="https://img.shields.io/badge/tests-passing-brightgreen?style=flat-square" alt="Tests"></a>
</p>

<p align="center">
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-features">Features</a> •
  <a href="#-documentation">Documentation</a> •
  <a href="#-examples">Examples</a> •
  <a href="#-contributing">Contributing</a>
</p>

---

## What is Lalaz Validator?

Lalaz Validator is a **zero-dependency** data validation package for the Lalaz Framework. It provides everything you need to validate user input, API requests, and model data—with a fluent API that makes complex validation rules readable.

```php
// That's it. Validate your data with clean, readable rules.
$errors = $validator->validateData($data, [
    'email' => 'required|email',
    'age' => Rule::create()->required()->int()->min(18),
]);
```

---

## ⚡ Quick Start

### Installation

```bash
php lalaz package:add lalaz/validator
```

### 1. Validate Data (30 seconds)

```php
use Lalaz\Validator\Validator;

$validator = new Validator();

$errors = $validator->validateData([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
], [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => 'required|int|min:18',
]);

if (empty($errors)) {
    // Validation passed!
}
```

### 2. Use the Fluent Rule Builder

```php
use Lalaz\Validator\Rule;

$rules = [
    'email' => Rule::create()->required()->email(),
    'password' => Rule::create()->required()->min(8)->confirmed(),
    'role' => Rule::create()->required()->in('admin', 'editor', 'viewer'),
];
```

### 3. Add Validation to Models

```php
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
            'name' => 'required|min:2',
            'email' => 'required|email',
            'age' => 'int|min:18',
        ];
    }
}

$user = new User();
$user->fill(['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);

if ($user->isValid()) {
    // User data is valid!
}
```

**That's the basics!** For advanced rules, custom validators, and more, keep reading.

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 📝 String & Builder Rules
Define rules as simple strings or use the fluent Rule builder. Mix and match in the same validation.

### 🔗 Chainable API
Build complex rules with method chaining. `Rule::create()->required()->email()->min(5)`

### 🎯 20+ Built-in Rules
Required, email, URL, IP, min, max, regex, date, JSON, and many more validation rules.

</td>
<td width="50%">

### 🔄 Custom Callbacks
Add custom validation logic with closures that receive the value and all data.

### 📦 Validatable Trait
Add validation capabilities to any class or model with a single trait.

### ⚡ Zero Dependencies
Pure PHP implementation with no external dependencies.

</td>
</tr>
</table>

---

## 📖 Examples

### Form Validation

```php
use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

$validator = new Validator();

$data = $request->all();

$errors = $validator->validateData($data, [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'password' => 'required|min:8|confirmed',
    'age' => 'required|int|min:18|max:120',
    'website' => 'url', // optional, only validates if provided
    'bio' => 'max:500',
]);

if (!empty($errors)) {
    return response()->json(['errors' => $errors], 422);
}
```

### API Request Validation

```php
$errors = $validator->validateData($request->json(), [
    'title' => Rule::create()->required()->min(5)->max(200),
    'content' => Rule::create()->required()->min(10),
    'status' => Rule::create()->required()->in('draft', 'published', 'archived'),
    'category_id' => Rule::create()->required()->int(),
    'tags' => Rule::create()->json(),
    'published_at' => Rule::create()->date(),
]);
```

### Custom Validation Rules

```php
$rules = [
    'username' => Rule::create()
        ->required()
        ->min(3)
        ->max(20)
        ->regex('/^[a-z0-9_]+$/')
        ->custom(function ($value, $data) {
            // Check if username is unique (example)
            return !User::where('username', $value)->exists();
        })
        ->message('Username is already taken'),
        
    'promo_code' => Rule::create()
        ->custom(fn($value) => PromoCode::isValid($value))
        ->message('Invalid promo code'),
];
```

### Model Validation with Trait

```php
use Lalaz\Validator\Concerns\Validatable;
use Lalaz\Validator\Rule;

class Product
{
    use Validatable;

    public string $name = '';
    public float $price = 0;
    public int $quantity = 0;
    public string $sku = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|min:3|max:200',
            'price' => Rule::create()->required()->decimal()->min(0.01),
            'quantity' => 'required|int|min:0',
            'sku' => Rule::create()
                ->required()
                ->regex('/^[A-Z]{3}-\d{4}$/')
                ->message('SKU must be in format XXX-0000'),
        ];
    }
}

// Usage
$product = new Product();
$product->fill($request->all());

if (!$product->isValid()) {
    $errors = $product->errors();
    // Handle validation errors
}

// Or throw exception on validation failure
$product->fillAndValidate($request->all());
```

### Partial Validation

```php
$user = new User();
$user->name = 'John';
$user->email = 'invalid'; // Would normally fail

// Only validate the name field
if ($user->isValidOnly(['name'])) {
    // Name is valid, email not checked
}

// Validate all except email
if ($user->isValidExcept(['email'])) {
    // All fields except email are valid
}
```

---

## 🏗️ Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                        Your Application                          │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Validator         Main validation engine                        │
│                    validateData() / validate()                   │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│  Rule Builder      Fluent API for building rules                 │
│                    required() / email() / min() / custom()       │
├──────────────────────────────────────────────────────────────────┤
│  RuleParser        Converts string rules to normalized format    │
│                    "required|email" → [['name' => 'required']...]│
├──────────────────────────────────────────────────────────────────┤
│  Validatable       Trait for adding validation to any class      │
│                    isValid() / validate() / fill()               │
├──────────────────────────────────────────────────────────────────┤
│  ValidationException  Thrown when validation fails               │
│                       errors() returns all field errors          │
└──────────────────────────────────────────────────────────────────┘
```

**Key Concepts:**
- **Validator** — Validates data against rules, returns errors array
- **Rule** — Fluent builder for creating validation rules
- **RuleParser** — Parses string rules into normalized format
- **Validatable** — Trait that adds validation to any class/model

---

## 📋 Available Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must be present and not empty | `'name' => 'required'` |
| `email` | Must be a valid email address | `'email' => 'email'` |
| `url` | Must be a valid URL | `'website' => 'url'` |
| `ip` | Must be a valid IP address | `'server' => 'ip'` |
| `domain` | Must be a valid domain name | `'site' => 'domain'` |
| `int` / `integer` | Must be an integer | `'age' => 'int'` |
| `decimal` / `float` | Must be a decimal number | `'price' => 'decimal'` |
| `boolean` / `bool` | Must be a boolean | `'active' => 'boolean'` |
| `min:n` | Minimum value/length | `'age' => 'min:18'` |
| `max:n` | Maximum value/length | `'bio' => 'max:500'` |
| `match:field` | Must match another field | `'confirm' => 'match:password'` |
| `confirmed` | Field must have matching `{field}_confirmation` | `'password' => 'confirmed'` |
| `in:a,b,c` | Must be one of the values | `'role' => 'in:admin,user'` |
| `not_in:a,b,c` | Must not be one of the values | `'name' => 'not_in:admin,root'` |
| `regex:/pattern/` | Must match regex pattern | `'code' => 'regex:/^[A-Z]+$/'` |
| `date` | Must be a valid date | `'dob' => 'date'` |
| `date_format:format` | Must match date format | `'date' => 'date_format:Y-m-d'` |
| `json` | Must be valid JSON | `'config' => 'json'` |
| `custom` | Custom callback validation | See examples below |

---

## 📚 Documentation

| Topic | Description |
|-------|-------------|
| [Installation](./docs/installation.md) | Setup and configuration |
| [Quick Start](./docs/quick-start.md) | Get started in 5 minutes |
| [Core Concepts](./docs/concepts.md) | Validator, Rules, and Parser explained |
| [Glossary](./docs/glossary.md) | Validation terminology |
| [API Reference](./docs/api-reference.md) | Complete class documentation |

---

## 🔧 Configuration

The validator works out of the box with zero configuration. For advanced use cases, you can customize behavior through the service provider:

```php
// config/validator.php
return [
    // Future configuration options
];
```

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.3 |
| lalaz/framework | ^1.0 |

**Optional:**
- `lalaz/orm` — For ORM model validation adapter

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

Please read our [Contributing Guide](../../CONTRIBUTING.md) for details on our code of conduct and development process.

### Running Tests

```bash
composer test
```

---

## 🔒 Security

If you discover a security vulnerability, please **do not** open a public issue. Instead, email us at:

📧 **security@lalaz.dev**

We take security seriously and will respond promptly to verified vulnerabilities.

---

## 📄 License

Lalaz Validator is open-source software licensed under the [MIT License](LICENSE).

---

<p align="center">
  <sub>Built with ❤️ by the <a href="https://github.com/lalaz-foundation">Lalaz Foundation</a></sub>
</p>
