<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Common;

use PHPUnit\Framework\TestCase;

/**
 * Base test case for Web package integration tests.
 *
 * Provides utilities for testing full request/response cycles
 * and template rendering with real dependencies.
 *
 * @package lalaz/web
 */
abstract class WebIntegrationTestCase extends TestCase
{
    /**
     * Skip test if Twig is not installed.
     */
    protected function requireTwig(): void
    {
        if (!class_exists('\\Twig\\Environment')) {
            $this->markTestSkipped('Twig is not installed. Run: composer require twig/twig:^3.0');
        }
    }

    /**
     * Skip test if Config class is not available.
     */
    protected function requireConfig(): void
    {
        if (!class_exists('\\Lalaz\\Config\\Config')) {
            $this->markTestSkipped('Config class is not available in this environment.');
        }
    }

    /**
     * Skip test if views path is not configured.
     *
     * @param string $viewsPath Path to views directory
     */
    protected function requireViewsPath(string $viewsPath): void
    {
        if (!is_dir($viewsPath)) {
            $this->markTestSkipped("Views directory not found: {$viewsPath}");
        }
    }
}
