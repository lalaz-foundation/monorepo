<?php

declare(strict_types=1);

namespace Lalaz\Logging\Contracts;

/**
 * Contract for log writers.
 *
 * Writers are responsible for outputting formatted log messages
 * to various destinations (files, console, network, etc.).
 *
 * Example implementation:
 * ```php
 * class FileWriter implements WriterInterface
 * {
 *     public function __construct(private string $path) {}
 *
 *     public function write(string $message): void
 *     {
 *         file_put_contents($this->path, $message, FILE_APPEND | LOCK_EX);
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
interface WriterInterface
{
    /**
     * Write a log message to the destination.
     *
     * @param string $message The formatted log message to write
     * @return void
     */
    public function write(string $message): void;
}
