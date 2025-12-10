<?php

declare(strict_types=1);

namespace Lalaz\Database\Seeding;

use Lalaz\Container\Contracts\ContainerInterface;

abstract class Seeder
{
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    abstract public function run(): void;

    /**
     * Call other seeders.
     *
     * @param array<int, class-string<Seeder>> $seeders
     */
    protected function call(array $seeders): void
    {
        foreach ($seeders as $seeder) {
            $this->resolve($seeder)->run();
        }
    }

    protected function resolve(string $seeder): Seeder
    {
        if ($this->container !== null && $this->container->bound($seeder)) {
            return $this->container->resolve($seeder);
        }

        return new $seeder($this->container);
    }
}
