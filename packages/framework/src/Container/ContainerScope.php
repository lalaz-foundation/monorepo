<?php

declare(strict_types=1);

namespace Lalaz\Container;

use Lalaz\Container\Contracts\ContainerInterface;

/**
 * Helper for executing code within a container scope.
 *
 * Provides a safe way to run callbacks within a scoped container context,
 * guaranteeing that the scope is properly started and ended even if an
 * exception occurs. Useful for request-scoped services and testing.
 *
 * Example:
 * ```php
 * $result = ContainerScope::run($container, function() use ($container) {
 *     // Scoped services are available here
 *     return $container->resolve(RequestContext::class)->process();
 * });
 * // Scoped services are cleaned up after the callback completes
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ContainerScope
{
    /**
     * Execute a callback within a container scope, guaranteeing begin/end.
     *
     * Begins a new scope before executing the callback and ensures the scope
     * is ended after the callback completes, even if an exception is thrown.
     *
     * @template TReturn
     * @param ContainerInterface $container The container to scope.
     * @param callable():TReturn $callback The callback to execute within the scope.
     * @return TReturn The callback's return value.
     */
    public static function run(
        ContainerInterface $container,
        callable $callback,
    ): mixed {
        $container->beginScope();

        try {
            return $callback();
        } finally {
            $container->endScope();
        }
    }
}
