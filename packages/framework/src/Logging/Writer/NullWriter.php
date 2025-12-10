<?php

declare(strict_types=1);

namespace Lalaz\Logging\Writer;

use Lalaz\Logging\Contracts\WriterInterface;

/**
 * Null writer that discards all log messages.
 *
 * Useful for:
 * - Testing (avoid log output in tests)
 * - Disabling logging in specific environments
 * - Performance testing without I/O overhead
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class NullWriter implements WriterInterface
{
    /**
     * @var array<string> Captured messages (for testing)
     */
    private array $messages = [];

    /**
     * @var bool Whether to capture messages for inspection
     */
    private bool $capture;

    /**
     * @param bool $capture Whether to capture messages (useful for assertions in tests)
     */
    public function __construct(bool $capture = false)
    {
        $this->capture = $capture;
    }

    /**
     * Writes a log message (discards unless capture is enabled).
     *
     * When capture mode is enabled, messages are stored internally
     * for later inspection. Otherwise, messages are simply discarded.
     *
     * @param string $message The formatted log message to write
     *
     * @return void
     */
    public function write(string $message): void
    {
        if ($this->capture) {
            $this->messages[] = $message;
        }
        // Otherwise, do nothing - discard the message
    }

    /**
     * Gets all captured messages.
     *
     * Only available when capture mode is enabled during construction.
     *
     * @return array<int, string> Array of captured log messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Gets the last captured message.
     *
     * Useful for assertions in tests when you want to verify
     * the most recent log entry.
     *
     * @return string|null The last message or null if none captured
     */
    public function getLastMessage(): ?string
    {
        if (empty($this->messages)) {
            return null;
        }

        return $this->messages[array_key_last($this->messages)];
    }

    /**
     * Checks if any messages were captured.
     *
     * @return bool True if at least one message was captured
     */
    public function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Gets the count of captured messages.
     *
     * @return int The number of captured messages
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Clears all captured messages.
     *
     * Useful between test cases to reset state.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * Checks if a message matching a pattern was logged.
     *
     * Useful for assertions when you need to verify that a specific
     * type of log message was written without matching exact content.
     *
     * @param string $pattern Regex pattern to match against captured messages
     *
     * @return bool True if any captured message matches the pattern
     */
    public function hasMessageMatching(string $pattern): bool
    {
        foreach ($this->messages as $message) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }
}
