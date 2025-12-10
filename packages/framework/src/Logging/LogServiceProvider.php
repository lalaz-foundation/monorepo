<?php

declare(strict_types=1);

namespace Lalaz\Logging;

use Lalaz\Config\Config;
use Lalaz\Container\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service provider for logging.
 *
 * Registers Logger and LogManager in the container based on configuration.
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register LogManager as singleton
        $this->container->singleton(LogManager::class, function () {
            $config = $this->getLoggingConfig();
            return new LogManager($config);
        });

        // Register Logger interface to resolve to LogManager
        $this->container->singleton(LoggerInterface::class, function () {
            return $this->container->resolve(LogManager::class);
        });

        // Alias for convenience
        $this->container->alias(LogManager::class, 'log');
    }

    public function boot(): void
    {
        // Set up the Log facade with the manager
        $manager = $this->container->resolve(LogManager::class);
        Log::setManager($manager);
    }

    /**
     * Get logging configuration.
     *
     * @return array
     */
    private function getLoggingConfig(): array
    {
        // Try to get from Config if available
        if (class_exists(Config::class)) {
            $config = Config::get('logging', []);
            if (!empty($config)) {
                return $config;
            }
        }

        // Default configuration
        return $this->getDefaultConfig();
    }

    /**
     * Get default logging configuration.
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        $isDebug = (bool) ($_ENV['APP_DEBUG'] ?? false);
        $logPath = $_ENV['LOG_PATH'] ?? 'storage/logs/app.log';
        $logLevel = $_ENV['LOG_LEVEL'] ?? ($isDebug ? LogLevel::DEBUG : LogLevel::INFO);

        return [
            'default' => 'stack',

            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['single', 'console'],
                    'level' => $logLevel,
                ],

                'single' => [
                    'driver' => 'single',
                    'path' => $logPath,
                    'level' => $logLevel,
                    'formatter' => 'text',
                ],

                'daily' => [
                    'driver' => 'daily',
                    'path' => $logPath,
                    'level' => $logLevel,
                    'days' => 14,
                    'formatter' => 'text',
                ],

                'console' => [
                    'driver' => 'console',
                    'level' => $logLevel,
                    'formatter' => 'text',
                ],

                'json' => [
                    'driver' => 'single',
                    'path' => str_replace('.log', '.json.log', $logPath),
                    'level' => $logLevel,
                    'formatter' => 'json',
                ],

                'security' => [
                    'driver' => 'single',
                    'path' => str_replace('app.log', 'security.log', $logPath),
                    'level' => LogLevel::INFO,
                    'formatter' => 'json',
                ],

                'audit' => [
                    'driver' => 'daily',
                    'path' => str_replace('app.log', 'audit.log', $logPath),
                    'level' => LogLevel::INFO,
                    'days' => 90,
                    'formatter' => 'json',
                ],
            ],
        ];
    }
}
