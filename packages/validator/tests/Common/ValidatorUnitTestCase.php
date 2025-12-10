<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Common;

use PHPUnit\Framework\TestCase;
use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;
use Lalaz\Validator\RuleParser;
use Lalaz\Validator\ValidationException;

/**
 * Base test case for Validator package unit tests.
 *
 * Extends PHPUnit TestCase to provide validator-specific helpers
 * like rule assertions and factory methods.
 *
 * @package lalaz/validator
 */
abstract class ValidatorUnitTestCase extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getSetUpMethods() as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->getTearDownMethods()) as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        parent::tearDown();
    }

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpValidator',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownValidator',
        ];
    }

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
     * Create a rule with common required validation.
     */
    protected function createRequiredRule(): Rule
    {
        return Rule::create()->required();
    }

    /**
     * Create a rule for email validation.
     */
    protected function createEmailRule(bool $required = true): Rule
    {
        $rule = Rule::create();
        if ($required) {
            $rule->required();
        }
        return $rule->email();
    }

    /**
     * Create a rule for string length validation.
     */
    protected function createLengthRule(int $min, int $max): Rule
    {
        return Rule::create()->min($min)->max($max);
    }

    /**
     * Create a rule for integer validation with range.
     */
    protected function createIntegerRangeRule(int $min, int $max): Rule
    {
        return Rule::create()->int()->min($min)->max($max);
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
     * Assert that ValidationException is thrown.
     *
     * @param callable $callback
     */
    protected function assertValidationExceptionThrown(callable $callback, string $message = ''): void
    {
        $this->expectException(ValidationException::class);
        $callback();
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
    // Parser Assertions
    // =========================================================================

    /**
     * Assert that RuleParser correctly parses a rule string.
     *
     * @param string|array<string|array<string,mixed>> $input
     */
    protected function assertRuleParsed(string|array $input, int $expectedCount, string $message = ''): void
    {
        $parsed = RuleParser::parse($input);
        $this->assertCount(
            $expectedCount,
            $parsed,
            $message ?: "Parser should produce {$expectedCount} rules"
        );
    }
}
