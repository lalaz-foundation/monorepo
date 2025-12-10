<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Tests\Unit\Concerns;

use Lalaz\Reactive\ReactiveComponent;
use Lalaz\Reactive\Tests\TestCase;

class ValidatesInputTest extends TestCase
{
    public function test_has_errors_returns_false_initially(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function render(): string
            {
                return '';
            }
        };

        $this->assertFalse($component->hasErrors());
    }

    public function test_get_errors_returns_empty_array_initially(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function render(): string
            {
                return '';
            }
        };

        $this->assertSame([], $component->getErrors());
    }

    public function test_get_error_returns_null_for_nonexistent_field(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function render(): string
            {
                return '';
            }
        };

        $this->assertNull($component->getError('nonexistent'));
    }

    public function test_errors_can_be_set_and_retrieved(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function setValidationErrors(array $errors): void
            {
                $this->errors = $errors;
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setValidationErrors([
            'email' => 'Invalid email format',
            'name' => 'Name is required',
        ]);

        $this->assertTrue($component->hasErrors());
        $this->assertCount(2, $component->getErrors());
        $this->assertSame('Invalid email format', $component->getError('email'));
        $this->assertSame('Name is required', $component->getError('name'));
    }

    public function test_get_error_returns_null_after_clearing_errors(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function setValidationErrors(array $errors): void
            {
                $this->errors = $errors;
            }

            public function clearErrors(): void
            {
                $this->errors = [];
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setValidationErrors(['email' => 'Invalid email']);
        $this->assertTrue($component->hasErrors());

        $component->clearErrors();
        $this->assertFalse($component->hasErrors());
        $this->assertNull($component->getError('email'));
    }

    public function test_has_errors_returns_true_when_errors_exist(): void
    {
        $component = new class extends ReactiveComponent {
            public string $email = '';

            public function setValidationErrors(array $errors): void
            {
                $this->errors = $errors;
            }

            public function render(): string
            {
                return '';
            }
        };

        $component->setValidationErrors(['field' => 'error']);

        $this->assertTrue($component->hasErrors());
    }

    public function test_multiple_errors_per_request(): void
    {
        $component = new class extends ReactiveComponent {
            public string $name = '';
            public string $email = '';
            public int $age = 0;

            public function setValidationErrors(array $errors): void
            {
                $this->errors = $errors;
            }

            public function render(): string
            {
                return '';
            }
        };

        $errors = [
            'name' => 'Name is required',
            'email' => 'Email is invalid',
            'age' => 'Age must be positive',
        ];

        $component->setValidationErrors($errors);

        $this->assertCount(3, $component->getErrors());
        $this->assertSame('Name is required', $component->getError('name'));
        $this->assertSame('Email is invalid', $component->getError('email'));
        $this->assertSame('Age must be positive', $component->getError('age'));
    }
}
