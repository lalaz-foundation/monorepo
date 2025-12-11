<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Registrars;

use Lalaz\Web\Routing\Contracts\RouteRegistrarInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class ControllerAttributeRegistrar implements RouteRegistrarInterface
{
    /**
     * @param array<int, class-string> $controllers
     */
    public function __construct(private array $controllers)
    {
    }

    public function register(RouterInterface $router): void
    {
        if ($this->controllers === []) {
            return;
        }

        $router->registerControllers($this->controllers);
    }
}
