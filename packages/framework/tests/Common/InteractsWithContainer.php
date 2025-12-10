<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

use Lalaz\Container\Container;
use Lalaz\Container\Contracts\ContainerInterface;

/**
 * Trait for tests that need a fresh container instance.
 *
 * @package lalaz/framework
 */
trait InteractsWithContainer
{
    protected ?ContainerInterface $container = null;

    protected function setUpContainer(): void
    {
        $this->container = new Container();
    }

    protected function tearDownContainer(): void
    {
        if ($this->container !== null) {
            $this->container->flush();
            $this->container = null;
        }
    }

    protected function container(): ContainerInterface
    {
        if ($this->container === null) {
            $this->setUpContainer();
        }

        return $this->container;
    }

    /**
     * @param class-string $abstract
     */
    protected function bind(string $abstract, mixed $concrete = null): void
    {
        $this->container()->bind($abstract, $concrete);
    }

    /**
     * @param class-string $abstract
     */
    protected function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container()->singleton($abstract, $concrete);
    }

    /**
     * @param class-string $abstract
     */
    protected function instance(string $abstract, mixed $instance): void
    {
        $this->container()->instance($abstract, $instance);
    }

    /**
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    protected function resolve(string $abstract): mixed
    {
        return $this->container()->resolve($abstract);
    }
}
