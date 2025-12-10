<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Contracts;

/**
 * Contract for HTTP Request objects.
 *
 * Provides a clean abstraction for accessing request data including
 * route parameters, query strings, form data, JSON body, headers,
 * cookies, and uploaded files.
 *
 * This interface enables dependency inversion, allowing components
 * to depend on the abstraction rather than the concrete Request class,
 * which improves testability and decoupling.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
interface RequestInterface
{
    /**
     * Get the HTTP request method.
     *
     * @return string The HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
     */
    public function method(): string;

    /**
     * Set the HTTP request method.
     *
     * This is primarily used by method spoofing middleware to convert
     * POST requests with a _method field to PUT, PATCH, or DELETE.
     *
     * @param string $method The HTTP method
     * @return void
     */
    public function setMethod(string $method): void;

    /**
     * Get the request path (without query string).
     *
     * @return string The request path (e.g., "/users/123")
     */
    public function path(): string;

    /**
     * Get the full request URI (including query string).
     *
     * @return string The full URI (e.g., "/users?page=2")
     */
    public function uri(): string;

    /**
     * Get all parameters (route + query combined).
     *
     * @return array<string, mixed> Combined parameters
     */
    public function params(): array;

    /**
     * Get a parameter value (route params take precedence over query).
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function param(string $name, mixed $default = null): mixed;

    /**
     * Get all route parameters.
     *
     * @return array<string, mixed> Route parameters
     */
    public function routeParams(): array;

    /**
     * Get a route parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function routeParam(string $name, mixed $default = null): mixed;

    /**
     * Get all query string parameters.
     *
     * @return array<string, mixed> Query parameters
     */
    public function queryParams(): array;

    /**
     * Get a query string parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function queryParam(string $name, mixed $default = null): mixed;

    /**
     * Get the request body (JSON or form data).
     *
     * @return mixed The body data (array or object for JSON, array for form)
     */
    public function body(): mixed;

    /**
     * Get a value from the request body.
     *
     * @param string $name Field name
     * @param mixed $default Default value if not found
     * @return mixed The field value
     */
    public function input(string $name, mixed $default = null): mixed;

    /**
     * Get all request data (params + body merged).
     *
     * @return array<string, mixed> All request data
     */
    public function all(): array;

    /**
     * Get JSON body data.
     *
     * @param string|null $key Optional key to retrieve specific value
     * @param mixed $default Default value if key not found
     * @return mixed The JSON data or specific value
     */
    public function json(?string $key = null, mixed $default = null): mixed;

    /**
     * Get a cookie value.
     *
     * @param string $name Cookie name
     * @return mixed The cookie value or null
     */
    public function cookie(string $name): mixed;

    /**
     * Check if a cookie exists.
     *
     * @param string $name Cookie name
     * @return bool Whether the cookie exists
     */
    public function hasCookie(string $name): bool;

    /**
     * Check if the request has JSON content.
     *
     * @return bool Whether the request is JSON
     */
    public function isJson(): bool;

    /**
     * Get all request headers.
     *
     * @return array<string, string> Request headers
     */
    public function headers(): array;

    /**
     * Get a header value (case-insensitive).
     *
     * @param string $name Header name
     * @param mixed $default Default value if not found
     * @return mixed The header value
     */
    public function header(string $name, mixed $default = null): mixed;

    /**
     * Check if a request field exists.
     *
     * @param string $key Field name
     * @return bool Whether the field exists
     */
    public function has(string $key): bool;

    /**
     * Get a boolean value from the request.
     *
     * @param string $key Field name
     * @param bool $default Default value if not found
     * @return bool The boolean value
     */
    public function boolean(string $key, bool $default = false): bool;

    /**
     * Get an uploaded file.
     *
     * @param string $key File input name
     * @return array<string, mixed>|null The file data or null
     */
    public function file(string $key);

    /**
     * Get the client IP address.
     *
     * @return string The client IP address
     */
    public function ip(): string;

    /**
     * Get the client user agent string.
     *
     * @return string The user agent
     */
    public function userAgent(): string;

    /**
     * Check if the client expects a JSON response.
     *
     * @return bool Whether client wants JSON
     */
    public function wantsJson(): bool;

    /**
     * Check if the connection is secure (HTTPS).
     *
     * @return bool Whether the connection is secure
     */
    public function isSecure(): bool;
}
