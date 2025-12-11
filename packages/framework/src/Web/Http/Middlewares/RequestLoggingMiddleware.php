<?php

declare(strict_types=1);

namespace Lalaz\Web\Http\Middlewares;

use Lalaz\Logging\Log;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * RequestLoggingMiddleware
 *
 * Logs HTTP requests with timing, memory usage, and status information.
 * Useful for observability, debugging, and monitoring in production.
 *
 * Features:
 * - Request duration tracking
 * - Memory usage tracking
 * - Configurable log levels by status code
 * - Path exclusion support
 * - Header/body logging (debug mode only)
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class RequestLoggingMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string> Paths to exclude from logging
     */
    private array $excludePaths;

    /**
     * @var bool Whether to log request headers
     */
    private bool $logHeaders;

    /**
     * @var bool Whether to log request body
     */
    private bool $logBody;

    /**
     * @var int Threshold in ms to consider a request "slow"
     */
    private int $slowThreshold;

    /**
     * @param array<string> $excludePaths Paths to exclude (supports wildcards)
     * @param bool $logHeaders Include headers in log context
     * @param bool $logBody Include body in log context (careful with sensitive data)
     * @param int $slowThreshold Milliseconds threshold for slow request warning
     */
    public function __construct(
        array $excludePaths = [],
        bool $logHeaders = false,
        bool $logBody = false,
        int $slowThreshold = 1000,
    ) {
        $this->excludePaths = $excludePaths;
        $this->logHeaders = $logHeaders;
        $this->logBody = $logBody;
        $this->slowThreshold = $slowThreshold;
    }

    public function handle(RequestInterface $request, ResponseInterface $response, callable $next): void
    {
        // Check if this path should be excluded
        if ($this->shouldExclude($request->path())) {
            $next($request, $response);
            return;
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Execute the rest of the middleware chain
        $next($request, $response);

        // Calculate metrics
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $statusCode = $response->getStatusCode();

        // Build log context
        $context = $this->buildContext(
            $request,
            $response,
            $duration,
            $memoryUsed,
        );

        // Determine log level and message
        $level = $this->determineLogLevel($statusCode, $duration);
        $message = $this->buildMessage($request, $statusCode, $duration);

        Log::log($level, $message, $context);
    }

    /**
     * Check if the given path should be excluded from logging.
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludePaths as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a path against a pattern (supports * wildcard).
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard pattern
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['/', '*'], ['\/', '.*'], $pattern) . '$/';
            return (bool) preg_match($regex, $path);
        }

        return false;
    }

    /**
     * Build the log context array.
     *
     * @return array<string, mixed>
     */
    private function buildContext(
        RequestInterface $request,
        ResponseInterface $response,
        float $duration,
        int $memoryUsed,
    ): array {
        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'memory_bytes' => $memoryUsed,
            'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        // Add query params if present
        $query = $request->queryParams();
        if (!empty($query)) {
            $context['query'] = $query;
        }

        // Add headers if enabled
        if ($this->logHeaders) {
            $context['headers'] = $this->sanitizeHeaders($request->headers());
        }

        // Add body if enabled (be careful with sensitive data)
        if ($this->logBody) {
            $body = $request->body();
            if (!empty($body)) {
                $context['body'] = $this->sanitizeBody($body);
            }
        }

        return $context;
    }

    /**
     * Sanitize headers by removing sensitive values.
     *
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ];

        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize request body by removing sensitive fields.
     *
     * @param mixed $body
     * @return mixed
     */
    private function sanitizeBody(mixed $body): mixed
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'cvv',
        ];

        if (is_array($body)) {
            foreach ($sensitiveFields as $field) {
                if (isset($body[$field])) {
                    $body[$field] = '[REDACTED]';
                }
            }
        }

        return $body;
    }

    /**
     * Determine the appropriate log level based on status and duration.
     */
    private function determineLogLevel(int $statusCode, float $duration): string
    {
        // Server errors are always errors
        if ($statusCode >= 500) {
            return 'error';
        }

        // Client errors are warnings
        if ($statusCode >= 400) {
            return 'warning';
        }

        // Slow requests are warnings
        if ($duration > $this->slowThreshold) {
            return 'warning';
        }

        // Everything else is info
        return 'info';
    }

    /**
     * Build the log message.
     */
    private function buildMessage(RequestInterface $request, int $statusCode, float $duration): string
    {
        $message = sprintf(
            '%s %s %d (%.2fms)',
            $request->method(),
            $request->path(),
            $statusCode,
            $duration,
        );

        if ($duration > $this->slowThreshold) {
            $message .= ' [SLOW]';
        }

        return $message;
    }

    /**
     * Create a middleware instance from configuration array.
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            excludePaths: $config['exclude_paths'] ?? [],
            logHeaders: $config['log_headers'] ?? false,
            logBody: $config['log_body'] ?? false,
            slowThreshold: $config['slow_threshold'] ?? 1000,
        );
    }
}
