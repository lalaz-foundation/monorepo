<?php

declare(strict_types=1);

namespace Lalaz\Logging\Writer;

use Lalaz\Logging\Contracts\WriterInterface;

/**
 * Console writer for standard output.
 *
 * Writes log messages to STDOUT, making them visible in the console.
 * Useful for development, CLI applications, and Docker environments
 * where logs are collected from stdout.
 *
 * Example usage:
 * ```php
 * $logger = new Logger();
 * $logger->pushWriter(new ConsoleWriter());
 * $logger->info('Application started');
 * // Output: [2024-01-15 10:30:45] INFO: Application started
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ConsoleWriter implements WriterInterface
{
    /**
     * STDOUT stream resource.
     *
     * @var resource|false
     */
    private $stream;

    /**
     * Create a new console writer.
     *
     * Opens php://stdout for writing log messages.
     */
    public function __construct()
    {
        $this->stream = fopen('php://stdout', 'w');
    }

    /**
     * Write a log message to the console.
     *
     * @param string $message The formatted log message
     * @return void
     */
    public function write(string $message): void
    {
        if ($this->stream) {
            fwrite($this->stream, $message . PHP_EOL);
        }
    }

    /**
     * Close the stream on destruction.
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
