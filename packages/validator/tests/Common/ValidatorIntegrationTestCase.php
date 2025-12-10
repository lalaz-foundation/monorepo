<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;
use Lalaz\Validator\RuleParser;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Concerns\Validatable;

/**
 * Base test case for Validator package integration tests.
 *
 * Extends PHPUnit TestCase to provide validator-specific helpers
 * for integration testing.
 *
 * @package lalaz/validator
 */
abstract class ValidatorIntegrationTestCase extends TestCase
{
    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a new Validator instance for testing.
     */
    protected function createValidator(): Validator
    {
        return new Validator();
    }

    /**
     * Create a new Rule builder instance for testing.
     */
    protected function createRule(): Rule
    {
        return Rule::create();
    }

    /**
     * Create a validatable test model.
     *
     * @param array<string, string|Rule> $rules
     * @param array<string, string> $messages
     */
    protected function createValidatableModel(array $rules = [], array $messages = []): object
    {
        return new class($rules, $messages) {
            use Validatable;

            public string $name = '';
            public string $email = '';
            public int $age = 0;
            public string $password = '';

            private array $customRules;
            private array $customMessages;

            public function __construct(array $rules, array $messages)
            {
                $this->customRules = $rules;
                $this->customMessages = $messages;
            }

            protected function rules(): array
            {
                return count($this->customRules) > 0
                    ? $this->customRules
                    : [
                        'name' => 'required|min:2|max:100',
                        'email' => 'required|email',
                        'age' => 'required|int|min:18',
                    ];
            }

            protected function messages(): array
            {
                return $this->customMessages;
            }
        };
    }

    /**
     * Create sample valid user data for testing.
     *
     * @return array<string, mixed>
     */
    protected function createValidUserData(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    /**
     * Create sample invalid user data for testing.
     *
     * @return array<string, mixed>
     */
    protected function createInvalidUserData(): array
    {
        return [
            'name' => '',
            'email' => 'invalid-email',
            'age' => 10,
            'password' => 'abc',
            'password_confirmation' => 'xyz',
        ];
    }

    /**
     * Create common user validation rules.
     *
     * @return array<string, string|Rule>
     */
    protected function createUserValidationRules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'required|int|min:18',
            'password' => 'required|min:6|confirmed',
        ];
    }

    // =========================================================================
    // Validation Assertions
    // =========================================================================

    /**
     * Assert that validation passes for the given data and rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array|Rule> $rules
     */
    protected function assertValidationPasses(array $data, array $rules, string $message = ''): void
    {
        $validator = $this->createValidator();
        $errors = $validator->validateData($data, $rules);

        $this->assertEmpty($errors, $message ?: 'Validation should pass but found errors: ' . json_encode($errors));
    }

    /**
     * Assert that validation fails for the given data and rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array|Rule> $rules
     */
    protected function assertValidationFails(array $data, array $rules, string $message = ''): void
    {
        $validator = $this->createValidator();
        $errors = $validator->validateData($data, $rules);

        $this->assertNotEmpty($errors, $message ?: 'Validation should fail but passed');
    }

    /**
     * Assert that a specific field has validation error.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array|Rule> $rules
     */
    protected function assertFieldHasError(string $field, array $data, array $rules, string $message = ''): void
    {
        $validator = $this->createValidator();
        $errors = $validator->validateData($data, $rules);

        $this->assertArrayHasKey($field, $errors, $message ?: "Field '{$field}' should have validation error");
    }

    /**
     * Assert that a specific field has no validation error.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array|Rule> $rules
     */
    protected function assertFieldHasNoError(string $field, array $data, array $rules, string $message = ''): void
    {
        $validator = $this->createValidator();
        $errors = $validator->validateData($data, $rules);

        $this->assertArrayNotHasKey($field, $errors, $message ?: "Field '{$field}' should not have validation error");
    }

    /**
     * Assert that a field has a specific error message.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array|Rule> $rules
     */
    protected function assertFieldHasErrorMessage(
        string $field,
        string $expectedMessage,
        array $data,
        array $rules,
        string $message = ''
    ): void {
        $validator = $this->createValidator();
        $errors = $validator->validateData($data, $rules);

        $this->assertArrayHasKey($field, $errors, "Field '{$field}' should have errors");
        $this->assertContains(
            $expectedMessage,
            $errors[$field],
            $message ?: "Field '{$field}' should contain error message '{$expectedMessage}'"
        );
    }

    /**
     * Assert that a validatable model is valid.
     */
    protected function assertModelIsValid(object $model, string $message = ''): void
    {
        $this->assertTrue($model->isValid(), $message ?: 'Model should be valid');
    }

    /**
     * Assert that a validatable model is invalid.
     */
    protected function assertModelIsInvalid(object $model, string $message = ''): void
    {
        $this->assertFalse($model->isValid(), $message ?: 'Model should be invalid');
    }

    /**
     * Assert that a validatable model has specific error.
     */
    protected function assertModelHasError(object $model, string $field, string $message = ''): void
    {
        $model->isValid();
        $this->assertTrue($model->hasError($field), $message ?: "Model should have error for '{$field}'");
    }

    /**
     * Assert error count on a validatable model.
     */
    protected function assertModelErrorCount(object $model, int $expectedCount, string $message = ''): void
    {
        $model->isValid();
        $errors = $model->errorMessages();
        $this->assertCount($expectedCount, $errors, $message ?: "Model should have {$expectedCount} errors");
    }

    // =========================================================================
    // Rule Builder Assertions
    // =========================================================================

    /**
     * Assert that a Rule builder produces expected string output.
     */
    protected function assertRuleStringContains(Rule $rule, string $expected, string $message = ''): void
    {
        $string = (string) $rule;
        $this->assertStringContainsString(
            $expected,
            $string,
            $message ?: "Rule string should contain '{$expected}'"
        );
    }

    /**
     * Assert that a Rule builder produces expected array output.
     *
     * @param array<int, string|array<string, mixed>> $expected
     */
    protected function assertRuleArrayContains(Rule $rule, array $expected, string $message = ''): void
    {
        $array = $rule->buildArray();
        foreach ($expected as $item) {
            $this->assertContains(
                $item,
                $array,
                $message ?: "Rule array should contain expected item"
            );
        }
    }

    // =========================================================================
    // Exception Assertions
    // =========================================================================

    /**
     * Assert that ValidationException is thrown with expected errors.
     *
     * @param callable $callback
     * @param array<int, string> $expectedFields Fields expected to have errors
     */
    protected function assertValidationExceptionWithFields(callable $callback, array $expectedFields): void
    {
        try {
            $callback();
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $errors, "Exception should contain error for '{$field}'");
            }
        }
    }

    /**
     * Assert that ValidationException contains specific error count.
     */
    protected function assertValidationExceptionErrorCount(callable $callback, int $expectedCount): void
    {
        try {
            $callback();
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $totalErrors = 0;
            foreach ($e->errors() as $fieldErrors) {
                $totalErrors += count($fieldErrors);
            }
            $this->assertEquals($expectedCount, $totalErrors, "Exception should contain {$expectedCount} errors");
        }
    }
}
