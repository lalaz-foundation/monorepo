# API Reference

Complete API documentation for all public classes and methods in Lalaz Validator.

---

## Validator

The main entry point for data validation.

**Namespace:** `Lalaz\Validator`

### Constructor

```php
public function __construct()
```

Creates a new Validator instance.

**Example:**

```php
$validator = new Validator();
```

---

### validateData()

```php
public function validateData(array $data, array $rules, array $messages = []): array
```

Validates the given data against the specified rules.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | `array` | The data to validate |
| `$rules` | `array` | Validation rules (string or Rule objects) |
| `$messages` | `array` | Optional custom error messages |

**Returns:** `array` - Associative array of field => error messages

**Example:**

```php
$errors = $validator->validateData(
    ['email' => 'invalid', 'name' => ''],
    ['email' => 'required|email', 'name' => 'required|min:2'],
    ['email.email' => 'Enter a valid email']
);

// Result:
// [
//     'email' => ['Enter a valid email'],
//     'name' => ['The name field is required.']
// ]
```

---

### validateDataOrFail()

```php
public function validateDataOrFail(array $data, array $rules, array $messages = []): void
```

Validates data and throws an exception if validation fails.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | `array` | The data to validate |
| `$rules` | `array` | Validation rules |
| `$messages` | `array` | Optional custom messages |

**Throws:** `ValidationException` - If validation fails

**Example:**

```php
try {
    $validator->validateDataOrFail($data, $rules);
    // Validation passed
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

---

## Rule

Fluent rule builder for constructing validation rules.

**Namespace:** `Lalaz\Validator`

### create()

```php
public static function create(): Rule
```

Creates a new Rule builder instance.

**Returns:** `Rule` - A new Rule instance

**Example:**

```php
$rule = Rule::create();
```

---

### required()

```php
public function required(): Rule
```

The field must have a non-empty value.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->required();
```

---

### email()

```php
public function email(): Rule
```

The field must be a valid email address.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->email();
```

---

### url()

```php
public function url(): Rule
```

The field must be a valid URL.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->url();
// Accepts: https://example.com, http://sub.domain.com/path
```

---

### ip()

```php
public function ip(): Rule
```

The field must be a valid IP address (IPv4 or IPv6).

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->ip();
// Accepts: 192.168.1.1, ::1, 2001:0db8:85a3::8a2e:0370:7334
```

---

### domain()

```php
public function domain(): Rule
```

The field must be a valid domain name.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->domain();
// Accepts: example.com, sub.example.com
```

---

### int()

```php
public function int(): Rule
```

The field must be an integer.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->int();
// Accepts: 1, 42, -100
```

---

### decimal()

```php
public function decimal(): Rule
```

The field must be a decimal number.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->decimal();
// Accepts: 1.5, 99.99, -3.14
```

---

### boolean()

```php
public function boolean(): Rule
```

The field must be a boolean-like value.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->boolean();
// Accepts: true, false, 1, 0, "1", "0", "true", "false"
```

---

### min()

```php
public function min(int $value): Rule
```

Sets a minimum value (for numbers) or length (for strings).

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | `int` | The minimum value or length |

**Returns:** `Rule` - For method chaining

**Example:**

```php
// For strings: minimum length
$rule = Rule::create()->min(3); // At least 3 characters

// For numbers: minimum value
$rule = Rule::create()->int()->min(18); // At least 18
```

---

### max()

```php
public function max(int $value): Rule
```

Sets a maximum value (for numbers) or length (for strings).

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$value` | `int` | The maximum value or length |

**Returns:** `Rule` - For method chaining

**Example:**

```php
// For strings: maximum length
$rule = Rule::create()->max(255); // At most 255 characters

// For numbers: maximum value
$rule = Rule::create()->int()->max(100); // At most 100
```

---

### match()

```php
public function match(string $field): Rule
```

