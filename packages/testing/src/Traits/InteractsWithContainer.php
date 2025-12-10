<?php

declare(strict_types=1);

namespace Lalaz\Testing\Traits;

use Lalaz\Testing\Integration\TestApplication;

/**
 * InteractsWithContainer - Trait for container interaction in tests
 *
 * Provides methods to create, manage, and interact with the
 * TestApplication container in integration tests.
 *
 * @package lalaz/testing
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
trait InteractsWithContainer
{
    /**
     * The test application instance.
     *
     * @var TestApplication|null
     */
    protected ?TestApplication $app = null;

    /**
     * Get the test application instance.
     *
     * @return TestApplication
     */
    protected function app(): TestApplication
    {
        if ($this->app === null) {
            $this->createApplication();
        }

        return $this->app;
    }

    /**
     * Create a fresh test application.
     *
     * @return TestApplication
     */
    protected function createApplication(): TestApplication
    {
        // Call hooks if they exist
        if (method_exists($this, 'beforeApplicationBoot')) {
            $beforeBoot = fn (TestApplication $app) => $this->beforeApplicationBoot();
        } else {
            $beforeBoot = null;
        }

        if (method_exists($this, 'afterApplicationBoot')) {
            $afterBoot = fn (TestApplication $app) => $this->afterApplicationBoot();
        } else {
            $afterBoot = null;
        }

        $this->app = TestApplication::create(
            providers: $this->getPackageProviders(),
            config: $this->getPackageConfig(),
            beforeBoot: $beforeBoot,
            afterBoot: $afterBoot
        );

        return $this->app;
    }

    /**
     * Destroy the test application and reset state.
     *
     * @return void
     */
    protected function destroyApplication(): void
    {
        if ($this->app !== null) {
            $this->app->flush();
            $this->app = null;
        }

        TestApplication::destroy();
    }

    /**
     * Refresh the application (destroy and recreate).
     *
     * @return TestApplication
     */
    protected function refreshApplication(): TestApplication
    {
        $this->destroyApplication();
        return $this->createApplication();
    }

    /**
     * Resolve a service from the container.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return T|mixed
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        return $this->app()->resolve($abstract, $parameters);
    }

    /**
     * Check if a service is bound in the container.
     *
     * @param string $abstract
     * @return bool
     */
    protected function bound(string $abstract): bool
    {
        return $this->app()->bound($abstract);
    }

    /**
     * Assert that a service is bound in the container.
     *
     * @param string $abstract
     * @param string $message
     * @return void
     */
    protected function assertBound(string $abstract, string $message = ''): void
    {
        $this->assertTrue(
            $this->bound($abstract),
            $message ?: "Failed asserting that [{$abstract}] is bound in the container."
        );
    }

    /**
     * Assert that a service is NOT bound in the container.
     *
     * @param string $abstract
     * @param string $message
     * @return void
     */
    protected function assertNotBound(string $abstract, string $message = ''): void
    {
        $this->assertFalse(
            $this->bound($abstract),
            $message ?: "Failed asserting that [{$abstract}] is not bound in the container."
        );
    }

    /**
     * Assert that a resolved service is an instance of a class.
     *
     * @param string $expected Expected class/interface
     * @param string $abstract Service to resolve
     * @param string $message
     * @return void
     */
    protected function assertResolves(string $expected, string $abstract, string $message = ''): void
    {
        $resolved = $this->resolve($abstract);

        $this->assertInstanceOf(
            $expected,
            $resolved,
            $message ?: "Failed asserting that [{$abstract}] resolves to an instance of [{$expected}]."
        );
    }

    /**
     * Override a service with a mock.
     *
     * @param string $abstract The service identifier
     * @param mixed $mock The mock or replacement
     * @return static
     */
    protected function mock(string $abstract, mixed $mock): static
    {
        $this->app()->mock($abstract, $mock);
        return $this;
    }

    /**
     * Register an instance in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return static
     */
    protected function instance(string $abstract, mixed $instance): static
    {
        $this->app()->instance($abstract, $instance);
        return $this;
    }

    /**
     * Bind a service in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return static
     */
    protected function bind(string $abstract, mixed $concrete = null): static
    {
        $this->app()->bind($abstract, $concrete);
        return $this;
    }

    /**
     * Bind a singleton in the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return static
     */
    protected function singleton(string $abstract, mixed $concrete = null): static
    {
        $this->app()->singleton($abstract, $concrete);
        return $this;
    }

    /**
     * Get the service providers to register.
     *
     * Override in test class to provide providers.
     *
     * @return array<class-string>
     */
    protected function getPackageProviders(): array
    {
        return [];
    }

    /**
     * Get the package configuration.
     *
     * Override in test class to provide configuration.
     *
     * @return array<string, mixed>
     */
    protected function getPackageConfig(): array
    {
        return [];
    }
}
