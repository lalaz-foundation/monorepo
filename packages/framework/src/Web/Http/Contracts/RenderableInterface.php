<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Contract for objects that can render themselves to an HTTP response.
 *
 * This interface enables a clean return-based pattern for controllers,
 * where the controller returns a Renderable object and the framework
 * automatically converts it to an HTTP response.
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
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
interface RenderableInterface
{
    /**
     * Render this object to the given HTTP response.
     *
     * Implementations should set appropriate headers, status codes,
     * and body content on the response object.
     *
     * @param ResponseInterface $response The response to render to.
     * @return void
     */
    public function toResponse(ResponseInterface $response): void;
}