The field must match the value of another field.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | The name of the field to match |

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rules = [
    'password' => Rule::create()->required()->min(8),
    'password_confirm' => Rule::create()->required()->match('password'),
];
```

---

### confirmed()

```php
public function confirmed(): Rule
```

The field must have a matching `{field}_confirmation` field.

**Returns:** `Rule` - For method chaining

**Example:**

```php
// Data must include password_confirmation
$rule = Rule::create()->required()->confirmed();
```

---

### in()

```php
public function in(string ...$values): Rule
```

The field must be one of the specified values.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$values` | `string` | Allowed values |

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->in('pending', 'approved', 'rejected');
```

---

### notIn()

```php
public function notIn(string ...$values): Rule
```

The field must not be one of the specified values.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$values` | `string` | Disallowed values |

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->notIn('admin', 'root', 'system');
```

---

### regex()

```php
public function regex(string $pattern): Rule
```

The field must match the given regular expression.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$pattern` | `string` | The regex pattern |

**Returns:** `Rule` - For method chaining

**Example:**

```php
// Alphanumeric only
$rule = Rule::create()->regex('/^[a-zA-Z0-9]+$/');

// Phone number format
$rule = Rule::create()->regex('/^\+?[1-9]\d{1,14}$/');
```

---

### date()

```php
public function date(): Rule
```

The field must be a valid date.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->date();
// Accepts: 2024-01-15, January 15, 2024, etc.
```

---

### dateFormat()

```php
public function dateFormat(string $format): Rule
```

The field must match the given date format.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$format` | `string` | PHP date format string |

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->dateFormat('Y-m-d');
// Accepts: 2024-01-15

$rule = Rule::create()->dateFormat('d/m/Y');
// Accepts: 15/01/2024
```

---

### json()

```php
public function json(): Rule
```

The field must be a valid JSON string.

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()->json();
// Accepts: {"key": "value"}, [1, 2, 3]
```

---

### custom()

```php
public function custom(callable $callback, string $message = ''): Rule
```

Adds a custom validation callback.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$callback` | `callable` | Function receiving (value, data) returning bool |
| `$message` | `string` | Optional error message |

**Returns:** `Rule` - For method chaining

**Example:**

```php
$rule = Rule::create()
    ->required()
    ->custom(
        fn($value, $data) => !str_contains($value, 'spam'),
        'Content cannot contain spam'
    );
```

---

### __toString()

```php
public function __toString(): string
```

Converts the rule to a string representation.

**Returns:** `string` - Pipe-delimited rule string

**Example:**

```php
$rule = Rule::create()->required()->email();
echo $rule; // "required|email"
```

---

## RuleParser

Parses rule strings into executable validation rules.

**Namespace:** `Lalaz\Validator`

### Constructor

```php
public function __construct(string|Rule $rules)
```

Creates a new RuleParser instance.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$rules` | `string\|Rule` | Rules to parse |

**Example:**

```php
$parser = new RuleParser('required|email|min:5');
$parser = new RuleParser(Rule::create()->required()->email());
```

---

### parse()

```php
public function parse(): array
```

Parses the rules into an array of rule definitions.

**Returns:** `array` - Array of parsed rules

**Example:**

```php
$parser = new RuleParser('required|min:5|in:a,b,c');
$rules = $parser->parse();

// Result:
// [
//     ['required', []],
//     ['min', ['5']],
//     ['in', ['a', 'b', 'c']]
// ]
```

---

## ValidationException

Exception thrown when validation fails.

**Namespace:** `Lalaz\Validator\Exceptions`

**Extends:** `\Exception`

### Constructor

```php
public function __construct(array $errors)
```

