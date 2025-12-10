<?php

declare(strict_types=1);

namespace Lalaz\Testing\Integration;

use Lalaz\Testing\Traits\InteractsWithContainer;
use Lalaz\Testing\Unit\UnitTestCase;

/**
 * Base TestCase for Integration Tests
 *
 * Provides a mini-runtime with a DI container for testing how
 * multiple classes work together. Service providers can be
 * registered to bootstrap the container with real bindings.
 *
 * Features:
 * - Automatic container bootstrapping
 * - Service provider registration
 * - Service mocking/overriding
 * - Automatic cleanup between tests
 *
 * @example
 * ```php
 * class AuthManagerIntegrationTest extends IntegrationTestCase
 * {
 *     protected function getPackageProviders(): array
 *     {
 *         return [AuthServiceProvider::class];
 *     }
 *
 *     protected function getPackageConfig(): array
 *     {
 *         return ['auth.default_guard' => 'token'];
 *     }
 *
 *     public function testAuthManagerResolves(): void
 *     {
 *         $manager = $this->resolve(AuthManager::class);
 *         $this->assertInstanceOf(AuthManager::class, $manager);
 *     }
 *
 *     public function testWithMockedDependency(): void
 *     {
 *         $mock = $this->createMock(SomeInterface::class);
 *         $this->mock(SomeInterface::class, $mock);
 *
 *         $service = $this->resolve(ServiceThatDependsOnInterface::class);
 *         // Service now uses the mock
 *     }
 * }
 * ```
 *
 * @package lalaz/testing
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
abstract class IntegrationTestCase extends UnitTestCase
{
    use InteractsWithContainer;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->destroyApplication();
        parent::tearDown();
    }

    /**
     * Get the service providers to register.
     *
     * Override this method to specify which service providers
     * should be registered for your integration tests.
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
     * Override this method to provide configuration values
     * that will be available during the test.
     *
     * @return array<string, mixed>
     */
    protected function getPackageConfig(): array
    {
        return [];
    }

    /**
     * Perform any additional bootstrapping before the application boots.
     *
     * Override this method to add custom bindings or configuration
     * before the service providers are booted.
     *
     * @return void
     */
    protected function beforeApplicationBoot(): void
    {
        // Override in subclasses
    }

    /**
     * Perform any additional setup after the application boots.
     *
     * Override this method to perform additional setup after
     * all service providers have been registered and booted.
     *
     * @return void
     */
    protected function afterApplicationBoot(): void
    {
        // Override in subclasses
    }
}
