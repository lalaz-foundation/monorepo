<?php

declare(strict_types=1);

/**
 * HTTP Response helper functions.
 *
 * Provides convenient helper functions for creating HTTP responses
 * that can be returned directly from controllers.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Web\Http\JsonResponse;

if (!function_exists('json')) {
    /**
     * Create a JSON response.
     *
     * This helper allows controllers to return JSON responses explicitly:
     *
     * ```php
     * public function index()
     * {
     *     return json(['users' => $users]);
     * }
     *
     * public function created()
     * {
     *     return json(['id' => 123], 201);
     * }
     * ```
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $statusCode HTTP status code (default: 200).
     * @return JsonResponse
     */
    function json(mixed $data = [], int $statusCode = 200): JsonResponse
    {
        return new JsonResponse($data, $statusCode);
    }
}

if (!function_exists('json_success')) {
    /**
     * Create a success JSON response.
     *
     * ```php
     * return json_success(['user' => $user], 'User created successfully');
     * ```
     *
     * @param mixed $data The data to include.
     * @param string $message Success message.
     * @return JsonResponse
     */
    function json_success(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return JsonResponse::success($data, $message);
    }
}

if (!function_exists('json_error')) {
    /**
     * Create an error JSON response.
     *
     * ```php
     * return json_error('Validation failed', 422, ['email' => 'Invalid email']);
     * ```
     *
     * @param string $message Error message.
     * @param int $statusCode HTTP status code (default: 400).
     * @param array<string, mixed> $errors Optional validation errors.
     * @return JsonResponse
     */
    function json_error(
        string $message,
        int $statusCode = 400,
        array $errors = [],
    ): JsonResponse {
        return JsonResponse::error($message, $statusCode, $errors);
    }
}
