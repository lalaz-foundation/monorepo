<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Integration;

use Lalaz\Validator\Tests\Common\ValidatorIntegrationTestCase;
use Lalaz\Validator\Rule;
use Lalaz\Validator\RuleParser;
use Lalaz\Validator\Validator;

/**
 * Integration tests for the Rule builder.
 *
 * Tests the fluent Rule API for building validation rules,
 * including chaining, custom callbacks, and rule output formats.
 *
 * @package lalaz/validator
 */
class RuleBuilderIntegrationTest extends ValidatorIntegrationTestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->createValidator();
    }

    // =========================================================================
    // Basic Rule Building
    // =========================================================================

    public function test_creates_required_rule(): void
    {
        $rule = Rule::create()->required();

        $this->assertRuleStringContains($rule, 'required');
        $this->assertContains('required', $rule->buildArray());
    }

    public function test_creates_email_rule(): void
    {
        $rule = Rule::create()->email();

        $this->assertRuleStringContains($rule, 'email');
    }

    public function test_creates_url_rule(): void
    {
        $rule = Rule::create()->url();

        $this->assertRuleStringContains($rule, 'url');
    }

    public function test_creates_ip_rule(): void
    {
        $rule = Rule::create()->ip();

        $this->assertRuleStringContains($rule, 'ip');
    }

    public function test_creates_domain_rule(): void
    {
        $rule = Rule::create()->domain();

        $this->assertRuleStringContains($rule, 'domain');
    }

    public function test_creates_date_rule(): void
    {
        $rule = Rule::create()->date();

        $this->assertRuleStringContains($rule, 'date');
    }

    public function test_creates_json_rule(): void
    {
        $rule = Rule::create()->json();

        $this->assertRuleStringContains($rule, 'json');
    }

    // =========================================================================
    // Type Rules
    // =========================================================================

    public function test_creates_int_rule(): void
    {
        $rule = Rule::create()->int();

        $this->assertRuleStringContains($rule, 'int');
    }

    public function test_creates_integer_alias(): void
    {
        $rule = Rule::create()->integer();

        $this->assertRuleStringContains($rule, 'int');
    }

    public function test_creates_decimal_rule(): void
    {
        $rule = Rule::create()->decimal();

        $this->assertRuleStringContains($rule, 'decimal');
    }

    public function test_creates_float_alias(): void
    {
        $rule = Rule::create()->float();

        $this->assertRuleStringContains($rule, 'decimal');
    }

    public function test_creates_boolean_rule(): void
    {
        $rule = Rule::create()->bool();

        $this->assertRuleStringContains($rule, 'boolean');
    }

    public function test_creates_boolean_alias(): void
    {
        $rule = Rule::create()->boolean();

        $this->assertRuleStringContains($rule, 'boolean');
    }

    // =========================================================================
    // Size Rules
    // =========================================================================

    public function test_creates_min_rule(): void
    {
        $rule = Rule::create()->min(5);

        $this->assertRuleStringContains($rule, 'min:5');
    }

    public function test_creates_max_rule(): void
    {
        $rule = Rule::create()->max(100);

        $this->assertRuleStringContains($rule, 'max:100');
    }

    public function test_creates_min_max_range(): void
    {
        $rule = Rule::create()->min(5)->max(100);

        $string = (string) $rule;
        $this->assertStringContainsString('min:5', $string);
        $this->assertStringContainsString('max:100', $string);
    }

    public function test_min_max_with_float_values(): void
    {
        $rule = Rule::create()->min(0.5)->max(99.9);

        $string = (string) $rule;
        $this->assertStringContainsString('min:0.5', $string);
        $this->assertStringContainsString('max:99.9', $string);
    }

    // =========================================================================
    // Comparison Rules
    // =========================================================================

    public function test_creates_match_rule(): void
    {
        $rule = Rule::create()->match('password');

        $this->assertRuleStringContains($rule, 'match:password');
    }

    public function test_creates_same_alias(): void
    {
        $rule = Rule::create()->same('password');

        $this->assertRuleStringContains($rule, 'match:password');
    }

    public function test_creates_confirmed_rule(): void
    {
        $rule = Rule::create()->confirmed();

        $this->assertRuleStringContains($rule, 'confirmed');
    }

    // =========================================================================
    // List Rules
    // =========================================================================

    public function test_creates_in_rule(): void
    {
        $rule = Rule::create()->in('admin', 'editor', 'viewer');

        $this->assertRuleStringContains($rule, 'in:admin,editor,viewer');
    }

    public function test_creates_not_in_rule(): void
    {
        $rule = Rule::create()->notIn('banned', 'suspended');

        $this->assertRuleStringContains($rule, 'not_in:banned,suspended');
    }

    // =========================================================================
    // Pattern Rules
    // =========================================================================

    public function test_creates_regex_rule(): void
    {
        $rule = Rule::create()->regex('/^[a-z]+$/');

        $this->assertRuleStringContains($rule, 'regex:/^[a-z]+$/');
    }

    public function test_creates_date_format_rule(): void
    {
        $rule = Rule::create()->dateFormat('Y-m-d');

        $this->assertRuleStringContains($rule, 'date_format:Y-m-d');
    }

    // =========================================================================
    // Custom Callback Rules
    // =========================================================================

    public function test_creates_custom_callback_rule(): void
    {
        $rule = Rule::create()->custom(fn($value) => $value === 'expected');

        $array = $rule->buildArray();
        $this->assertNotEmpty($array);

        $lastRule = end($array);
        $this->assertIsArray($lastRule);
        $this->assertEquals('custom', $lastRule['name']);
        $this->assertIsCallable($lastRule['callback']);
    }

    public function test_custom_callback_validates_correctly(): void
    {
        $data = ['code' => 'ABC'];
        $rules = [
            'code' => Rule::create()->custom(fn($value) => strlen($value) === 3),
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_custom_callback_fails_correctly(): void
    {
        $data = ['code' => 'ABCD'];
        $rules = [
            'code' => Rule::create()->custom(fn($value) => strlen($value) === 3),
        ];

        $this->assertValidationFails($data, $rules);
    }

    public function test_custom_callback_receives_all_data(): void
    {
        $receivedData = null;

        $data = ['code' => 'ABC', 'prefix' => 'X'];
        $rules = [
            'code' => Rule::create()->custom(function ($value, $allData) use (&$receivedData) {
                $receivedData = $allData;
                return true;
            }),
        ];

        $this->validator->validateData($data, $rules);

        $this->assertEquals($data, $receivedData);
    }

    // =========================================================================
    // Custom Messages
    // =========================================================================

    public function test_adds_custom_message_to_rule(): void
    {
        $rule = Rule::create()->required()->message('Field is required');

        $array = $rule->buildArray();
        $lastRule = end($array);

        $this->assertIsArray($lastRule);
        $this->assertEquals('Field is required', $lastRule['message']);
    }

    public function test_custom_message_appears_in_errors(): void
    {
        $data = ['code' => 'invalid'];
        $rules = [
            'code' => Rule::create()
                ->custom(fn($value) => $value === 'valid')
                ->message('Code must be valid'),
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertArrayHasKey('code', $errors);
        $this->assertContains('Code must be valid', $errors['code']);
    }

    // =========================================================================
    // Rule Chaining
    // =========================================================================

    public function test_chains_multiple_rules(): void
    {
        $rule = Rule::create()
            ->required()
            ->email()
            ->min(5)
            ->max(100);

        $string = (string) $rule;

        $this->assertStringContainsString('required', $string);
        $this->assertStringContainsString('email', $string);
        $this->assertStringContainsString('min:5', $string);
        $this->assertStringContainsString('max:100', $string);
    }

    public function test_chains_rules_with_validation(): void
    {
        $data = [
            'email' => 'test@example.com',
            'age' => 25,
        ];

        $rules = [
            'email' => Rule::create()->required()->email()->min(5),
            'age' => Rule::create()->required()->int()->min(18)->max(120),
        ];

        $this->assertValidationPasses($data, $rules);
    }

    public function test_complex_rule_chain(): void
    {
        $rule = Rule::create()
            ->required()
            ->int()
            ->min(1)
            ->max(100)
            ->custom(fn($v) => $v % 2 === 0)
            ->message('Must be even');

        $data = ['number' => 42];
        $rules = ['number' => $rule];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // Rule Output Formats
    // =========================================================================

    public function test_build_returns_string(): void
    {
        $rule = Rule::create()->required()->email();

        $result = $rule->build();

        $this->assertIsString($result);
        $this->assertStringContainsString('required', $result);
        $this->assertStringContainsString('email', $result);
    }

    public function test_build_array_returns_array(): void
    {
        $rule = Rule::create()->required()->email();

        $result = $rule->buildArray();

        $this->assertIsArray($result);
        $this->assertContains('required', $result);
        $this->assertContains('email', $result);
    }

    public function test_to_string_magic_method(): void
    {
        $rule = Rule::create()->required()->email();

        $string = (string) $rule;

        $this->assertIsString($string);
        $this->assertStringContainsString('required', $string);
    }

    // =========================================================================
    // Integration with Validator
    // =========================================================================

    public function test_rule_builder_integrates_with_validator(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'website' => 'https://example.com',
            'status' => 'active',
        ];

        $rules = [
            'name' => Rule::create()->required()->min(2)->max(100),
            'email' => Rule::create()->required()->email(),
            'age' => Rule::create()->required()->int()->min(18),
            'website' => Rule::create()->url(),
            'status' => Rule::create()->required()->in('active', 'inactive'),
        ];

        $errors = $this->validator->validateData($data, $rules);

        $this->assertEmpty($errors);
    }

    public function test_mixed_string_and_builder_rules(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25,
        ];

        $rules = [
            'name' => 'required|min:2',
            'email' => Rule::create()->required()->email(),
            'age' => 'int|min:18',
        ];

        $this->assertValidationPasses($data, $rules);
    }

    // =========================================================================
    // Rule Parser Integration
    // =========================================================================

    public function test_parser_handles_string_rules(): void
    {
        $parsed = RuleParser::parse('required|email|min:5');

        $this->assertCount(3, $parsed);
    }

    public function test_parser_handles_array_rules(): void
    {
        $input = ['required', 'email', ['min' => 5]];
        $parsed = RuleParser::parse($input);

        $this->assertCount(3, $parsed);
        $this->assertEquals($input, $parsed);
    }

    public function test_parser_normalizes_confirmed(): void
    {
        $rules = RuleParser::normalizeConfirmed('password', ['required', 'confirmed']);

        $this->assertEquals('password_confirmation', $rules[1]['match']);
    }

    public function test_parser_handles_parameterized_rules(): void
    {
        $parsed = RuleParser::parse('min:5|max:100|in:a,b,c');

        $this->assertCount(3, $parsed);

        // Find min rule
        $minRule = array_filter($parsed, fn($r) => is_array($r) && ($r['name'] ?? '') === 'min');
        $this->assertNotEmpty($minRule);

        // Find max rule
        $maxRule = array_filter($parsed, fn($r) => is_array($r) && ($r['name'] ?? '') === 'max');
        $this->assertNotEmpty($maxRule);

        // Find in rule
        $inRule = array_filter($parsed, fn($r) => is_array($r) && ($r['name'] ?? '') === 'in');
        $this->assertNotEmpty($inRule);
    }

    public function test_parser_handles_custom_messages(): void
    {
        $parsed = RuleParser::parse('required|message:This field is required');

        // The message should be attached to the previous rule
        $this->assertCount(1, $parsed);

        $rule = $parsed[0];
        $this->assertIsArray($rule);
        $this->assertEquals('This field is required', $rule['message']);
    }
}
