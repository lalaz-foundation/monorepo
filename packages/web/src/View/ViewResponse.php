<?php

declare(strict_types=1);

namespace Lalaz\Web\View;

use Lalaz\Config\Config;
use Lalaz\Web\Http\Contracts\RenderableInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * ViewResponse - A renderable view response object.
 *
 * This class represents a view that can be returned from a controller
 * and automatically rendered to an HTTP response by the framework.
 *
 * It applies view composers, merges ViewContext data, and uses the
 * configured template engine for rendering.
 *
 * Example usage:
 * ```php
 * class HomeController
 * {
 *     public function index()
 *     {
 *         return view('pages/home', ['title' => 'Welcome']);
 *     }
 * }
 * ```
 *
 * @package Lalaz\Web\View
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ViewResponse implements RenderableInterface
{
    /**
     * @var array<string, string> Additional headers to send with the response.
     */
    private array $headers = [];

    /**
     * @var string|null Optional layout to wrap the view.
     */
    private ?string $layout = null;

    /**
     * @var bool Whether to use a layout.
     */
    private bool $useLayout = true;

    /**
     * Create a new ViewResponse instance.
     *
     * @param string $template The view template name (e.g., 'pages/home').
     * @param array<string, mixed> $data Data to pass to the view.
     * @param int $statusCode HTTP status code (default: 200).
     */
    public function __construct(
        private string $template,
        private array $data = [],
        private int $statusCode = 200,
    ) {
    }

    /**
     * Create a new ViewResponse instance (static factory).
     *
     * @param string $template The view template name.
     * @param array<string, mixed> $data Data to pass to the view.
     * @param int $statusCode HTTP status code.
     * @return self
     */
    public static function create(
        string $template,
        array $data = [],
        int $statusCode = 200,
    ): self {
        return new self($template, $data, $statusCode);
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode The HTTP status code.
     * @return self
     */
    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Add a header to the response.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers to the response.
     *
     * @param array<string, string> $headers Headers to add.
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add or merge data to the view.
     *
     * @param string|array<string, mixed> $key Key or array of key-value pairs.
     * @param mixed $value Value (if $key is a string).
     * @return self
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Set a layout template to wrap the view.
     *
     * @param string $layout The layout template name.
     * @return self
     */
    public function layout(string $layout): self
    {
        $this->layout = $layout;
        $this->useLayout = true;
        return $this;
    }

    /**
     * Disable layout for this view (render as partial).
     *
     * @return self
     */
    public function withoutLayout(): self
    {
        $this->useLayout = false;
        $this->layout = null;
        return $this;
    }

    /**
     * Get the template name.
     *
     * @return string
     */
    public function template(): string
    {
        return $this->template;
    }

    /**
     * Get the view data.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get the status code.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Render the view to a string.
     *
     * Applies view composers and merges ViewContext data.
     *
     * @return string The rendered view content.
     */
    public function render(): string
    {
        $data = $this->applyComposers($this->template, $this->data);
        $merged = array_merge($data, ViewContext::resolved());
        $output = TemplateEngine::getEngine()->render($this->template, $merged);

        ViewContext::reset();

        return $output;
    }

    /**
     * Render this view to the given HTTP response.
     *
     * @param ResponseInterface $response The response to render to.
     * @return void
     */
    public function toResponse(ResponseInterface $response): void
    {
        $response->status($this->statusCode);

        foreach ($this->headers as $name => $value) {
            $response->header($name, $value);
        }

        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($this->render());
    }

    /**
     * Apply view composers to the view data.
     *
     * @param string $view The view template name.
     * @param array<string, mixed> $data The current view data.
     * @return array<string, mixed> The modified view data.
     */
    private function applyComposers(string $view, array $data): array
    {
        $composers = Config::get('views.composers', []);

        if (empty($composers)) {
            return $data;
        }

        foreach ($composers as $pattern => $composerClass) {
            if ($this->matchesPattern($view, $pattern)) {
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
    private function matchesPattern(string $view, string $pattern): bool
    {
        if ($view === $pattern) {
            return true;
        }

        if ($pattern === '*') {
            return true;
        }

        $regex = str_replace(['/', '*'], ['\/', '.*'], $pattern);

        return preg_match("/^{$regex}$/", $view) === 1;
    }

    /**
     * Convert to string (renders the view).
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
