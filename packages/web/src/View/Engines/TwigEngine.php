<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Engines;

use Lalaz\Config\Config;
use Lalaz\Support\Errors;
use Lalaz\Web\View\Contracts\TemplateEngineInterface;
use Lalaz\Web\View\ViewHelpers;

/**
 * Twig-based template engine implementation.
 * Loads views from configured path, optional cache, and registers view helpers/extensions.
 */
class TwigEngine implements TemplateEngineInterface
{
    private mixed $twig;

    public function __construct()
    {
        if (!class_exists('\\Twig\\Environment')) {
            Errors::throwConfigurationError(
                "TwigEngine requires Twig to be installed. Install it with: composer require 'twig/twig:^3.0'",
            );
        }

        $viewsPath = Config::get('views.path');
        $loaderClass = '\\Twig\\Loader\\FilesystemLoader';
        $envClass = '\\Twig\\Environment';

        $loader = new $loaderClass($viewsPath);

        $options = [];
        $cacheConfig = Config::get('views.cache');

        if (is_array($cacheConfig)) {
            $enabled = $cacheConfig['enabled'] ?? true;
            $cachePath = $cacheConfig['path'] ?? null;

            if ($enabled && is_string($cachePath) && $cachePath !== '') {
                if (!is_dir($cachePath)) {
                    if (!mkdir($cachePath, 0755, true) && !is_dir($cachePath)) {
                        Errors::throwConfigurationError(
                            'Unable to create views cache directory.',
                            ['cache_path' => $cachePath],
                        );
                    }
                }

                $options['cache'] = $cachePath;
            }
        } elseif (is_string($cacheConfig) && $cacheConfig !== '') {
            $cachePath = $cacheConfig;

            if (!is_dir($cachePath)) {
                if (!mkdir($cachePath, 0755, true) && !is_dir($cachePath)) {
                    Errors::throwConfigurationError(
                        'Unable to create views cache directory.',
                        ['cache_path' => $cachePath],
                    );
                }
            }

            $options['cache'] = $cachePath;
        }

        $this->twig = new $envClass($loader, $options);

        $this->attachUtilFunctions();
        $this->attachExtensions();
    }

    private function isAbsolutePath(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if ($path[0] === '/') {
            return true;
        }

        if (\strlen($path) > 1 && $path[1] === ':') {
            return true;
        }

        return false;
    }

    /**
     * Render a Twig template by name (appends .twig).
     */
    public function render(string $template, array $data = []): string
    {
        return $this->twig->render("$template.twig", $data);
    }

    /**
     * Render a template from a raw string.
     */
    public function renderFromString(string $content, array $data = []): string
    {
        $template = $this->twig->createTemplate($content);
        return $template->render($data);
    }

    private function attachUtilFunctions(): void
    {
        $twigFunctionClass = '\\Twig\\TwigFunction';

        foreach (ViewHelpers::all() as $helper) {
            $function = new $twigFunctionClass(
                $helper->getName(),
                $helper->getCallable(),
                $helper->getOptions(),
            );

            $this->twig->addFunction($function);
        }
    }

    private function attachExtensions(): void
    {
        $extensionFiles = Config::get('views.extensions');

        if ($extensionFiles) {
            foreach ($extensionFiles as $file) {
                if (file_exists($file)) {
                    $callback = require $file;
                    if (is_callable($callback)) {
                        $callback($this->twig);
                    }
                }
            }
        }
    }
}