Creates a new ValidationException.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$errors` | `array` | Validation error messages |

---

### errors()

```php
public function errors(): array
```

Gets the validation errors.

**Returns:** `array` - Associative array of field => messages

**Example:**

```php
try {
    $validator->validateDataOrFail($data, $rules);
} catch (ValidationException $e) {
    $errors = $e->errors();
    // ['email' => ['Invalid email'], 'name' => ['Required']]
}
```

---

## Validatable Trait

Adds validation capabilities to any class.

**Namespace:** `Lalaz\Validator\Concerns`

### rules() (Abstract)

```php
protected function rules(): array
```

Define validation rules. Must be implemented by the using class.

**Returns:** `array` - Field => rules mapping

**Example:**

```php
protected function rules(): array
{
    return [
        'name' => 'required|min:2',
        'email' => Rule::create()->required()->email(),
    ];
}
```

---

### messages()

```php
protected function messages(): array
```

Override to define custom error messages.

**Returns:** `array` - Custom messages array

**Example:**

```php
protected function messages(): array
{
    return [
        'email.required' => 'Email is mandatory',
        'email.email' => 'Please enter a valid email',
    ];
}
```

---

### validate()

```php
public function validate(array $data = null): array
```

Validates the model's data.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | `array\|null` | Data to validate, or uses model properties |

**Returns:** `array` - Validation errors

**Example:**

```php
$user = new User();
$user->name = 'Jo';
$errors = $user->validate();
// ['name' => ['Name must be at least 2 characters']]
```

---

### isValid()

```php
public function isValid(): bool
```

Checks if the model passes validation.

**Returns:** `bool` - True if valid

**Example:**

```php
if ($user->isValid()) {
    $user->save();
}
```

---

### errors()

```php
public function errors(): array
```

Gets validation errors from the last validation.

**Returns:** `array` - Error messages

---

### hasErrors()

```php
public function hasErrors(): bool
```

Checks if there are validation errors.

**Returns:** `bool` - True if errors exist

---

### fill()

```php
public function fill(array $data): static
```

Fills the model with data.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | `array` | Data to fill |

**Returns:** `static` - The model instance

**Example:**

```php
$user->fill(['name' => 'John', 'email' => 'john@example.com']);
```

---

### fillAndValidate()

```php
public function fillAndValidate(array $data): static
```

Fills the model and throws exception if validation fails.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | `array` | Data to fill and validate |

**Returns:** `static` - The model instance

**Throws:** `ValidationException` - If validation fails

**Example:**

```php
try {
    $user->fillAndValidate($_POST);
} catch (ValidationException $e) {
    // Handle errors
}
```

---

## OrmValidatorAdapter

Adapter for ORM integration.

**Namespace:** `Lalaz\Validator\Adapters`

**Implements:** `ModelValidatorInterface`

### Constructor

```php
public function __construct(Validator $validator)
```

Creates a new adapter instance.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$validator` | `Validator` | The validator instance |

---

### validate()

```php
public function validate(object $model, array $rules, array $messages = []): array
```

Validates an ORM model.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$model` | `object` | The ORM model |
| `$rules` | `array` | Validation rules |
| `$messages` | `array` | Custom messages |

**Returns:** `array` - Validation errors

---

## Available Validation Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must have a non-empty value | `required` |
| `email` | Valid email address | `email` |
| `url` | Valid URL | `url` |
| `ip` | Valid IP address | `ip` |
| `domain` | Valid domain name | `domain` |
| `int` | Integer value | `int` |
| `decimal` | Decimal number | `decimal` |
| `boolean` | Boolean-like value | `boolean` |
| `min:n` | Minimum value/length | `min:3` |
| `max:n` | Maximum value/length | `max:255` |
| `match:field` | Matches another field | `match:email` |
| `confirmed` | Has matching _confirmation field | `confirmed` |
| `in:a,b,c` | Must be one of values | `in:draft,published` |
| `not_in:a,b,c` | Must not be one of values | `not_in:admin,root` |
| `regex:pattern` | Matches regex pattern | `regex:/^[A-Z]+$/` |
| `date` | Valid date string | `date` |
| `date_format:format` | Matches date format | `date_format:Y-m-d` |
| `json` | Valid JSON string | `json` |
| `custom` | Custom callback (Rule only) | Via `->custom()` |

---

## Error Messages

### Default Messages

The validator provides default messages for all rules. Customize them using the `$messages` parameter:

```php
$messages = [
    'field.rule' => 'Custom message',
    'email.required' => 'Email is required',
    'email.email' => 'Enter a valid email address',
    'password.min' => 'Password must be at least :min characters',
];
```

### Message Placeholders

| Placeholder | Replaced With |
|-------------|---------------|
| `:field` | The field name |
| `:min` | Minimum value |
| `:max` | Maximum value |
| `:values` | Allowed values (in rule) |
| `:format` | Date format |
