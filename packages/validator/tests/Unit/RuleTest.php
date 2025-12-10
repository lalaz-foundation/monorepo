<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Unit;

use Lalaz\Validator\Tests\Common\ValidatorUnitTestCase;
use Lalaz\Validator\Rule;
use Lalaz\Validator\RuleParser;

class RuleTest extends ValidatorUnitTestCase
{
    public function test_rule_builder_stringifies_rules(): void
    {
        $rule = Rule::create()
            ->required()
            ->email()
            ->min(3)
            ->max(10)
            ->message("bad")
            ->regex("/^a/");

        $string = (string) $rule;

        $this->assertStringContainsString("required", $string);
        $this->assertStringContainsString("email", $string);
        $this->assertStringContainsString("min:3", $string);
        $this->assertStringContainsString("max:10", $string);
    }

    public function test_rule_parser_normalizes_confirmed(): void
    {
        $rules = RuleParser::normalizeConfirmed("password", [
            "confirmed",
            ["match" => "confirmed", "name" => "match"],
        ]);

        $this->assertSame("password_confirmation", $rules[0]["match"] ?? null);
        $this->assertSame("password_confirmation", $rules[1]["match"] ?? null);
    }
}
