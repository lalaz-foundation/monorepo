<?php

declare(strict_types=1);

namespace Lalaz\Console\Contracts;

use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Interface for console commands.
 *
 * All console commands must implement this interface to be registered
 * with the console application. Commands define their name, description,
 * arguments, and options, and implement the handle method for execution.
 *
 * Example implementation:
 * ```php
 * class MigrateCommand implements CommandInterface
 * {
 *     public function name(): string { return 'migrate'; }
 *     public function description(): string { return 'Run database migrations'; }
 *
 *     public function arguments(): array {
 *         return [
 *             ['name' => 'step', 'description' => 'Steps to migrate', 'optional' => true]
 *         ];
 *     }
 *
 *     public function options(): array {
 *         return [
 *             ['name' => 'force', 'description' => 'Force in production', 'shortcut' => 'f']
 *         ];
 *     }
 *
 *     public function handle(Input $input, Output $output): int {
 *         $output->writeln('Running migrations...');
 *         return 0;
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
interface CommandInterface
{
    /**
     * Gets the command name.
     *
     * This is the name used to invoke the command from the CLI.
     * Convention is to use lowercase words separated by colons
     * (e.g., "migrate:status", "cache:clear").
     *
     * @return string The command name
     */
    public function name(): string;

    /**
     * Gets the command description.
     *
     * A brief description shown in the command list and help.
     *
     * @return string The description
     */
    public function description(): string;

    /**
     * Gets the command's positional arguments.
     *
     * Each argument is an array with:
     * - name: The argument name
     * - description: Help text for the argument
     * - optional: Whether the argument is optional
     *
     * @return array<int, array{name: string, description: string, optional: bool}>
     */
    public function arguments(): array;

    /**
     * Gets the command's options.
     *
     * Each option is an array with:
     * - name: The option name (used as --name)
     * - description: Help text for the option
     * - shortcut: Optional single-letter shortcut (used as -x)
     * - requiresValue: Whether the option requires a value
     *
     * @return array<int, array{name: string, description: string, shortcut?: string, requiresValue?: bool}>
     */
    public function options(): array;

    /**
     * Handles command execution.
     *
     * This method contains the command's logic. It receives parsed
     * input and an output instance for writing to the console.
     *
     * @param Input  $input  The parsed command input
     * @param Output $output The output handler
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public function handle(Input $input, Output $output): int;
}
