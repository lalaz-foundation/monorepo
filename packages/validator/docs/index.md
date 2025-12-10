# Lalaz Validator Documentation

Welcome to the Lalaz Validator documentation. This guide will help you understand and implement data validation in your Lalaz applications.

## What is Lalaz Validator?

Lalaz Validator is a comprehensive data validation package that provides:

- **Data Validation**: Validating user input, API requests, and form data
- **Rule Building**: Fluent API for creating complex validation rules
- **Model Integration**: Add validation to any class with the Validatable trait

## Table of Contents

### Getting Started
- [Quick Start](./quick-start.md) - Get validation working in 5 minutes ⚡
- [Installation](./installation.md) - How to install and configure the package
- [Core Concepts](./concepts.md) - Understanding Validator, Rules, and Parser
- [Glossary](./glossary.md) - Validation terminology explained

### Validation Rules
- [Built-in Rules](#built-in-rules) - All available validation rules
- [String Rules](#string-rules) - Using string-based rule definitions
- [Rule Builder](#rule-builder) - Fluent API for building rules
- [Custom Rules](#custom-rules) - Creating custom validation callbacks

### Model Validation
- [Validatable Trait](#validatable-trait) - Adding validation to models
- [Fill and Validate](#fill-and-validate) - Data filling with validation
- [Partial Validation](#partial-validation) - Validating specific fields

### Testing
- [Testing Guide](./testing.md) - How to run and write tests

### Reference
- [API Reference](./api-reference.md) - Complete class and method reference

## Quick Example

Here's a simple example to get you started:

```php
<?php

use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

$validator = new Validator();

// Validate form data
$errors = $validator->validateData([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
], [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'age' => Rule::create()->required()->int()->min(18),
]);

if (empty($errors)) {
    // All data is valid!
    echo "Validation passed!";
} else {
    // Handle validation errors
    foreach ($errors as $field => $messages) {
        echo "$field: " . implode(', ', $messages) . "\n";
    }
}
```

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Your Application                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│                       Validator                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  validateData(array $data, array $rules): array     │    │
│  │  validate(object $model, array $data, ...): void    │    │
│  └───────────────────────┬─────────────────────────────┘    │
│                          │                                   │
│         ┌────────────────┼────────────────┐                 │
│         ▼                ▼                ▼                 │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐          │
│  │   String   │   │    Rule    │   │   Custom   │          │
│  │   Rules    │   │   Builder  │   │  Callbacks │          │
│  └─────┬──────┘   └─────┬──────┘   └─────┬──────┘          │
│        │                │                │                  │
│        └────────────────┼────────────────┘                  │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │    RuleParser       │                        │
│              │  (Normalizes rules) │                        │
│              └──────────┬──────────┘                        │
│                         │                                    │
│                         ▼                                    │
│              ┌─────────────────────┐                        │
│              │  Validation Logic   │                        │
│              │  (20+ built-in)     │                        │
│              └──────────┬──────────┘                        │
│                         │                                    │
│              ┌──────────┴──────────┐                        │
│              ▼                     ▼                        │
│        ┌─────────┐          ┌───────────┐                  │
│        │ Passes  │          │  Errors   │                  │
│        │   []    │          │ ['field'  │                  │
│        └─────────┘          │  => [...]]│                  │
│                             └───────────┘                  │
└─────────────────────────────────────────────────────────────┘
```

## Key Concepts at a Glance

| Concept | Description | Example |
|---------|-------------|---------|
| **Validator** | Main class that validates data | `$validator->validateData($data, $rules)` |
| **Rule** | Fluent builder for validation rules | `Rule::create()->required()->email()` |
| **RuleParser** | Converts string rules to normalized format | `"required\|email"` → array |
| **Validatable** | Trait for adding validation to models | `class User { use Validatable; }` |
| **ValidationException** | Thrown when validation fails | Contains `errors()` method |

## Built-in Rules

| Rule | Description |
|------|-------------|
| `required` | Field must not be empty |
| `email` | Valid email format |
| `url` | Valid URL format |
| `ip` | Valid IP address |
| `domain` | Valid domain name |
| `int` | Integer value |
| `decimal` | Decimal/float value |
| `boolean` | Boolean value |
| `min:n` | Minimum value/length |
| `max:n` | Maximum value/length |
| `match:field` | Must match another field |
| `confirmed` | Must have `{field}_confirmation` match |
| `in:a,b,c` | Must be in list |
| `not_in:a,b,c` | Must not be in list |
| `regex:/pattern/` | Must match regex |
| `date` | Valid date |
| `date_format:fmt` | Specific date format |
| `json` | Valid JSON string |

## Next Steps

1. **New to Lalaz Validator?** Start with the [Quick Start](./quick-start.md) guide
2. Already familiar? Jump to [Installation](./installation.md) for setup details
3. Read [Core Concepts](./concepts.md) to understand the architecture
4. Explore [API Reference](./api-reference.md) for complete documentation
5. Check the [Testing Guide](./testing.md) for running and writing tests
6. Use the [Glossary](./glossary.md) as a reference for terminology
