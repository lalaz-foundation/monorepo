<?php

declare(strict_types=1);

namespace Lalaz\Web;

use Lalaz\Container\ServiceProvider;
use Lalaz\Web\Console\Commands\CraftComponentCommand;
use Lalaz\Web\Console\Commands\CraftComposerCommand;
use Lalaz\Web\Console\Commands\CraftViewCommand;
use Lalaz\Web\Http\SessionManager;
use Lalaz\Web\View\Contracts\TemplateEngineInterface;
use Lalaz\Web\View\Engines\TwigEngine;

/**
 * Service provider for the Web package.
 *
 * Registers view rendering components, session management,
 * security features like CSRF protection, and console commands.
 *
 * @package Lalaz\Web
 */
final class WebServiceProvider extends ServiceProvider
{
    /**
     * Register web package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerTemplateEngine();
        $this->registerSessionManager();
        $this->registerCsrfProtection();
        $this->registerCommands();
    }

    /**
     * Register the template engine.
     *
     * @return void
     */
    private function registerTemplateEngine(): void
    {
        $this->singleton(TemplateEngineInterface::class, function () {
            return new TwigEngine();
        });
    }

    /**
     * Register the session manager.
     *
     * @return void
     */
    private function registerSessionManager(): void
    {
        $this->singleton(SessionManager::class, function () {
            $config = [];

            if (class_exists(\Lalaz\Config\Config::class)) {
                $config = \Lalaz\Config\Config::getArray('session', []) ?? [];
            }

            return new SessionManager($config);
        });
    }

    /**
     * Register CSRF protection.
     *
     * CSRF protection uses stateless cookie-based tokens
     * and doesn't require explicit registration since it's
     * a static helper, but we can initialize configuration here.
     *
     * @return void
     */
    private function registerCsrfProtection(): void
    {
        // CSRF protection is stateless and works via static methods
        // No explicit registration needed, but we ensure configuration
        // is loaded if needed in the future
    }

    /**
     * Register console commands provided by this package.
     *
     * @return void
     */
    private function registerCommands(): void
    {
        $this->commands(
            CraftComponentCommand::class,
            CraftComposerCommand::class,
            CraftViewCommand::class,
        );
    }
}
