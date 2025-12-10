<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use Closure;
use Lalaz\Scheduler\Mutex\MutexInterface;

/**
 * ScheduledClosure - A scheduled closure/callback.
 *
 * @package lalaz/scheduler
 */
class ScheduledClosure extends ScheduledEvent
{
    /**
     * The callback to execute.
     *
     * @var callable
     */
    private $callback;

    /**
     * Creates a new scheduled closure.
     *
     * @param callable $callback
     * @param MutexInterface|null $mutex
     * @param bool $distributedMode
     */
    public function __construct(
        callable $callback,
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ) {
        parent::__construct($mutex, $distributedMode);

        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): mixed
    {
        $this->callBeforeCallbacks();

        try {
            $result = call_user_func($this->callback);

            if (is_string($result)) {
                $this->handleOutput($result);
            }

            $this->callAfterCallbacks($result);
            $this->callSuccessCallbacks($result);

            return $result;
        } catch (\Throwable $e) {
            $this->callFailureCallbacks($e);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mutexName(): string
    {
        if ($this->callback instanceof Closure) {
            $reflection = new \ReflectionFunction($this->callback);
            return 'closure:' . sha1(
                $reflection->getFileName() . ':' . $reflection->getStartLine()
            );
        }

        if (is_array($this->callback)) {
            $class = is_object($this->callback[0])
                ? get_class($this->callback[0])
                : $this->callback[0];
            return 'callback:' . sha1($class . '::' . $this->callback[1]);
        }

        if (is_string($this->callback)) {
            return 'callback:' . sha1($this->callback);
        }

        return 'closure:' . sha1(spl_object_hash((object) $this->callback));
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        if ($this->callback instanceof Closure) {
            $reflection = new \ReflectionFunction($this->callback);
            return sprintf(
                'Closure at %s:%d',
                basename($reflection->getFileName()),
                $reflection->getStartLine()
            );
        }

        if (is_array($this->callback)) {
            $class = is_object($this->callback[0])
                ? get_class($this->callback[0])
                : $this->callback[0];
            return "{$class}::{$this->callback[1]}()";
        }

        if (is_string($this->callback)) {
            return "{$this->callback}()";
        }

        return 'Closure';
    }

    /**
     * Get the callback.
     *
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }
}
