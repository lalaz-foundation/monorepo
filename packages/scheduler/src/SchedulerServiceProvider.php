<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use Lalaz\Cache\CacheInterface;
use Lalaz\Config\Config;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Container\ServiceProvider;
use Lalaz\Scheduler\Console\ScheduleListCommand;
use Lalaz\Scheduler\Console\ScheduleRunCommand;
use Lalaz\Scheduler\Console\ScheduleTestCommand;
use Lalaz\Scheduler\Mutex\CacheMutex;
use Lalaz\Scheduler\Mutex\MutexInterface;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * SchedulerServiceProvider - Service provider for the Scheduler package.
 *
 * Registers the Schedule, Mutex, and CLI commands.
 *
 * Configuration modes:
 * - 'single' (default): No mutex coordination, suitable for single-server deployments
 * - 'distributed': Uses cache-based mutex for multi-server deployments
 *
 * @package lalaz/scheduler
 */
final class SchedulerServiceProvider extends ServiceProvider
{
    /**
     * Register scheduler services.
     *
     * @return void
     */
    public function register(): void
    {
        $config = $this->loadConfiguration();

        // Register mutex based on mode
        $this->registerMutex($config);

        // Register the Schedule
        $this->registerSchedule($config);

        // Register console commands
        $this->commands(
            ScheduleRunCommand::class,
            ScheduleListCommand::class,
            ScheduleTestCommand::class,
        );
    }

    /**
     * Load and merge configuration.
     *
     * @return array<string, mixed>
     */
    private function loadConfiguration(): array
    {
        $defaults = [
            'timezone' => date_default_timezone_get(),
            'mode' => 'single',
            'mutex' => [
                'driver' => 'cache',
                'store' => null,
                'prefix' => 'scheduler:',
            ],
            'logging' => [
                'enabled' => false,
                'channel' => null,
            ],
        ];

        $userConfig = Config::getArray('scheduler') ?? [];

        return array_replace_recursive($defaults, $userConfig);
    }

    /**
     * Register the mutex implementation based on mode.
     *
     * @param array<string, mixed> $config
     * @return void
     */
    private function registerMutex(array $config): void
    {
        $this->singleton(MutexInterface::class, function (ContainerInterface $container) use ($config): MutexInterface {
            $mode = $config['mode'] ?? 'single';

            // In single mode, use NullMutex (no coordination needed)
            if ($mode === 'single') {
                return new NullMutex();
            }

            // In distributed mode, use CacheMutex
            if ($mode === 'distributed') {
                if (!$container->has(CacheInterface::class)) {
                    throw new \RuntimeException(
                        'Scheduler in "distributed" mode requires the cache package. ' .
                        'Please install lalaz/cache or set SCHEDULE_MODE=single.'
                    );
                }

                /** @var CacheInterface $cache */
                $cache = $container->resolve(CacheInterface::class);

                return new CacheMutex($cache);
            }

            // Unknown mode, default to NullMutex
            return new NullMutex();
        });
    }

    /**
     * Register the Schedule instance.
     *
     * @param array<string, mixed> $config
     * @return void
     */
    private function registerSchedule(array $config): void
    {
        $this->singleton(Schedule::class, function (ContainerInterface $container) use ($config): Schedule {
            $timezone = $config['timezone'] ?? date_default_timezone_get();
            $mode = $config['mode'] ?? 'single';
            $isDistributed = $mode === 'distributed';

            // Resolve mutex (will be NullMutex or CacheMutex based on mode)
            /** @var MutexInterface $mutex */
            $mutex = $container->resolve(MutexInterface::class);

            $schedule = new Schedule($mutex, $timezone, $isDistributed);


            // Call the user's schedule definition if it exists
            $this->defineUserSchedule($container, $schedule);

            return $schedule;
        });
    }

    /**
     * Call the user's schedule definition callback.
     *
     * Users define their schedule in app/Console/Kernel.php or similar.
     *
     * @param ContainerInterface $container
     * @param Schedule $schedule
     * @return void
     */
    private function defineUserSchedule(ContainerInterface $container, Schedule $schedule): void
    {
        // Check if there's a ScheduleDefinition callback registered
        if ($container->has('scheduler.definition')) {
            $definition = $container->resolve('scheduler.definition');

            if (is_callable($definition)) {
                $definition($schedule);
            }
        }

        // Alternative: Check for a Console Kernel with schedule method
        $kernelClass = 'App\\Console\\Kernel';

        if (class_exists($kernelClass) && method_exists($kernelClass, 'schedule')) {
            $kernel = $container->has($kernelClass)
                ? $container->resolve($kernelClass)
                : new $kernelClass();

            if (method_exists($kernel, 'schedule')) {
                $kernel->schedule($schedule);
            }
        }
    }
}
