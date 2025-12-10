<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Integration;

use Lalaz\Validator\Tests\Common\ValidatorIntegrationTestCase;
use Lalaz\Validator\Concerns\Validatable;
use Lalaz\Validator\Rule;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Validator;

/**
 * Integration tests for the Validatable trait.
 *
 * Tests the trait's integration with classes and models,
 * including fill, validate, and error handling.
 *
 * @package lalaz/validator
 */
class ValidatableTraitIntegrationTest extends ValidatorIntegrationTestCase
{
    // =========================================================================
    // Basic Validation
    // =========================================================================

    public function test_is_valid_returns_true_for_valid_data(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->age = 25;

        $this->assertTrue($model->isValid());
    }

    public function test_is_valid_returns_false_for_invalid_data(): void
    {
        $model = $this->createTestModel();
        $model->name = '';
        $model->email = 'invalid';
        $model->age = 10;

        $this->assertFalse($model->isValid());
    }

    public function test_validate_throws_exception_on_failure(): void
    {
        $model = $this->createTestModel();
        $model->name = '';
        $model->email = 'invalid';

        $this->expectException(ValidationException::class);

        $model->validate();
    }

    public function test_validate_returns_self_on_success(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->age = 25;

        $result = $model->validate();

        $this->assertSame($model, $result);
    }

    // =========================================================================
    // Error Handling
    // =========================================================================

    public function test_errors_returns_all_validation_errors(): void
    {
        $model = $this->createTestModel();
        $model->name = '';
        $model->email = 'invalid';
        $model->age = 10;

        $model->isValid();
        $errors = $model->errors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    public function test_error_returns_first_error_for_field(): void
    {
        $model = $this->createTestModel();
        $model->name = '';

        $model->isValid();

        $this->assertNotNull($model->error('name'));
    }

    public function test_error_returns_null_for_valid_field(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->age = 25;

        $model->isValid();

        $this->assertNull($model->error('name'));
    }

    public function test_has_error_returns_true_for_field_with_errors(): void
    {
        $model = $this->createTestModel();
        $model->name = '';

        $model->isValid();

        $this->assertTrue($model->hasError('name'));
    }

    public function test_has_error_returns_false_for_valid_field(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->age = 25;

        $model->isValid();

        $this->assertFalse($model->hasError('name'));
    }

    public function test_error_messages_returns_flat_array(): void
    {
        $model = $this->createTestModel();
        $model->name = '';
        $model->email = 'invalid';

        $model->isValid();
        $messages = $model->errorMessages();

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);

        // All messages should be strings, not arrays
        foreach ($messages as $message) {
            $this->assertIsString($message);
        }
    }

    public function test_first_error_returns_first_message(): void
    {
        $model = $this->createTestModel();
        $model->name = '';

        $model->isValid();

        $this->assertNotNull($model->firstError());
        $this->assertIsString($model->firstError());
    }

    public function test_clear_errors_removes_all_errors(): void
    {
        $model = $this->createTestModel();
        $model->name = '';

        $model->isValid();
        $this->assertNotEmpty($model->errors());

        $model->clearErrors();
        $this->assertEmpty($model->errors());
    }

    // =========================================================================
    // Fill Method
    // =========================================================================

