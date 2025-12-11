<?php

declare(strict_types=1);

namespace Lalaz\Container\Contracts;

/**
 * Interface for containers that support tagged bindings.
 *
 * Tagged bindings allow grouping related services under a common tag,
 * making it easy to resolve all services of a particular category.
 *
 * @example
 * ```php
 * // Register multiple log handlers with a tag
 * $container->bind(FileLogHandler::class)->tag('log-handlers');
 * $container->bind(DatabaseLogHandler::class)->tag('log-handlers');
 * $container->bind(SlackLogHandler::class)->tag('log-handlers');
 *
 * // Resolve all handlers at once
 * $handlers = $container->tagged('log-handlers');
 * // Returns: [FileLogHandler, DatabaseLogHandler, SlackLogHandler]
 *
 * // Use in a composite pattern
 * $container->singleton(CompositeLogger::class, function ($c) {
 *     return new CompositeLogger($c->tagged('log-handlers'));
 * });
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 */
interface TaggableContainerInterface
{
    /**
     * Assign tags to an abstract type.
     *
     * @param string $abstract The service identifier
     * @param string|array<string> $tags One or more tags to assign
     * @return void
     */
    public function tag(string $abstract, string|array $tags): void;

    /**
     * Resolve all services tagged with the given tag.
     *
     * @param string $tag The tag to resolve
     * @return array<int, mixed> Array of resolved instances
     */
    public function tagged(string $tag): array;

    /**
     * Get all abstracts registered under a tag without resolving them.
     *
     * @param string $tag The tag to look up
     * @return array<int, string> Array of abstract identifiers
     */
    public function getTaggedAbstracts(string $tag): array;

    /**
     * Check if a tag exists.
     *
     * @param string $tag The tag to check
     * @return bool
     */
    public function hasTag(string $tag): bool;
}
