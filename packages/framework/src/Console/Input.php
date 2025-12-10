<?php

declare(strict_types=1);

namespace Lalaz\Console;

/**
 * Parses and provides access to command line input.
 *
 * This class handles parsing of command line arguments into structured
 * data, supporting positional arguments, long options (--name), short
 * options (-n), and flags. It provides a clean API for commands to
 * access their input.
 *
 * Example usage:
 * ```php
 * // php lalaz migrate --database=mysql -v --force
 * $input = new Input($argv);
 *
 * echo $input->command();           // "migrate"
 * echo $input->option('database');  // "mysql"
 * echo $input->hasFlag('v');        // true
 * echo $input->hasFlag('force');    // true
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Input
{
    /**
     * Parsed positional arguments.
     *
     * Index 0 contains the command name, subsequent indices contain
     * additional positional arguments.
     *
     * @var array<int, string>
     */
    private array $arguments = [];

    /**
     * Parsed options and flags.
     *
     * Keys are option names (without dashes), values are either the
     * option value or true for flags without values.
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * Creates a new input instance from argv.
     *
     * @param array<int, string> $argv Command line arguments (typically global $argv)
     */
    public function __construct(array $argv)
    {
        $this->parse($argv);
    }

    /**
     * Gets the command name.
     *
     * Returns the first positional argument, which is conventionally
     * the command name. Defaults to "list" if no command is provided.
     *
     * @return string The command name
     */
    public function command(): string
    {
        return $this->arguments[0] ?? 'list';
    }

    /**
     * Gets a positional argument by index.
     *
     * Index 0 returns the first argument after the command name.
     *
     * @param int         $index   The argument index (0-based, after command)
     * @param string|null $default Default value if argument not present
     *
     * @return string|null The argument value or default
     */
    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index + 1] ?? $default;
    }

    /**
     * Gets an option value by name.
     *
     * Options can be specified as --name=value or --name value.
     *
     * @param string $name    The option name (without dashes)
     * @param mixed  $default Default value if option not present
     *
     * @return mixed The option value or default
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Gets all parsed options.
     *
     * @return array<string, mixed> All options as name => value pairs
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Checks if a flag or option is present.
     *
     * @param string $name The flag/option name (without dashes)
     *
     * @return bool True if the flag/option was specified
     */
    public function hasFlag(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * Parses command line arguments.
     *
     * Handles:
     * - Long options: --name=value or --name value
     * - Short options: -n value
     * - Flags: --flag or -f (value is true)
     * - Positional arguments: everything else
     *
     * @param array<int, string> $argv The raw command line arguments
     *
     * @return void
     */
    private function parse(array $argv): void
    {
        $tokens = $argv;
        array_shift($tokens); // remove script name

        if ($tokens === []) {
            $this->arguments = [];
            return;
        }

        $this->arguments[] = $tokens[0];

        $positionals = [];

        for ($i = 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (str_starts_with($token, '--')) {
                $parts = explode('=', substr($token, 2), 2);
                $name = $parts[0];
                if ($parts[1] ?? null) {
                    $this->options[$name] = $parts[1];
                    continue;
                }

                $valueToken = $tokens[$i + 1] ?? null;
                if (
                    $valueToken !== null &&
                    !str_starts_with($valueToken, '-')
                ) {
                    $this->options[$name] = $valueToken;
                    $i++;
                } else {
                    $this->options[$name] = true;
                }
                continue;
            }

            if (str_starts_with($token, '-')) {
                $name = substr($token, 1);
                $valueToken = $tokens[$i + 1] ?? null;
                if (
                    $valueToken !== null &&
                    !str_starts_with($valueToken, '-')
                ) {
                    $this->options[$name] = $valueToken;
                    $i++;
                } else {
                    $this->options[$name] = true;
                }
                continue;
            }

            $positionals[] = $token;
        }

        $this->arguments = array_merge($this->arguments, $positionals);
    }
}
