<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Contracts;

/**
 * Contract for route registrars that register routes with a router.
 *
 * Route registrars provide a modular way to register routes from
 * various sources such as route files, controller attributes, or
 * auto-discovery mechanisms.
 *
 * Example implementation:
 * ```php
 * class ApiRouteRegistrar implements RouteRegistrarInterface
 * {
 *     public function register(RouterInterface $router): void
 *     {
 *         $router->group(['prefix' => '/api'], function ($router) {
 *             $router->get('/users', [UserController::class, 'index']);
 *         });
 *     }
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
interface RouteRegistrarInterface
{
    /**
     * Register routes with the given router.
     *
     * @param RouterInterface $router The router to register routes with.
     * @return void
     */
    public function register(RouterInterface $router): void;
}
