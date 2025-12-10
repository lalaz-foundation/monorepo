<?php declare(strict_types=1);

namespace Lalaz\Validator\Tests\Unit;

use Lalaz\Validator\Tests\Common\ValidatorUnitTestCase;
use Lalaz\Validator\ValidationException;
use Lalaz\Validator\Validator;
use Lalaz\Validator\Rule;

class ValidatorTest extends ValidatorUnitTestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function test_passes_when_data_satisfies_rules(): void
    {
        $errors = $this->validator->validateData(
            [
                "email" => "a@b.com",
                "age" => 20,
                "name" => "Ada",
                "role" => "admin",
            ],
            [
                "email" => "required|email",
                "age" => "int|min:18|max:40",
                "name" => Rule::create()->min(3)->max(10),
                "role" => Rule::create()->in("admin", "editor"),
            ],
        );

        $this->assertSame([], $errors);
    }

    public function test_collects_errors_for_invalid_data(): void
    {
        $errors = $this->validator->validateData(
            [
                "email" => "bad",
                "age" => 10,
                "name" => "x",
                "role" => "guest",
                "json" => "{oops",
            ],
            [
                "email" => "required|email",
                "age" => "int|min:18",
                "name" => "required|min:2",
                "role" => "in:admin,editor",
                "json" => "json",
            ],
        );

        $this->assertArrayHasKey("email", $errors);
        $this->assertArrayHasKey("age", $errors);
        $this->assertArrayHasKey("role", $errors);
        $this->assertArrayHasKey("json", $errors);
    }

    public function test_match_and_confirmed_rules(): void
    {
        $errors = $this->validator->validateData(
            [
                "password" => "abc",
                "password_confirmation" => "ab",
            ],
            [
                "password" => "confirmed",
            ],
        );

        $this->assertArrayHasKey("password", $errors);
    }

    public function test_custom_callbacks_can_fail_validation(): void
    {
        $errors = $this->validator->validateData(
            [
                "code" => "xyz",
            ],
            [
                "code" => Rule::create()
                    ->custom(fn($value) => $value === "ok")
                    ->message("bad"),
            ],
        );

        $this->assertSame("bad", $errors["code"][0] ?? null);
    }

    public function test_validate_as_model_validator_throws_validation_exception(): void
    {
        $model = new class {
            public array $data = [];
        };

        $this->expectException(ValidationException::class);

        $this->validator->validateModel(
            $model,
            ["email" => "bad"],
            ["email" => "email|required"],
            "create",
        );
    }

    public function test_boolean_url_domain_ip_regex_json_validations(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), "lalaz_mime_") ?: __FILE__;
        file_put_contents($tmp, "hello");

        $errors = $this->validator->validateData(
            [
                "flag" => "true",
                "site" => "not-a-url",
                "domain" => "invalid_domain",
                "ip" => "999.0.0.1",
                "code" => "xyz",
                "payload" => "{oops",
                "file" => $tmp,
            ],
            [
                "flag" => "boolean",
                "site" => "url",
                "domain" => "domain",
                "ip" => "ip",
                "code" => "regex:/^ok$/",
                "payload" => "json",
                "file" => "mime:text/plain",
            ],
        );

        $this->assertArrayHasKey("flag", $errors);
        $this->assertArrayHasKey("site", $errors);
        $this->assertArrayHasKey("domain", $errors);
        $this->assertArrayHasKey("ip", $errors);
        $this->assertArrayHasKey("code", $errors);
        $this->assertArrayHasKey("payload", $errors);

        @unlink($tmp);
    }

    public function test_min_max_length_vs_numeric_and_in_not_in_date_date_format(): void
    {
        $errors = $this->validator->validateData(
            [
                "age" => 10,
                "name" => "x",
                "status" => "guest",
                "dob" => "not-date",
                "custom" => "2024/01/01",
            ],
            [
                "age" => "min:18|max:20",
                "name" => "min:2",
                "status" => "not_in:guest,bot",
                "dob" => "date",
                "custom" => "date_format:Y-m-d",
            ],
        );

        $this->assertArrayHasKey("age", $errors);
        $this->assertArrayHasKey("name", $errors);
        $this->assertArrayHasKey("status", $errors);
        $this->assertArrayHasKey("dob", $errors);
        $this->assertArrayHasKey("custom", $errors);

        $ok = $this->validator->validateData(
            [
                "age" => 19,
                "name" => "John",
                "status" => "admin",
                "dob" => "2024-01-01",
                "custom" => "2024-02-02",
            ],
            [
                "age" => "min:18|max:20",
                "name" => "min:2|max:10",
                "status" => "not_in:guest,bot",
                "dob" => "date",
                "custom" => "date_format:Y-m-d",
            ],
        );

        $this->assertSame([], $ok);
    }
}
