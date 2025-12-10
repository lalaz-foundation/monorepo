<?php

declare(strict_types=1);

namespace Lalaz\Reactive\Concerns;

/**
 * HandlesEvents trait
 *
 * Provides event handling capabilities to ReactiveComponent
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
trait HandlesEvents
{
    /**
     * Call an event listener method
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return mixed Result of the listener
     */
    protected function callListener(string $event, array $data = []): mixed
    {
        if (!isset($this->listeners[$event])) {
            return null;
        }

        $handler = $this->listeners[$event];

        if (is_callable($handler)) {
            return $handler($data);
        }

        if (is_string($handler) && method_exists($this, $handler)) {
            return $this->$handler($data);
        }

        return null;
    }

    /**
     * Check if component listens to an event
     *
     * @param string $event Event name
     * @return bool
     */
    public function listensTo(string $event): bool
    {
        return isset($this->listeners[$event]);
    }
}
