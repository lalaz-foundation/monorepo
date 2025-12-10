<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Config\Config;
use Lalaz\Web\Http\Concerns\FlashMessage;
use Lalaz\Web\Http\HttpEnvironment;
use Throwable;

/**
 * View rendering facade for the Lalaz framework.
 *
 * Provides static methods for rendering views, handling errors,
 * and managing view context with support for view composers.
 *
 * @package Lalaz\Web\View
 */
class View
{
    use FlashMessage;

    /**
     * Render a view template with the given data.
     *
     * @param string $view The view template name.
     * @param array $data Data to pass to the view.
     * @param int $statusCode HTTP status code.
     * @param bool $resetContext Whether to reset the view context after rendering.
     * @return string The rendered view content.
     */
    public static function render(
        string $view,
        array $data = [],
        int $statusCode = 200,
        bool $resetContext = true,
    ): string {
        $data = static::applyComposers($view, $data);
        $merged = array_merge($data, ViewContext::resolved());
        $output = TemplateEngine::getEngine()->render($view, $merged);

        if ($resetContext) {
            ViewContext::reset();
        }

        http_response_code($statusCode);
        return $output;
    }

    /**
     * Apply view composers to the view data.
     *
     * @param string $view The view template name.
     * @param array $data The current view data.
     * @return array The modified view data after applying composers.
     */
    private static function applyComposers(string $view, array $data): array
    {
        $composers = Config::get('views.composers', []);

        if (empty($composers)) {
            return $data;
        }

        foreach ($composers as $pattern => $composerClass) {
            if (static::matchesPattern($view, $pattern)) {
                if (class_exists($composerClass)) {
                    $composer = new $composerClass();

                    if (method_exists($composer, 'compose')) {
                        $data = $composer->compose($data);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Check if a view name matches a pattern.
     *
     * @param string $view The view name.
     * @param string $pattern The pattern to match against.
     * @return bool True if the view matches the pattern.
     */
    private static function matchesPattern(string $view, string $pattern): bool
    {
        if ($view === $pattern) {
            return true;
        }

        if ($pattern === '*') {
            return true;
        }

        $regex = str_replace(['/', '*'], ["\/", '.*'], $pattern);

        return preg_match("/^{$regex}$/", $view) === 1;
    }

    /**
     * Render a 404 not found error page.
     *
     * @param array $data Additional data to pass to the error view.
     * @return void
     */
    public static function renderNotFound(array $data = []): void
    {
        $view = Config::get('views.errors.not_found', 'errors/404');
        echo static::render($view, $data, 404);
    }

    /**
     * Render a 500 internal server error page.
     *
     * In development/debug mode, renders detailed error information.
     * In production, renders a generic error page or JSON response.
     *
     * @param array $data Additional data to pass to the error view.
     * @param Throwable|null $exception The exception that caused the error.
     * @return void
     */
    public static function renderError(
        array $data = [],
        ?Throwable $exception = null,
    ): void {
        if (ob_get_length()) {
            ob_clean();
        }

        if (Config::isDevelopment() || Config::isDebug()) {
            static::renderDevelopmentError($exception);
            return;
        }

        if (HttpEnvironment::isJsonRequest()) {
            static::sendJsonResponse(
                [
                    'status' => 'error',
                    'message' =>
                        'An unexpected error occurred. Please try again later.',
                ],
                500,
            );

            return;
        }

        $view = Config::get(
            'views.errors.internal_server_error',
            'errors/error',
        );
        echo static::render($view, $data, 500);
    }

    /**
     * Render detailed error information for development mode.
     *
     * @param Throwable|null $exception The exception to display.
     * @return void
     */
    private static function renderDevelopmentError(?Throwable $exception): void
    {
        if ($exception === null) {
            static::sendJsonResponse(
                ['status' => 'error', 'message' => 'Unknown error'],
                500,
            );
            return;
        }

        if (HttpEnvironment::isJsonRequest()) {
            static::sendJsonResponse(
                [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ],
                500,
            );

            return;
        }

        http_response_code(500);
        echo '<h1>Development Error</h1>';
        echo '<p><strong>Message:</strong> ' .
            htmlspecialchars($exception->getMessage()) .
            '</p>';
        echo '<p><strong>File:</strong> ' .
            htmlspecialchars($exception->getFile()) .
            '</p>';
        echo '<p><strong>Line:</strong> ' . $exception->getLine() . '</p>';
        echo '<h2>Stack Trace:</h2>';
        echo '<pre>' .
            htmlspecialchars($exception->getTraceAsString()) .
            '</pre>';
    }

    /**
     * Send a JSON response directly without requiring a Response instance.
     *
     * This is used internally for error responses where we need
     * to output JSON without access to a Response object.
     *
     * @param array $data The data to encode as JSON.
     * @param int $statusCode The HTTP status code.
     * @return void
     */
    private static function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
