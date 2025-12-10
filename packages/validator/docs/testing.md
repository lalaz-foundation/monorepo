# Testing Guide

This guide covers how to run the Lalaz Validator test suite and write your own tests.

## Running Tests

### Run All Tests

```bash
cd packages/validator
./vendor/bin/phpunit
```

### Run Unit Tests Only

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Run Integration Tests Only

```bash
./vendor/bin/phpunit --testsuite Integration
```

### Run Specific Test File

```bash
./vendor/bin/phpunit tests/Unit/ValidatorTest.php
```

### Run Specific Test Method

```bash
./vendor/bin/phpunit --filter test_passes_when_data_satisfies_rules
```

## Test Structure

The test suite is organized as follows:

```
tests/
├── bootstrap.php                    # Autoloader setup
├── Common/
│   ├── ValidatorUnitTestCase.php    # Base class for unit tests
│   └── ValidatorIntegrationTestCase.php  # Base class for integration tests
├── Unit/
│   ├── ValidatorTest.php            # Validator class tests
│   └── RuleTest.php                 # Rule builder tests
└── Integration/
    ├── ValidatorFlowIntegrationTest.php      # End-to-end validation flows
    ├── RuleBuilderIntegrationTest.php        # Rule builder integration
    └── ValidatableTraitIntegrationTest.php   # Validatable trait integration
```

## Writing Tests

### Extending Base Test Cases

For unit tests, extend `ValidatorUnitTestCase`:

```php
<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Unit;

use Lalaz\Validator\Tests\Common\ValidatorUnitTestCase;

class MyValidatorTest extends ValidatorUnitTestCase
{
    public function test_my_validation(): void
    {
        $validator = $this->createValidator();
        
        $errors = $validator->validateData(
            ['email' => 'test@example.com'],
            ['email' => 'required|email']
        );
        
        $this->assertEmpty($errors);
    }
}
```

For integration tests, extend `ValidatorIntegrationTestCase`:

```php
<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Integration;

use Lalaz\Validator\Tests\Common\ValidatorIntegrationTestCase;

class MyIntegrationTest extends ValidatorIntegrationTestCase
{
    public function test_complete_validation_flow(): void
    {
        $data = $this->createValidUserData();
        $rules = $this->createUserValidationRules();
        
        $this->assertValidationPasses($data, $rules);
    }
}
```

### Available Factory Methods

Both base test cases provide factory methods:

```php
// Create instances
$validator = $this->createValidator();
$rule = $this->createRule();

// Create test data
$validData = $this->createValidUserData();
$invalidData = $this->createInvalidUserData();
$rules = $this->createUserValidationRules();

// Create specific rules
$emailRule = $this->createEmailRule(required: true);
$lengthRule = $this->createLengthRule(min: 2, max: 100);
$intRule = $this->createIntegerRangeRule(min: 18, max: 65);
```

### Available Assertions

The base test cases provide validation-specific assertions:

```php
// Validation assertions
$this->assertValidationPasses($data, $rules);
$this->assertValidationFails($data, $rules);
$this->assertFieldHasError('email', $data, $rules);
$this->assertFieldHasNoError('email', $data, $rules);
$this->assertFieldHasErrorMessage('email', 'Invalid email', $data, $rules);

// Rule builder assertions
$this->assertRuleStringContains($rule, 'required');
$this->assertRuleArrayContains($rule, ['required']);

// Parser assertions
$this->assertRuleParsed('required|email', expectedCount: 2);
```

### Testing Validatable Models

```php
public function test_model_validation(): void
{
    $model = $this->createValidatableModel([
        'name' => 'required|min:2',
        'email' => 'required|email',
    ]);
    
    $model->name = 'John';
    $model->email = 'john@example.com';
    
    $this->assertModelIsValid($model);
}

public function test_model_validation_fails(): void
{
    $model = $this->createValidatableModel([
        'email' => 'required|email',
    ]);
    
    $model->email = 'invalid';
    
    $this->assertModelIsInvalid($model);
    $this->assertModelHasError($model, 'email');
}
```

### Testing Custom Validation Rules

```php
public function test_custom_callback_validation(): void
{
    $rule = Rule::create()
        ->required()
        ->custom(fn($value) => $value !== 'forbidden', 'Value is forbidden');
    
    $validator = $this->createValidator();
    
    // Should pass
    $errors = $validator->validateData(
        ['field' => 'allowed'],
        ['field' => $rule]
    );
    $this->assertEmpty($errors);
    
    // Should fail
    $errors = $validator->validateData(
        ['field' => 'forbidden'],
        ['field' => $rule]
    );
    $this->assertArrayHasKey('field', $errors);
}
```

### Testing ValidationException

```php
public function test_throws_validation_exception(): void
{
    $validator = $this->createValidator();
    
    $this->expectException(ValidationException::class);
    
    $validator->validateModel(
        new stdClass(),
        ['email' => 'invalid'],
        ['email' => 'required|email'],
        'create'
    );
}

public function test_exception_contains_errors(): void
{
    $validator = $this->createValidator();
    
    try {
        $validator->validateModel(
            new stdClass(),
            ['email' => 'bad', 'name' => ''],
            ['email' => 'email', 'name' => 'required'],
            'create'
        );
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
}
```

## Test Coverage

To generate a code coverage report:

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Continuous Integration

The test suite is run automatically on every push via GitHub Actions. See `.github/workflows/tests.yml` for the CI configuration.

## Best Practices

1. **Test one thing per test** - Each test method should verify a single behavior
2. **Use descriptive names** - Test names should describe what they test
3. **Use factory methods** - Leverage the base class helpers for consistency
4. **Test edge cases** - Empty values, null, special characters, etc.
5. **Test both success and failure** - Verify validation passes and fails correctly
