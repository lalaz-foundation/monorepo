<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Web\View\Contracts\TemplateEngineInterface;

/**
 * TemplateEngine Facade
 *
 * Provides static access to template engine functionality via DI Container.
 *
 * @package elasticmind\lalaz-framework
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class TemplateEngine
{
    /**
     * Initialize template engine (no-op, kept for backward compatibility).
     *
     * @return void
     */
    public static function init(): void
    {
        // Template engine is now initialized via DI Container
        // This method is kept for backward compatibility
    }

    /**
     * Get the template engine instance from container.
     *
     * @return TemplateEngineInterface
     */
    public static function getEngine(): TemplateEngineInterface
    {
        return resolve(TemplateEngineInterface::class);
    }

    /**
     * Render a template.
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Rendered output
     */
    public static function render(string $template, array $data = []): string
    {
        return self::getEngine()->render($template, $data);
    }
}
