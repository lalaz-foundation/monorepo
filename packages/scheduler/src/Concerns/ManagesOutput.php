<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Concerns;

use Closure;

/**
 * ManagesOutput - Trait for managing scheduled task output.
 *
 * @package lalaz/scheduler
 */
trait ManagesOutput
{
    /**
     * Path to send output to.
     */
    protected ?string $outputPath = null;

    /**
     * Whether to append output instead of overwrite.
     */
    protected bool $appendOutput = false;

    /**
     * Email address to send output to.
     */
    protected ?string $emailOutputTo = null;

    /**
     * Callback to handle the output.
     */
    protected ?Closure $outputHandler = null;

    /**
     * Before callbacks to run before the task.
     *
     * @var array<Closure>
     */
    protected array $beforeCallbacks = [];

    /**
     * After callbacks to run after the task.
     *
     * @var array<Closure>
     */
    protected array $afterCallbacks = [];

    /**
     * Success callbacks to run if the task succeeds.
     *
     * @var array<Closure>
     */
    protected array $onSuccessCallbacks = [];

    /**
     * Failure callbacks to run if the task fails.
     *
     * @var array<Closure>
     */
    protected array $onFailureCallbacks = [];

    /**
     * Send output to a file.
     *
     * @param string $path
     * @param bool $append
     * @return static
     */
    public function sendOutputTo(string $path, bool $append = false): static
    {
        $this->outputPath = $path;
        $this->appendOutput = $append;
        return $this;
    }

    /**
     * Append output to a file.
     *
     * @param string $path
     * @return static
     */
    public function appendOutputTo(string $path): static
    {
        return $this->sendOutputTo($path, true);
    }

    /**
     * Send output to email.
     *
     * @param string $email
     * @return static
     */
    public function emailOutputTo(string $email): static
    {
        $this->emailOutputTo = $email;
        return $this;
    }

    /**
     * Handle output with a callback.
     *
     * @param Closure $callback Receives output and exit code
     * @return static
     */
    public function handleOutputUsing(Closure $callback): static
    {
        $this->outputHandler = $callback;
        return $this;
    }

    /**
     * Register a callback to run before the task.
     *
     * @param Closure $callback
     * @return static
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run after the task.
     *
     * @param Closure $callback Receives the output
     * @return static
     */
    public function after(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * Alias for after().
     *
     * @param Closure $callback
     * @return static
     */
    public function then(Closure $callback): static
    {
        return $this->after($callback);
    }

    /**
     * Register a callback to run on success.
     *
     * @param Closure $callback
     * @return static
     */
    public function onSuccess(Closure $callback): static
    {
        $this->onSuccessCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run on failure.
     *
     * @param Closure $callback Receives the exception
     * @return static
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailureCallbacks[] = $callback;
        return $this;
    }

    /**
     * Call all before callbacks.
     *
     * @return void
     */
    protected function callBeforeCallbacks(): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Call all after callbacks.
     *
     * @param mixed $output
     * @return void
     */
    protected function callAfterCallbacks(mixed $output = null): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $callback($output);
        }
    }

    /**
     * Call all success callbacks.
     *
     * @param mixed $output
     * @return void
     */
    protected function callSuccessCallbacks(mixed $output = null): void
    {
        foreach ($this->onSuccessCallbacks as $callback) {
            $callback($output);
        }
    }

    /**
     * Call all failure callbacks.
     *
     * @param \Throwable $e
     * @return void
     */
    protected function callFailureCallbacks(\Throwable $e): void
    {
        foreach ($this->onFailureCallbacks as $callback) {
            $callback($e);
        }
    }

    /**
     * Write output to file if configured.
     *
     * @param string $output
     * @return void
     */
    protected function writeOutput(string $output): void
    {
        if ($this->outputPath === null) {
            return;
        }

        $flags = $this->appendOutput ? FILE_APPEND : 0;
        file_put_contents($this->outputPath, $output . PHP_EOL, $flags);
    }

    /**
     * Send output to email if configured.
     *
     * @param string $output
     * @return void
     */
    protected function emailOutput(string $output): void
    {
        if ($this->emailOutputTo === null) {
            return;
        }

        // Simple mail() call - in production, use proper mailer
        mail(
            $this->emailOutputTo,
            sprintf('Scheduled Task Output: %s', $this->description ?? 'Unknown'),
            $output,
            'From: scheduler@localhost'
        );
    }

    /**
     * Process the output through all configured handlers.
     *
     * @param string $output
     * @param int $exitCode
     * @return void
     */
    protected function processOutput(string $output, int $exitCode = 0): void
    {
        $this->writeOutput($output);
        $this->emailOutput($output);

        if ($this->outputHandler !== null) {
            ($this->outputHandler)($output, $exitCode);
        }
    }

    /**
     * Handle output from the scheduled task.
     *
     * @param string $output
     * @param int $exitCode
     * @return void
     */
    protected function handleOutput(string $output, int $exitCode = 0): void
    {
        $this->processOutput($output, $exitCode);
    }
}
