<?php

declare(strict_types=1);

namespace Lalaz\Console;

/**
 * Handles console output to stdout and stderr.
 *
 * This class provides a simple interface for writing output to the
 * console, supporting both standard output and error output streams.
 * It can be extended for custom output handling (e.g., buffering,
 * styling, or testing).
 *
 * Example usage:
 * ```php
 * $output = new Output();
 * $output->writeln('Processing...');
 * $output->writeln('Done!');
 * $output->error('Something went wrong');
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class Output
{
    /**
     * Writes a line to standard output.
     *
     * Appends a newline character after the message.
     *
     * @param string $message The message to write (empty string for blank line)
     *
     * @return void
     */
    public function writeln(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    /**
     * Writes an error message to standard error.
     *
     * Appends a newline character after the message.
     *
     * @param string $message The error message to write
     *
     * @return void
     */
    public function error(string $message): void
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m" . PHP_EOL);
    }

    /**
     * Writes a success message to standard output.
     *
     * @param string $message The success message to write
     *
     * @return void
     */
    public function success(string $message): void
    {
        fwrite(STDOUT, "\033[32m✓ {$message}\033[0m" . PHP_EOL);
    }

    /**
     * Writes an info message to standard output.
     *
     * @param string $message The info message to write
     *
     * @return void
     */
    public function info(string $message): void
    {
        fwrite(STDOUT, "\033[36m{$message}\033[0m" . PHP_EOL);
    }

    /**
     * Writes a warning message to standard output.
     *
     * @param string $message The warning message to write
     *
     * @return void
     */
    public function warning(string $message): void
    {
        fwrite(STDOUT, "\033[33m⚠ {$message}\033[0m" . PHP_EOL);
    }
}
