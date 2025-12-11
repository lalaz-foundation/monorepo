<?php

declare(strict_types=1);

namespace Lalaz\Web\Routing\Registrars;

use Lalaz\Web\Routing\Contracts\RouteRegistrarInterface;
use Lalaz\Web\Routing\Contracts\RouterInterface;

/**
 * Loads route definitions from PHP files.
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class RouteFileRegistrar implements RouteRegistrarInterface
{
    /**
     * @param array<int, string> $files
     */
    public function __construct(private array $files)
    {
    }

    public function register(RouterInterface $router): void
    {
        foreach ($this->files as $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

            if (!file_exists($file)) {
                continue;
            }

            $result = require $file;

            if (is_callable($result)) {
                $result($router);
            }
        }
    }
}
