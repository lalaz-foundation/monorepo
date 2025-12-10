<?php

declare(strict_types=1);

/**
 * Web Package Helper Functions
 *
 * Provides convenient helper functions for view rendering in Lalaz Framework.
 * These functions are auto-loaded when the web package is installed.
 *
 * @package Lalaz\Web
 * @since 1.0.0
 */

use Lalaz\Web\Http\RedirectResponse;
use Lalaz\Web\Http\ViewDataBag;
use Lalaz\Web\View\ErrorBag;
use Lalaz\Web\View\ViewResponse;

if (!function_exists('view')) {
    /**
     * Create a ViewResponse for rendering a template.
     *
     * Returns a ViewResponse object that can be returned from controllers.
     * The HttpKernel will automatically process this and render the view.
     *
     * Usage in controllers:
     * ```php
     * // Simple view
     * return view('home/index');
     *
     * // View with data
     * return view('users/show', ['user' => $user]);
     *
     * // View with status code
     * return view('errors/not-found', [], 404);
     *
     * // View with layout
     * return view('admin/dashboard', ['stats' => $stats])
     *     ->layout('layouts/admin');
     * ```
     *
     * @param string $template The template name (without extension)
     * @param array<string, mixed> $data Data to pass to the template
     * @param int $statusCode HTTP status code (default: 200)
     * @return ViewResponse
     */
    function view(string $template, array $data = [], int $statusCode = 200): ViewResponse
    {
        return new ViewResponse($template, $data, $statusCode);
    }
}

if (!function_exists('partial')) {
    /**
     * Create a ViewResponse for rendering a partial template (no layout).
     *
     * Convenience function for rendering partials or fragments.
     *
     * Usage:
     * ```php
     * // Render partial for HTMX/AJAX
     * return partial('components/user-card', ['user' => $user]);
     * ```
     *
     * @param string $template The partial template name
     * @param array<string, mixed> $data Data to pass to the template
     * @param int $statusCode HTTP status code (default: 200)
     * @return ViewResponse
     */
    function partial(string $template, array $data = [], int $statusCode = 200): ViewResponse
    {
        return (new ViewResponse($template, $data, $statusCode))->withoutLayout();
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response.
     *
     * Returns a RedirectResponse for fluent redirect building with
     * support for flashing input data, errors, and session messages.
     *
     * Usage in controllers:
     * ```php
     * // Simple redirect
     * return redirect('/dashboard');
     *
     * // Redirect with flash message
     * return redirect('/users')
     *     ->with('success', 'User created successfully');
     *
     * // Redirect with validation errors
     * return redirect('/users/create')
     *     ->withInput()
     *     ->withErrors($validator->errors());
     *
     * // Redirect with status code
     * return redirect('/new-location')->status(301);
     * ```
     *
     * @param string $url The URL to redirect to
     * @return RedirectResponse
     */
    function redirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url);
    }
}

if (!function_exists('back')) {
    /**
     * Create a redirect response to the previous URL.
     *
     * Returns a RedirectResponse that redirects to the HTTP referer,
     * or falls back to the root URL if no referer is present.
     *
     * Usage in controllers:
     * ```php
     * // Simple back redirect
     * return back();
     *
     * // Back with validation errors
     * return back()
     *     ->withInput()
     *     ->withErrors($errors);
     *
     * // Back with flash message
     * return back()->with('error', 'Invalid credentials');
     * ```
     *
     * @param string $fallback Fallback URL if no referer (default: '/')
     * @return RedirectResponse
     */
    function back(string $fallback = '/'): RedirectResponse
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return new RedirectResponse($referer);
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value from the previous request.
     *
     * Retrieves a previously flashed input value, useful for
     * repopulating form fields after validation errors.
     *
     * Usage in templates:
     * ```php
     * <input type="email" name="email" value="<?= old('email') ?>">
     * <input type="name" name="name" value="<?= old('name', $user->name) ?>">
     * ```
     *
     * @param string $key The input field name
     * @param mixed $default Default value if no old input exists
     * @return mixed The old input value or default
     */
    function old(string $key, mixed $default = null): mixed
    {
        return ViewDataBag::getOldInput($key, $default);
    }
}

if (!function_exists('errors')) {
    /**
     * Get the error bag from the previous request.
     *
     * Retrieves the flashed ErrorBag, useful for accessing
     * validation errors in templates and controllers.
     *
     * Usage:
     * ```php
     * $errors = errors();
     * if ($errors->has('email')) {
     *     echo $errors->first('email');
     * }
     * ```
     *
     * @return ErrorBag The error bag (empty if no errors)
     */
    function errors(): ErrorBag
    {
        return ViewDataBag::getErrors();
    }
}
