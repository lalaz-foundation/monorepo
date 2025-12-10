<?php

declare(strict_types=1);

namespace Lalaz\Runtime\Console;

use Lalaz\Console\Application as ConsoleApplication;
use Lalaz\Console\Commands\ConfigCacheClearCommand;
use Lalaz\Console\Commands\ConfigCacheCommand;
use Lalaz\Console\Commands\ConfigInspectCommand;
use Lalaz\Console\Commands\CraftCommandCommand;
use Lalaz\Console\Commands\CraftControllerCommand;
use Lalaz\Console\Commands\CraftMiddlewareCommand;
use Lalaz\Console\Commands\CraftModelCommand;
use Lalaz\Console\Commands\CraftProviderCommand;
use Lalaz\Console\Commands\CraftRouteCommand;
use Lalaz\Console\Commands\HelpCommand;
use Lalaz\Console\Commands\ListCommands;
use Lalaz\Console\Commands\PackageAddCommand;
use Lalaz\Console\Commands\PackageDiscoverCommand;
use Lalaz\Console\Commands\PackageInfoCommand;
use Lalaz\Console\Commands\PackageListCommand;
use Lalaz\Console\Commands\PackageRemoveCommand;
use Lalaz\Console\Commands\RouteCacheClearCommand;
use Lalaz\Console\Commands\RouteCacheCommand;
use Lalaz\Console\Commands\RoutesInspectCommand;
use Lalaz\Console\Commands\RoutesListCommand;
use Lalaz\Console\Commands\RoutesValidateCommand;
use Lalaz\Console\Commands\ServeCommand;
use Lalaz\Console\Commands\VersionCommand;
use Lalaz\Console\Registry;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Console application kernel for CLI command handling.
 *
 * This kernel bootstraps the console application environment, registers
 * core framework commands, and provides the entry point for running
 * CLI commands. It reuses the HTTP application's container and services
 * to ensure consistency between web and console contexts.
 *
 * Usage:
 * ```php
 * #!/usr/bin/env php
 * <?php
 * require __DIR__ . '/vendor/autoload.php';
 *
 * $kernel = ConsoleKernel::boot(__DIR__);
 * exit($kernel->run($argv));
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ConsoleKernel
{
    /**
     * Create a new console kernel instance.
     *
     * @param ContainerInterface $container The dependency injection container.
     * @param Registry $registry The command registry.
     * @param ConsoleApplication $application The console application.
     */
    private function __construct(
        private ContainerInterface $container,
        private Registry $registry,
        private ConsoleApplication $application,
    ) {
    }

    /**
     * Boot the console kernel with a base path.
     *
     * This method initializes the HTTP application, extracts its container,
     * creates the command registry, registers default commands, and returns
     * a fully configured kernel ready to handle console commands.
     *
     * @param string $basePath The application base path (project root).
     * @param bool $debug Whether to enable debug mode.
     * @return self The booted console kernel.
     *
     * @example
     * ```php
     * $kernel = ConsoleKernel::boot('/var/www/myapp');
     * $exitCode = $kernel->run($argv);
     * ```
     */
    public static function boot(string $basePath, bool $debug = false): self
    {
        $http = HttpApplication::boot($basePath, debug: $debug);
        $container = $http->container();

        $registry = new Registry($container);
        $container->instance(Registry::class, $registry);

        $application = new ConsoleApplication($registry);
        $kernel = new self($container, $registry, $application);
        $kernel->registerDefaultCommands();

        // Boot commands from service providers (registered before Registry existed)
        $http->providers()->bootCommands();

        return $kernel;
    }

    /**
     * Core framework commands that are automatically registered.
     *
     * @var array<int, class-string>
     */
    private const CORE_COMMANDS = [
        HelpCommand::class,
        VersionCommand::class,
        ListCommands::class,
        ServeCommand::class,
        ConfigCacheCommand::class,
        ConfigCacheClearCommand::class,
        ConfigInspectCommand::class,
        CraftControllerCommand::class,
        CraftMiddlewareCommand::class,
        CraftProviderCommand::class,
        CraftRouteCommand::class,
        CraftModelCommand::class,
        CraftCommandCommand::class,
        PackageAddCommand::class,
        PackageRemoveCommand::class,
        PackageListCommand::class,
        PackageInfoCommand::class,
        PackageDiscoverCommand::class,
        RoutesListCommand::class,
        RoutesInspectCommand::class,
        RouteCacheCommand::class,
        RouteCacheClearCommand::class,
        RoutesValidateCommand::class,
    ];

    /**
     * Register all default framework commands.
     *
     * @return void
     */
    private function registerDefaultCommands(): void
    {
        foreach (self::CORE_COMMANDS as $command) {
            $this->registry->add($this->container->resolve($command));
        }
    }

    /**
     * Get the dependency injection container.
     *
     * @return ContainerInterface The container instance.
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the command registry.
     *
     * @return Registry The command registry.
     */
    public function registry(): Registry
    {
        return $this->registry;
    }

    /**
     * Run the console application with the given arguments.
     *
     * @param array<int, string> $argv Command line arguments (typically from global $argv).
     * @return int Exit code (0 for success, non-zero for failure).
     *
     * @example
     * ```php
     * $kernel = ConsoleKernel::boot(__DIR__);
     * exit($kernel->run($argv));
     * ```
     */
    public function run(array $argv): int
    {
        return $this->application->run($argv);
    }
}
