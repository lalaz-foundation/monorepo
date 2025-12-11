<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

/**
 * Exception thrown for routing-related errors.
 *
 * Use this exception when:
 * - Named routes cannot be found
 * - Required route parameters are missing
 * - Duplicate route names are detected
 * - Route patterns are invalid
 *
 * Example usage:
 * ```php
 * // Route not found
 * throw RoutingException::routeNotFound('users.profile', ['users.index', 'users.show']);
 *
 * // Missing parameter
 * throw RoutingException::missingRouteParameter('users.show', 'id');
 *
 * // Duplicate name
 * throw RoutingException::duplicateRouteName('home', '/', '/dashboard');
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class RoutingException extends FrameworkException
{
    /**
     * Create exception for route not found.
     *
     * Includes available routes for debugging and may suggest
     * similar route names using Levenshtein distance.
     *
     * @param string $name Route name that was not found
     * @param array<int, string> $availableRoutes List of available route names
     * @return self The exception instance
     */
    public static function routeNotFound(string $name, array $availableRoutes = []): self
    {
        $message = "Route '{$name}' is not defined.";

        $context = [
            'route' => $name,
            'available_routes' => $availableRoutes,
        ];

        if (!empty($availableRoutes)) {
            $context['suggestion'] = self::suggestSimilarRoute($name, $availableRoutes);
        }

        return new self($message, $context);
    }

    /**
     * Create exception for missing required parameter.
     *
     * Thrown when generating a URL for a named route but a required
     * parameter value was not provided.
     *
     * @param string $route Route name or path
     * @param string $parameter Missing parameter name
     * @return self The exception instance
     */
    public static function missingRouteParameter(string $route, string $parameter): self
    {
        return new self(
            "Missing required parameter '{$parameter}' for route '{$route}'.",
            [
                'route' => $route,
                'parameter' => $parameter,
            ],
        );
    }

    /**
     * Create exception for duplicate route name.
     *
     * Thrown when attempting to assign a name to a route that
     * is already used by another route.
     *
     * @param string $name Duplicate route name
     * @param string $existingPath Path of existing route with this name
     * @param string $newPath Path of new route attempting to use the name
     * @return self The exception instance
     */
    public static function duplicateRouteName(
        string $name,
        string $existingPath,
        string $newPath,
    ): self {
        return new self(
            "Route name '{$name}' is already defined for path '{$existingPath}'. Cannot assign to '{$newPath}'.",
            [
                'name' => $name,
                'existing_path' => $existingPath,
                'new_path' => $newPath,
            ],
        );
    }

    /**
     * Suggest a similar route name using Levenshtein distance.
     *
     * Helps users identify typos by suggesting the closest matching
     * route name within an edit distance of 3.
     *
     * @param string $name The mistyped route name
     * @param array<int, string> $available Available route names to compare against
     * @return string|null The suggested route name or null if none is close enough
     */
    private static function suggestSimilarRoute(string $name, array $available): ?string
    {
        $minDistance = PHP_INT_MAX;
        $suggestion = null;

        foreach ($available as $routeName) {
            $distance = levenshtein($name, $routeName);

            if ($distance < $minDistance && $distance <= 3) {
                $minDistance = $distance;
                $suggestion = $routeName;
            }
        }

        return $suggestion;
    }
}
