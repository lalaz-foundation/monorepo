<?php

use Lalaz\Reactive\ReactiveManager;
use Lalaz\Runtime\Application;

if (!function_exists('reactive')) {
    /**
     * Render a reactive component
     *
     * @param string $name Component name
     * @param array $params Component parameters
     * @return string Rendered HTML
     */
    function reactive(string $name, array $params = []): string
    {
        $manager = Application::container()->resolve(ReactiveManager::class);
        $component = $manager->mount($name, $params);
        return $manager->render($component);
    }
}

if (!function_exists('reactiveScripts')) {
    /**
     * Include Lalaz Reactive JavaScript
     *
     * @return string Script tags
     */
    function reactiveScripts(): string
    {
        return <<<HTML
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/morph@3.x.x/dist/cdn.min.js"></script>
        <script defer src="/vendor/lalaz/reactive/js/lalaz-reactive.js"></script>
        HTML;
    }
}

if (!function_exists('reactiveStyles')) {
    /**
     * Include Lalaz Reactive CSS (if needed)
     *
     * @return string Style tags
     */
    function reactiveStyles(): string
    {
        return '<link rel="stylesheet" href="/vendor/lalaz/reactive/css/lalaz-reactive.css">';
    }
}
