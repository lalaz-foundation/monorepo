<?php

declare(strict_types=1);

namespace Lalaz\Web\Http;

use Lalaz\Support\Concerns\HasAttributes;
use Lalaz\Web\Http\Contracts\RequestInterface;

/**
 * HTTP Request wrapper.
 *
 * Provides a clean interface for accessing request data including
 * route parameters, query strings, form data, JSON body, headers,
 * cookies, and uploaded files.
 *
 * Example usage:
 * ```php
 * // In a controller
 * public function store(Request $request): Response
 * {
 *     $name = $request->input('name');
 *     $id = $request->routeParam('id');
 *     $page = $request->queryParam('page', 1);
 *
 *     if ($request->wantsJson()) {
 *         return Response::json(['success' => true]);
 *     }
 *
 *     return Response::html('...');
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
class Request implements RequestInterface
{
    use HasAttributes;

    /**
     * HTTP request method (GET, POST, PUT, etc.).
     *
     * @var string
     */
    private string $method;

    /**
     * HTTP request headers.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Route parameters extracted from the URL path.
     *
     * @var array<string, mixed>
     */
    private array $routeParams = [];

    /**
     * Query string parameters.
     *
     * @var array<string, mixed>
     */
    private array $queryParams = [];

    /**
     * Form body data (POST fields).
     *
     * @var array<string, mixed>
     */
    private array $formBody = [];

    /**
     * Raw JSON body string (if present).
     *
     * @var string|null
     */
    private ?string $rawJsonBody = null;

    /**
     * Whether the JSON body has been parsed.
     *
     * @var bool
     */
    private bool $jsonParsed = false;

    /**
     * Parsed JSON body data.
     *
     * @var mixed
     */
    private mixed $jsonBody = null;

    /**
     * Request cookies.
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * Uploaded files.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $files = [];

    /**
     * Server parameters ($_SERVER).
     *
     * @var array<string, mixed>
     */
    private array $server = [];

    /**
     * Create a new Request instance.
     *
     * @param array<string, mixed> $routeParams Route parameters from URL
     * @param array<string, mixed> $queryParams Query string parameters
     * @param array<string, mixed> $formBody Form POST data
     * @param string|null $rawJsonBody Raw JSON body string
     * @param array<string, string> $headers HTTP headers
     * @param array<string, string> $cookies Request cookies
     * @param array<string, array<string, mixed>> $files Uploaded files
     * @param string $method HTTP method
     * @param array<string, mixed> $server Server parameters
     */
    public function __construct(
        array $routeParams,
        array $queryParams,
        array $formBody,
        ?string $rawJsonBody,
        array $headers,
        array $cookies,
        array $files,
        string $method,
        array $server,
    ) {
        $this->routeParams = $routeParams;
        $this->queryParams = $queryParams;
        $this->formBody = $formBody;
        $this->rawJsonBody = $rawJsonBody;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->method = strtoupper($method);
        $this->server = $server;
    }

    /**
     * Get the HTTP request method.
     *
     * @return string The HTTP method (GET, POST, PUT, PATCH, DELETE, etc.)
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Set the HTTP request method.
     *
     * Used by method spoofing middleware to override the actual HTTP method
     * based on a _method field or X-HTTP-Method-Override header.
     *
     * @param string $method The HTTP method to set
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->method = strtoupper($method);
    }

    /**
     * Get the request path (without query string).
     *
     * @return string The request path (e.g., "/users/123")
     */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url((string) $uri, PHP_URL_PATH);

        if ($path === null || $path === false || $path === '') {
            return '/';
        }

        return $path;
    }

    /**
     * Get the full request URI (including query string).
     *
     * @return string The full URI (e.g., "/users?page=2")
     */
    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get all parameters (route + query combined).
     *
     * @return array<string, mixed> Combined parameters
     */
    public function params(): array
    {
        return $this->getCombinedParams();
    }

    /**
     * Get a parameter value (route params take precedence over query).
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function param(string $name, mixed $default = null): mixed
    {
        $params = $this->getCombinedParams();
        return $params[$name] ?? $default;
    }

    /**
     * Get all route parameters.
     *
     * @return array<string, mixed> Route parameters
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Get a route parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function routeParam(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    /**
     * Set route parameters (used by router after matching).
     *
     * @param array<string, mixed> $params Route parameters
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get all query string parameters.
     *
     * @return array<string, mixed> Query parameters
     */
    public function queryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Get a query string parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not found
     * @return mixed The parameter value
     */
    public function queryParam(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    /**
     * Get the request body (JSON or form data).
     *
     * @return mixed The body data (array or object for JSON, array for form)
     */
    public function body(): mixed
    {
        // Only try JSON if content type indicates JSON
        if ($this->rawJsonBody !== null && $this->isJsonContentType()) {
            return $this->json();
        }

        return $this->formBody;
    }

    /**
     * Check if the request has a JSON content type.
     *
     * @return bool
     */
    private function isJsonContentType(): bool
    {
        $contentType = $this->header('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Get a value from the request body.
     *
     * @param string $name Field name
     * @param mixed $default Default value if not found
     * @return mixed The field value
     */
    public function input(string $name, mixed $default = null): mixed
    {
        $body = $this->body();

        if (is_array($body)) {
            return $body[$name] ?? $default;
        }

        if (is_object($body) && isset($body->{$name})) {
            return $body->{$name};
        }

        return $default;
    }

    /**
     * Get all request data (params + body merged).
     *
     * @return array<string, mixed> All request data
     */
    public function all(): array
    {
        $combined = $this->getCombinedParams();
        $body = $this->body();

        if (is_array($body)) {
            return array_merge($combined, $body);
        }

        if (is_object($body)) {
            return array_merge($combined, get_object_vars($body));
        }

        return $combined;
    }

    /**
     * Get JSON body data.
     *
     * @param string|null $key Optional key to retrieve specific value
     * @param mixed $default Default value if key not found
     * @return mixed The JSON data or specific value
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->rawJsonBody === null) {
            return $key === null ? null : $default;
        }

        if (!$this->jsonParsed) {
            $this->jsonBody = json_decode($this->rawJsonBody, true);
            $this->jsonParsed = true;
        }

        if ($this->jsonBody === null) {
            return $key === null ? null : $default;
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        if (
            is_array($this->jsonBody) &&
            array_key_exists($key, $this->jsonBody)
        ) {
            return $this->jsonBody[$key];
        }

        return $default;
    }

    /**
     * Get a cookie value.
     *
     * @param string $name Cookie name
     * @return mixed The cookie value or null
     */
    public function cookie($name): mixed
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Check if a cookie exists.
     *
     * @param string $name Cookie name
     * @return bool Whether the cookie exists
     */
    public function hasCookie(string $name): bool
    {
        return array_key_exists($name, $this->cookies);
    }

    /**
     * Check if the request has JSON content.
     *
     * @return bool Whether the request is JSON
     */
    public function isJson(): bool
    {
        $contentType = (string) $this->header('Content-Type', '');
        return $this->rawJsonBody !== null ||
            stripos($contentType, 'application/json') !== false;
    }

    /**
     * Get all request headers.
     *
     * @return array<string, string> Request headers
     */
    public function headers(): array
    {
        return $this->getHeaders();
    }

    /**
     * Get a header value (case-insensitive).
     *
     * @param string $name Header name
     * @param mixed $default Default value if not found
     * @return mixed The header value
     */
    public function header(string $name, mixed $default = null): mixed
    {
        $headers = $this->getHeaders();
        $target = strtoupper($name);

        foreach ($headers as $key => $value) {
            if (strtoupper($key) === $target) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Check if a request field exists.
     *
     * @param string $key Field name
     * @return bool Whether the field exists
     */
    public function has(string $key): bool
    {
        $combined = $this->getCombinedParams();

        if (array_key_exists($key, $combined)) {
            return true;
        }

        $body = $this->body();

        if (is_array($body)) {
            return array_key_exists($key, $body);
        }

        if (is_object($body)) {
            return isset($body->{$key});
        }

        return false;
    }

    /**
     * Get a boolean value from the request.
     *
     * Interprets "1", "true", "yes", "on" as true.
     * Interprets "0", "false", "no", "off" as false.
     *
     * @param string $key Field name
     * @param bool $default Default value if not found or not boolean-like
     * @return bool The boolean value
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->param($key, null);

        if ($value === null) {
            $value = $this->input($key, null);
        }

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * Get an uploaded file.
     *
     * @param string $key File input name
     * @return array<string, mixed>|null The file data or null
     */
    public function file(string $key)
    {
        if (!isset($this->files[$key])) {
            return null;
        }

        if (
            ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !==
            UPLOAD_ERR_OK
        ) {
            return null;
        }

        return $this->files[$key];
    }

    /**
     * Get the client IP address.
     *
     * Checks common proxy headers (X-Forwarded-For, CF-Connecting-IP, X-Real-IP)
     * before falling back to REMOTE_ADDR.
     *
     * @return string The client IP address
     */
    public function ip(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ipList = explode(',', $this->server[$header]);
                return trim($ipList[0]);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get the client user agent string.
     *
     * @return string The user agent or "Unknown" if not present
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Check if the client expects a JSON response.
     *
     * Returns true if the request has JSON content-type or
     * accepts JSON in the Accept header.
     *
     * @return bool Whether client wants JSON
     */
    public function wantsJson(): bool
    {
        if ($this->isJson()) {
            return true;
        }

        $accept = (string) $this->header('Accept', '');

        if ($accept === '') {
            return false;
        }

        $needles = ['application/json', 'text/json', '+json'];

        foreach ($needles as $needle) {
            if (stripos($accept, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current request is over HTTPS.
     *
     * Checks various server variables and proxy headers to determine
     * if the connection is secure.
     *
     * @return bool Whether the connection is secure
     */
    public function isSecure(): bool
    {
        // Direct HTTPS check
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        // Check X-Forwarded-Proto header (common behind proxies/load balancers)
        if (isset($this->server['HTTP_X_FORWARDED_PROTO']) && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Check X-Forwarded-SSL header
        if (isset($this->server['HTTP_X_FORWARDED_SSL']) && $this->server['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        // Check port 443
        if (isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * Create a Request from PHP superglobals.
     *
     * Factory method for creating a Request from the current
     * PHP environment ($_GET, $_POST, $_SERVER, etc.).
     *
     * @param array<string, mixed> $routeParams Route parameters (set by router)
     * @return self The Request instance
     */
    public static function fromGlobals(array $routeParams = []): self
    {
        $rawBody = file_get_contents('php://input');
        $rawBody = $rawBody === false || $rawBody === '' ? null : $rawBody;

        return new self(
            $routeParams,
            $_GET ?? [],
            $_POST ?? [],
            $rawBody,
            self::extractHeaders($_SERVER ?? []),
            $_COOKIE ?? [],
            $_FILES ?? [],
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER ?? [],
        );
    }

    /**
     * Get combined route and query parameters.
     *
     * @return array<string, mixed> Combined parameters
     */
    private function getCombinedParams(): array
    {
        return array_merge($this->routeParams, $this->queryParams);
    }

    /**
     * Get request headers.
     *
     * @return array<string, string> The headers
     */
    private function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Extract HTTP headers from $_SERVER array.
     *
     * @param array<string, mixed> $server The server array
     * @return array<string, string> Extracted headers
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))),
                );
                $headers[$name] = $value;
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', ucwords(strtolower($key), '_'));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
