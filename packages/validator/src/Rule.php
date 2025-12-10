<?php

declare(strict_types=1);

namespace Lalaz\Validator;

/**
 * Fluent rule builder. Produces rule strings or arrays consumable by the Validator.
 */
final class Rule
{
    /**
     * @var array<int, string|array<string, mixed>>
     */
    private array $rules = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function required(): self
    {
        $this->rules[] = 'required';
        return $this;
    }

    public function int(): self
    {
        $this->rules[] = 'int';
        return $this;
    }

    public function integer(): self
    {
        return $this->int();
    }

    public function decimal(): self
    {
        $this->rules[] = 'decimal';
        return $this;
    }

    public function float(): self
    {
        return $this->decimal();
    }

    public function bool(): self
    {
        $this->rules[] = 'boolean';
        return $this;
    }

    public function boolean(): self
    {
        return $this->bool();
    }

    public function email(): self
    {
        $this->rules[] = 'email';
        return $this;
    }

    public function url(): self
    {
        $this->rules[] = 'url';
        return $this;
    }

    public function domain(): self
    {
        $this->rules[] = 'domain';
        return $this;
    }

    public function ip(): self
    {
        $this->rules[] = 'ip';
        return $this;
    }

    public function min(int|float $min): self
    {
        $this->rules[] = ['min' => $min];
        return $this;
    }

    public function max(int|float $max): self
    {
        $this->rules[] = ['max' => $max];
        return $this;
    }

    public function match(string $field): self
    {
        $this->rules[] = ['match' => $field];
        return $this;
    }

    public function same(string $field): self
    {
        return $this->match($field);
    }

    public function confirmed(): self
    {
        $this->rules[] = 'confirmed';
        return $this;
    }

    public function regex(string $pattern): self
    {
        $this->rules[] = ['pattern' => $pattern];
        return $this;
    }

    public function in(string ...$values): self
    {
        $this->rules[] = ['values' => $values];
        return $this;
    }

    public function notIn(string ...$values): self
    {
        $this->rules[] = ['not_in' => ['values' => $values]];
        return $this;
    }

    public function date(): self
    {
        $this->rules[] = 'date';
        return $this;
    }

    public function dateFormat(string $format): self
    {
        $this->rules[] = ['format' => $format];
        return $this;
    }

    public function json(): self
    {
        $this->rules[] = 'json';
        return $this;
    }

    public function custom(callable $callback): self
    {
        $this->rules[] = ['name' => 'custom', 'callback' => $callback];
        return $this;
    }

    public function message(string $message): self
    {
        if ($this->rules !== []) {
            $index = count($this->rules) - 1;
            $last = $this->rules[$index];
            if (is_array($last)) {
                $last['message'] = $message;
                $this->rules[$index] = $last;
            } else {
                $this->rules[$index] = [$last, 'message' => $message];
            }
        }
        return $this;
    }

    public function build(): string
    {
        $parts = [];
        foreach ($this->rules as $rule) {
            if (is_string($rule)) {
                $parts[] = $rule;
                continue;
            }

            $parts[] = $this->stringifyRule($rule);
        }
        return implode('|', array_filter($parts));
    }

    /**
     * @return array<int, string|array<string, mixed>>
     */
    public function buildArray(): array
    {
        return $this->rules;
    }

    public function __toString(): string
    {
        return $this->build();
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function stringifyRule(array $rule): string
    {
        if (isset($rule['min'])) {
            return "min:{$rule['min']}";
        }
        if (isset($rule['max'])) {
            return "max:{$rule['max']}";
        }
        if (isset($rule['match'])) {
            return "match:{$rule['match']}";
        }
        if (isset($rule['pattern'])) {
            return "regex:{$rule['pattern']}";
        }
        if (isset($rule['values']) && !isset($rule['not_in'])) {
            return 'in:' . implode(',', (array) $rule['values']);
        }
        if (isset($rule['not_in']['values'])) {
            return 'not_in:' . implode(',', (array) $rule['not_in']['values']);
        }
        if (isset($rule['format'])) {
            return "date_format:{$rule['format']}";
        }
        if (isset($rule['message'])) {
            return "message:{$rule['message']}";
        }
        if (isset($rule['callback'])) {
            return ''; // cannot stringify closures
        }

        return '';
    }
}
