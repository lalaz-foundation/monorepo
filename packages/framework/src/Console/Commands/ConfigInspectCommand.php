<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Config\Config;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that inspects configuration values.
 *
 * Displays the resolved value for a given configuration key
 * using dot-notation syntax.
 *
 * Usage: php lalaz config:inspect app.name
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ConfigInspectCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'config:inspect';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Inspect the resolved value for a configuration key';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'key',
                'description' => 'Dot-notation key (e.g., app.name)',
                'optional' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $key = $input->argument(0);
        if ($key === null) {
            $output->error(
                'Usage: php lalaz config:inspect <key> (e.g., app.name)',
            );
            return 1;
        }

        $sentinel = new \stdClass();
        $value = Config::get($key, $sentinel);

        if ($value === $sentinel) {
            $output->error("Configuration key '{$key}' not found.");
            return 1;
        }

        $output->writeln(
            $key . ' = ' . $this->formatValue($value),
        );

        return 0;
    }

    /**
     * Formats a configuration value for display.
     *
     * @param mixed $value The value to format
     *
     * @return string The formatted value string
     */
    private function formatValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if ($value === null) {
                return 'null';
            }

            return (string) $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT) ?: 'null';
    }
}
