<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use Lalaz\Scheduler\Mutex\MutexInterface;

/**
 * ScheduledCommand - A scheduled console command.
 *
 * @package lalaz/scheduler
 */
class ScheduledCommand extends ScheduledEvent
{
    /**
     * The command to run.
     */
    private string $command;

    /**
     * Command parameters.
     *
     * @var array<string, mixed>
     */
    private array $parameters;

    /**
     * Creates a new scheduled command.
     *
     * @param string $command The command name
     * @param array<string, mixed> $parameters Command parameters
     * @param MutexInterface|null $mutex
     * @param bool $distributedMode
     */
    public function __construct(
        string $command,
        array $parameters = [],
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ) {
        parent::__construct($mutex, $distributedMode);

        $this->command = $command;
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): mixed
    {
        // Build the command string
        $commandLine = $this->buildCommand();

        if ($this->runInBackground) {
            return $this->executeInBackground($commandLine);
        }

        return $this->runForeground($commandLine);
    }

    /**
     * Build the full command string.
     *
     * @return string
     */
    private function buildCommand(): string
    {
        $parts = ['php', 'lalaz', $this->command];

        foreach ($this->parameters as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = "--{$key}";
                }
            } elseif (is_numeric($key)) {
                $parts[] = $value;
            } else {
                $parts[] = "--{$key}=" . escapeshellarg((string) $value);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Run command in foreground (blocking).
     *
     * @param string $commandLine
     * @return int Exit code
     */
    private function runForeground(string $commandLine): int
    {
        $output = [];
        $exitCode = 0;

        exec($commandLine . ' 2>&1', $output, $exitCode);

        $this->handleOutput(implode("\n", $output));

        return $exitCode;
    }

    /**
     * Run command in background (non-blocking).
     *
     * @param string $commandLine
     * @return int Process ID
     */
    private function executeInBackground(string $commandLine): int
    {
        $outputRedirect = '';
        if ($this->outputPath !== null) {
            $operator = $this->appendOutput ? '>>' : '>';
            $outputRedirect = " {$operator} " . escapeshellarg($this->outputPath) . ' 2>&1';
        } else {
            $outputRedirect = ' > /dev/null 2>&1';
        }

        // Start process in background and return PID
        $pid = (int) shell_exec(
            sprintf('(%s%s) & echo $!', $commandLine, $outputRedirect)
        );

        return $pid;
    }

    /**
     * {@inheritdoc}
     */
    public function mutexName(): string
    {
        return 'command:' . sha1($this->command . serialize($this->parameters));
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        $summary = $this->command;

        if (!empty($this->parameters)) {
            $params = [];
            foreach ($this->parameters as $key => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $params[] = "--{$key}";
                    }
                } elseif (is_numeric($key)) {
                    $params[] = $value;
                } else {
                    $params[] = "--{$key}={$value}";
                }
            }
            $summary .= ' ' . implode(' ', $params);
        }

        return $summary;
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the command name with parameters.
     *
     * Alias for getSummary() for convenience.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->getSummary();
    }

    /**
     * Get the command parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
