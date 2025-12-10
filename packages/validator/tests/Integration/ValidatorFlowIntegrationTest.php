<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Integration;

use Lalaz\Validator\Tests\Common\ValidatorIntegrationTestCase;
use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;
use Lalaz\Validator\ValidationException;

/**
 * Integration tests for the complete validation flow.
 *
 * Tests end-to-end validation scenarios including string rules,
 * Rule builder objects, and mixed validation approaches.
 *
 * @package lalaz/validator
 */
class ValidatorFlowIntegrationTest extends ValidatorIntegrationTestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->createValidator();
    }

    // =========================================================================
    // String Rules Integration
    // =========================================================================

    public function test_validates_user_registration_with_string_rules(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'required|int|min:18',
            'password' => 'required|min:6|confirmed',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_fails_user_registration_with_invalid_data(): void
    {
        $data = [
            'name' => 'J',
            'email' => 'not-an-email',
            'age' => 15,
            'password' => 'abc',
            'password_confirmation' => 'xyz',
        ];

        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'age' => 'required|int|min:18',
            'password' => 'required|min:6|confirmed',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_validates_api_request_with_multiple_fields(): void
    {
        $data = [
            'title' => 'My First Post',
            'content' => 'This is the content of my post with more than 10 characters.',
            'status' => 'published',
            'category_id' => 1,
            'tags' => '["php", "laravel"]',
        ];

        $rules = [
            'title' => 'required|min:5|max:200',
            'content' => 'required|min:10',
            'status' => 'required|in:draft,published,archived',
            'category_id' => 'required|int',
            'tags' => 'json',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // Rule Builder Integration
    // =========================================================================

    public function test_validates_with_rule_builder_objects(): void
    {
        $data = [
            'email' => 'user@example.com',
            'age' => 25,
            'website' => 'https://example.com',
            'role' => 'admin',
        ];

        $rules = [
            'email' => Rule::create()->required()->email(),
            'age' => Rule::create()->required()->int()->min(18)->max(120),
            'website' => Rule::create()->url(),
            'role' => Rule::create()->required()->in('admin', 'editor', 'viewer'),
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_validates_with_custom_callback_rules(): void
    {
        $data = [
            'username' => 'john_doe',
            'code' => 'ABC123',
        ];

        $rules = [
            'username' => Rule::create()
                ->required()
                ->custom(fn($value) => preg_match('/^[a-z_]+$/', $value) === 1)
                ->message('Username must contain only lowercase letters and underscores'),
            'code' => Rule::create()
                ->required()
                ->custom(fn($value) => strlen($value) === 6)
                ->message('Code must be exactly 6 characters'),
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_custom_callback_fails_with_message(): void
    {
        $data = [
            'code' => 'invalid',
        ];

        $rules = [
            'code' => Rule::create()
                ->custom(fn($value) => $value === 'valid')
                ->message('Code is invalid'),
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('code', $errors);
        $this->assertContains('Code is invalid', $errors['code']);
    }

    // =========================================================================
    // Mixed Rules Integration
    // =========================================================================

    public function test_validates_with_mixed_string_and_builder_rules(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'ip_address' => '192.168.1.1',
        ];

        $rules = [
            'name' => 'required|min:2',
            'email' => Rule::create()->required()->email(),
            'age' => 'int|min:18',
            'ip_address' => Rule::create()->ip(),
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // Conditional Validation
    // =========================================================================

    public function test_skips_optional_fields_when_empty(): void
    {
        $data = [
            'name' => 'John',
            'email' => '', // empty, not required
            'website' => null, // null, not required
        ];

        $rules = [
            'name' => 'required|min:2',
            'email' => 'email', // only validates if provided
            'website' => 'url', // only validates if provided
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_validates_optional_fields_when_provided(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'invalid-email',
            'website' => 'not-a-url',
        ];

        $rules = [
            'name' => 'required|min:2',
            'email' => 'email',
            'website' => 'url',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('website', $errors);
    }

    // =========================================================================
    // Match/Confirmed Rules
    // =========================================================================

    public function test_match_rule_compares_fields(): void
    {
        $data = [
            'password' => 'secret123',
            'password_confirm' => 'secret123',
        ];

        $rules = [
            'password' => 'required|min:6',
            'password_confirm' => 'required|match:password',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_confirmed_rule_uses_field_confirmation_convention(): void
    {
        $data = [
            'email' => 'test@example.com',
            'email_confirmation' => 'test@example.com',
        ];

        $rules = [
            'email' => 'required|email|confirmed',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_confirmed_rule_fails_when_confirmation_differs(): void
    {
        $data = [
            'email' => 'test@example.com',
            'email_confirmation' => 'different@example.com',
        ];

        $rules = [
            'email' => 'required|email|confirmed',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('email', $errors);
    }

    // =========================================================================
    // Date Validation
    // =========================================================================

    public function test_validates_date_formats(): void
    {
        $data = [
            'birth_date' => '1990-05-15',
            'appointment' => '2024-12-25',
            'custom_date' => '25/12/2024',
        ];

        $rules = [
            'birth_date' => 'required|date',
            'appointment' => 'date_format:Y-m-d',
            'custom_date' => 'date_format:d/m/Y',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_fails_invalid_date_formats(): void
    {
        $data = [
            'birth_date' => 'not-a-date',
            'appointment' => '25-12-2024', // wrong format
        ];

        $rules = [
            'birth_date' => 'date',
            'appointment' => 'date_format:Y-m-d',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('birth_date', $errors);
        $this->assertArrayHasKey('appointment', $errors);
    }

    // =========================================================================
    // Regex Validation
    // =========================================================================

    public function test_validates_regex_patterns(): void
    {
        $data = [
            'phone' => '+1-555-123-4567',
            'zip_code' => '12345',
            'slug' => 'my-article-slug',
        ];

        $rules = [
            'phone' => 'regex:/^\+\d+-\d+-\d+-\d+$/',
            'zip_code' => 'regex:/^\d{5}$/',
            'slug' => 'regex:/^[a-z0-9-]+$/',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // In/Not In Validation
    // =========================================================================

    public function test_validates_in_list(): void
    {
        $data = [
            'status' => 'active',
            'priority' => 'high',
        ];

        $rules = [
            'status' => 'in:active,inactive,pending',
            'priority' => 'in:low,medium,high',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_validates_not_in_list(): void
    {
        $data = [
            'status' => 'active',
            'role' => 'editor',
        ];

        $rules = [
            'status' => 'not_in:banned,suspended',
            'role' => 'not_in:admin,superadmin',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_fails_when_value_is_in_prohibited_list(): void
    {
        $data = [
            'username' => 'admin',
        ];

        $rules = [
            'username' => 'not_in:admin,root,system',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('username', $errors);
    }

    // =========================================================================
    // Exception Integration
    // =========================================================================

    public function test_validate_model_throws_exception_on_failure(): void
    {
        $model = new \stdClass();
        $data = ['email' => 'invalid'];
        $rules = ['email' => 'required|email'];

        $this->expectException(ValidationException::class);

        $this->validator->validateModel($model, $data, $rules, 'create');
    }

    public function test_validation_exception_contains_errors(): void
    {
        $model = new \stdClass();
        $data = [
            'email' => 'invalid',
            'age' => 10,
        ];
        $rules = [
            'email' => 'email',
            'age' => 'int|min:18',
        ];

        try {
            $this->validator->validateModel($model, $data, $rules, 'create');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('age', $errors);
        }
    }

    // =========================================================================
    // Numeric Validation
    // =========================================================================

    public function test_validates_integer_values(): void
    {
        $data = [
            'quantity' => 10,
            'price' => '100', // String that represents integer
        ];

        $rules = [
            'quantity' => 'int',
            'price' => 'int',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_validates_decimal_values(): void
    {
        $data = [
            'price' => 99.99,
            'rate' => '3.14',
        ];

        $rules = [
            'price' => 'decimal',
            'rate' => 'decimal',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_validates_min_max_for_strings_and_numbers(): void
    {
        $data = [
            'name' => 'John',
            'age' => 25,
            'score' => 85.5,
        ];

        $rules = [
            'name' => 'min:2|max:50', // String length
            'age' => 'int|min:18|max:120', // Numeric value
            'score' => 'decimal|min:0|max:100', // Numeric value
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // Network Validation
    // =========================================================================

    public function test_validates_network_formats(): void
    {
        $data = [
            'website' => 'https://example.com',
            'domain' => 'example.com',
            'ip_v4' => '192.168.1.1',
            'ip_v6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        ];

        $rules = [
            'website' => 'url',
            'domain' => 'domain',
            'ip_v4' => 'ip',
            'ip_v6' => 'ip',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // JSON Validation
    // =========================================================================

    public function test_validates_json_strings(): void
    {
        $data = [
            'config' => '{"key": "value", "nested": {"a": 1}}',
            'array' => '[1, 2, 3]',
            'simple' => '"string"',
        ];

        $rules = [
            'config' => 'json',
            'array' => 'json',
            'simple' => 'json',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_fails_invalid_json(): void
    {
        $data = [
            'config' => '{invalid json}',
        ];

        $rules = [
            'config' => 'json',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('config', $errors);
    }

    // =========================================================================
    // Boolean Validation
    // =========================================================================

    public function test_validates_boolean_values(): void
    {
        $data = [
            'active' => true,
            'verified' => false,
        ];

        $rules = [
            'active' => 'boolean',
            'verified' => 'boolean',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_fails_non_boolean_values(): void
    {
        $data = [
            'active' => 'yes',
            'verified' => 1,
        ];

        $rules = [
            'active' => 'boolean',
            'verified' => 'boolean',
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('active', $errors);
        $this->assertArrayHasKey('verified', $errors);
    }
}
