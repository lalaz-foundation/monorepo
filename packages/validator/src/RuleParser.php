<?php

declare(strict_types=1);

namespace Lalaz\Validator;

/**
 * Parse rule strings or arrays into a normalized array representation.
 */
final class RuleParser
{
    /**
     * @param array<string|array<string,mixed>>|string $rules
     * @return array<int, string|array<string, mixed>>
     */
    public static function parse(array|string $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        $parsed = [];
        $parts = array_filter(array_map('trim', explode('|', $rules)));
        $customMessage = null;

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, 'message:')) {
                $customMessage = substr($part, 8);
                self::attachCustomMessage($parsed, $customMessage);
                $customMessage = null;
                continue;
            }

            if (str_contains($part, ':')) {
                [$name, $params] = explode(':', $part, 2);
                $paramArray = array_map('trim', explode(',', $params));
                $ruleArray = self::ruleArray($name, $paramArray);
                if ($customMessage !== null) {
                    $ruleArray['message'] = $customMessage;
                    $customMessage = null;
                }
                $parsed[] = $ruleArray;
                continue;
            }

            $ruleName = self::mapRuleNameToConstant($part);
            $parsed[] =
                $customMessage !== null
                    ? [$ruleName, 'message' => $customMessage]
                    : $ruleName;
            $customMessage = null;
        }

        return $parsed;
    }

    /**
     * @param array<int, string|array<string,mixed>> $rules
     * @return array<int, string|array<string,mixed>>
     */
    public static function normalizeConfirmed(
        string $field,
        array $rules,
    ): array {
        $normalized = [];
        foreach ($rules as $rule) {
            if ($rule === 'confirmed') {
                $normalized[] = [
                    'name' => 'match',
                    'match' => "{$field}_confirmation",
                ];
                continue;
            }

            if (is_array($rule)) {
                $match = $rule['match'] ?? null;
                if ($match === 'confirmed') {
                    $rule['match'] = "{$field}_confirmation";
                }
                $normalized[] = $rule;
                continue;
            }

            $normalized[] = $rule;
        }
        return $normalized;
    }

    /**
     * @param array<int, string|array<string,mixed>> $parsedRules
     */
    private static function attachCustomMessage(
        array &$parsedRules,
        string $message,
    ): void {
        if ($parsedRules === []) {
            return;
        }

        $index = count($parsedRules) - 1;
        $last = $parsedRules[$index];

        if (is_array($last)) {
            $last['message'] = $message;
            $parsedRules[$index] = $last;
        } else {
            $parsedRules[$index] = [$last, 'message' => $message];
        }
    }

    /**
     * @param array<int, string> $params
     * @return array<string, mixed>
     */
    private static function ruleArray(string $name, array $params): array
    {
        $rule = ['name' => self::mapRuleNameToConstant($name)];

        return match ($rule['name']) {
            'min' => $rule + ['min' => (float) $params[0]],
            'max' => $rule + ['max' => (float) $params[0]],
            'match' => $rule + ['match' => $params[0]],
            'regex' => $rule + ['pattern' => $params[0]],
            'in' => $rule + ['values' => $params],
            'not_in' => $rule + ['values' => $params],
            'date_format' => $rule + ['format' => $params[0]],
            default => $rule,
        };
    }

    private static function mapRuleNameToConstant(string $name): string
    {
        return match (strtolower($name)) {
            'required' => 'required',
            'int', 'integer' => 'int',
            'decimal', 'float' => 'decimal',
            'bool', 'boolean' => 'boolean',
            'email' => 'email',
            'url' => 'url',
            'domain' => 'domain',
            'ip' => 'ip',
            'min' => 'min',
            'max' => 'max',
            'match', 'same' => 'match',
            'regex' => 'regex',
            'in' => 'in',
            'not_in' => 'not_in',
            'confirmed' => 'confirmed',
            'date' => 'date',
            'date_format' => 'date_format',
            'json' => 'json',
            default => $name,
        };
    }
}
