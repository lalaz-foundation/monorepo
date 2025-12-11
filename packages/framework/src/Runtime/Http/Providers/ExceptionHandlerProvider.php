<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Http\Providers;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Lalaz\Exceptions\ConfigurationException;
use Lalaz\Web\Http\Contracts\ExceptionHandlerInterface;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Web\Http\Contracts\ExtensibleExceptionHandlerInterface;

/**
 * Service provider for configuring exception handling.
 *
 * Registers custom exception renderers and reporters from configuration.
 * Allows applications to customize how exceptions are displayed and logged
 * through the errors.renderers and errors.reporters config options.
 *
 * Configuration example (config/errors.php):
 * ```php
 * return [
 *     'renderers' => [
 *         App\Exceptions\CustomExceptionRenderer::class,
 *     ],
 *     'reporters' => [
 *         App\Exceptions\SlackExceptionReporter::class,
 *     ],
 * ];
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ExceptionHandlerProvider extends ServiceProvider
{
    /**
     * Register custom renderers and reporters with the exception handler.
     *
     * @return void
     */
    public function register(): void
    {
        $handler = $this->resolveHandler();

        if ($handler === null) {
            return;
        }

        $renderers = $this->resolveDefinitions(
            Config::getArray('errors.renderers', []),
            ExceptionRendererInterface::class,
        );

        foreach (array_reverse($renderers) as $renderer) {
            $handler->prependRenderer($renderer);
        }

        foreach (
            $this->resolveDefinitions(
                Config::getArray('errors.reporters', []),
                ExceptionReporterInterface::class,
            ) as $reporter
        ) {
            $handler->addReporter($reporter);
        }
    }

    /**
     * Resolve the extensible exception handler from the container.
     *
     * @return ExtensibleExceptionHandlerInterface|null The handler or null if not available.
     */
    private function resolveHandler(): ?ExtensibleExceptionHandlerInterface
    {
        if (!$this->container->bound(ExceptionHandlerInterface::class)) {
            return null;
        }

        $handler = $this->container->resolve(ExceptionHandlerInterface::class);

        if ($handler instanceof ExtensibleExceptionHandlerInterface) {
            return $handler;
        }

        return null;
    }

    /**
     * Resolve an array of class definitions into instances.
     *
     * Accepts class names (strings) or pre-instantiated objects.
     * Validates that all resolved instances implement the expected interface.
     *
     * @template T of object
     * @param array<int, mixed>|null $definitions Class names or objects.
     * @param class-string<T> $expected The expected interface/class.
     * @return array<int, T> Array of resolved instances.
     * @throws ConfigurationException If a definition is invalid.
     */
    private function resolveDefinitions(
        ?array $definitions,
        string $expected,
    ): array {
        if ($definitions === null) {
            return [];
        }

        $instances = [];

        foreach ($definitions as $definition) {
            if (is_string($definition)) {
                $instance = $this->container->resolve($definition);
            } elseif (is_object($definition)) {
                $instance = $definition;
            } else {
                throw ConfigurationException::invalidValue(
                    'exception_handler_definition',
                    $definition,
                    'string class name or object instance',
                );
            }

            if (!($instance instanceof $expected)) {
                throw ConfigurationException::invalidValue(
                    'exception_handler_class',
                    get_class($instance),
                    "{$expected} implementation",
                );
            }

            $instances[] = $instance;
        }

        return $instances;
    }
}
