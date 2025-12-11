<?php

declare(strict_types=1);

/**
 * Container helper functions.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Runtime\Application;

if (!function_exists('container')) {
    /**
     * Get the DI container instance.
     *
     * @return ContainerInterface
     *
     * @example
     * $container = container();
     * $container->bind(PaymentGateway::class, StripeGateway::class);
     */
    function container(): ContainerInterface
    {
        return Application::container();
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param string $abstract The service to resolve
     * @param array $parameters Additional parameters
     * @return mixed
     *
     * @example
     * $logger = resolve('logger');
     * $repo = resolve(UserRepository::class);
     * $service = resolve(UserService::class, ['userId' => 123]);
     */
    function resolve(string $abstract, array $parameters = []): mixed
    {
        return container()->resolve($abstract, $parameters);
    }
}

if (!function_exists('bind')) {
    /**
     * Bind a service to the container.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation
     * @return void
     *
     * @example
     * bind(PaymentGateway::class, StripeGateway::class);
     * bind('mailer', fn() => new Mailer(config('mail')));
     */
    function bind(string $abstract, mixed $concrete = null): void
    {
        container()->bind($abstract, $concrete);
    }
}

if (!function_exists('singleton')) {
    /**
     * Bind a service as singleton to the container.
     *
     * @param string $abstract The interface or class name
     * @param mixed $concrete The implementation
     * @return void
     *
     * @example
     * singleton(Database::class);
     * singleton('cache', fn() => new FileCache());
     */
    function singleton(string $abstract, mixed $concrete = null): void
    {
        container()->singleton($abstract, $concrete);
    }
}