    public function test_fill_sets_properties(): void
    {
        $model = $this->createTestModel();

        $model->fill([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
        ]);

        $this->assertEquals('John Doe', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertEquals(25, $model->age);
    }

    public function test_fill_ignores_non_existent_properties(): void
    {
        $model = $this->createTestModel();

        $model->fill([
            'name' => 'John',
            'nonexistent' => 'value',
        ]);

        $this->assertEquals('John', $model->name);
        $this->assertFalse(property_exists($model, 'nonexistent'));
    }

    public function test_fill_returns_self_for_chaining(): void
    {
        $model = $this->createTestModel();

        $result = $model->fill(['name' => 'John']);

        $this->assertSame($model, $result);
    }

    public function test_fill_with_validate_flag_validates_after_fill(): void
    {
        $model = $this->createTestModel();

        $this->expectException(ValidationException::class);

        $model->fill(['name' => ''], validate: true);
    }

    public function test_fill_and_validate_throws_on_invalid(): void
    {
        $model = $this->createTestModel();

        $this->expectException(ValidationException::class);

        $model->fillAndValidate(['name' => '', 'email' => 'invalid']);
    }

    public function test_fill_and_validate_returns_self_on_valid(): void
    {
        $model = $this->createTestModel();

        $result = $model->fillAndValidate([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
        ]);

        $this->assertSame($model, $result);
    }

    // =========================================================================
    // Type Casting
    // =========================================================================

    public function test_fill_casts_integer_values(): void
    {
        $model = $this->createTestModel();

        $model->fill(['age' => '25']);

        $this->assertSame(25, $model->age);
    }

    public function test_fill_casts_boolean_values(): void
    {
        $model = $this->createModelWithBoolProperty();

        $model->fill(['active' => '1']);
        $this->assertTrue($model->active);

        $model->fill(['active' => 'true']);
        $this->assertTrue($model->active);

        $model->fill(['active' => 'on']);
        $this->assertTrue($model->active);

        $model->fill(['active' => '0']);
        $this->assertFalse($model->active);
    }

    public function test_fill_casts_string_values(): void
    {
        $model = $this->createTestModel();

        $model->fill(['name' => 123]);

        $this->assertSame('123', $model->name);
    }

    // =========================================================================
    // Partial Validation
    // =========================================================================

    public function test_is_valid_only_validates_specified_fields(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'invalid'; // Invalid but not checked
        $model->age = 10; // Invalid but not checked

        $result = $model->isValidOnly(['name']);

        $this->assertTrue($result);
    }

    public function test_is_valid_except_excludes_specified_fields(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John Doe';
        $model->email = 'invalid'; // Excluded
        $model->age = 10; // Excluded

        $result = $model->isValidExcept(['email', 'age']);

        $this->assertTrue($result);
    }

    // =========================================================================
    // Custom Validator
    // =========================================================================

    public function test_set_validator_uses_custom_instance(): void
    {
        $model = $this->createTestModel();
        $customValidator = new Validator();

        $model->setValidator($customValidator);
        $model->name = 'John';
        $model->email = 'john@example.com';
        $model->age = 25;

        $this->assertTrue($model->isValid());
    }

    // =========================================================================
    // Static Create Validated
    // =========================================================================

    public function test_create_validated_creates_and_validates(): void
    {
        $modelClass = get_class($this->createTestModel());

        $model = $modelClass::createValidated([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
        ]);

        $this->assertEquals('John Doe', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertEquals(25, $model->age);
    }

    public function test_create_validated_throws_on_invalid(): void
    {
        $modelClass = get_class($this->createTestModel());

        $this->expectException(ValidationException::class);

        $modelClass::createValidated([
            'name' => '',
            'email' => 'invalid',
        ]);
    }

    // =========================================================================
    // Custom Rules Override
    // =========================================================================

    public function test_validates_with_custom_rules(): void
    {
        $model = $this->createValidatableModel([
            'name' => 'required|min:5',
            'email' => 'required|email',
        ]);

        $model->name = 'John';
        $this->assertFalse($model->isValid());

        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $this->assertTrue($model->isValid());
    }

    public function test_validates_with_rule_builder_objects(): void
    {
        $model = $this->createValidatableModel([
            'name' => Rule::create()->required()->min(3)->max(50),
            'email' => Rule::create()->required()->email(),
        ]);

        $model->name = 'Jo';
        $this->assertFalse($model->isValid());

        $model->name = 'John';
        $model->email = 'john@example.com';
        $this->assertTrue($model->isValid());
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function test_handles_empty_rules(): void
    {
        // Create model with a dummy rule that won't be validated
        $model = new class {
            use Validatable;

            public string $name = '';
            public string $email = '';

            protected function rules(): array
            {
                return []; // Empty rules = always valid
            }
        };

        $model->name = '';
        $model->email = 'invalid';

        $this->assertTrue($model->isValid());
    }

    public function test_handles_null_values(): void
    {
        $model = $this->createTestModel();
        $model->name = 'John';
        $model->email = 'john@example.com';
        $model->age = 25;

        // Validate with null data should use model properties
        $this->assertTrue($model->isValid(null));
    }

    public function test_validate_with_custom_data(): void
    {
        $model = $this->createTestModel();

        // Validate with different data than model properties
        $customData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 30,
        ];

        $this->assertTrue($model->isValid($customData));
    }

    public function test_validate_with_custom_rules(): void
    {
        $model = $this->createTestModel();
        $model->name = 'Jo'; // Would fail default min:2

        // Use custom rules that allow shorter names
        $customRules = ['name' => 'required'];

        $this->assertTrue($model->isValid(null, $customRules));
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createTestModel(): object
    {
        return new class {
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
        };
    }

    private function createModelWithBoolProperty(): object
    {
        return new class {
            use Validatable;

            public string $name = '';
            public bool $active = false;

            protected function rules(): array
            {
                return [
                    'name' => 'required',
                ];
            }
        };
    }
}
