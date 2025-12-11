<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionClass;

/**
 * Base test case for Framework package unit tests.
 *
 * Provides utilities for testing individual classes in isolation,
 * without any framework bootstrapping. Use this when testing pure
 * logic that doesn't depend on the container or other services.
 *
 * Features:
 * - Access to private/protected methods and properties
 * - Mock creation helpers
 * - Common assertions
 * - Setup/teardown hooks for traits
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
abstract class FrameworkUnitTestCase extends PHPUnitTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getSetUpMethods() as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->getTearDownMethods()) as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        parent::tearDown();
    }

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpConfig',
            'setUpContainer',
            'setUpHttp',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownConfig',
            'tearDownContainer',
            'tearDownHttp',
            'tearDownTempFiles',
        ];
    }

    // =========================================================================
    // Reflection Helpers
    // =========================================================================

    /**
     * Invoke a private or protected method on an object.
     *
     * @param object $object The object instance
     * @param string $methodName The method name to invoke
     * @param array<int, mixed> $parameters Parameters to pass to the method
     * @return mixed The method's return value
     */
    protected function invokeMethod(
        object $object,
        string $methodName,
        array $parameters = []
    ): mixed {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Get the value of a private or protected property.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name
     * @return mixed The property value
     */
    protected function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Set the value of a private or protected property.
     *
     * @param object $object The object instance
     * @param string $propertyName The property name
     * @param mixed $value The value to set
     * @return void
     */
    protected function setProperty(
        object $object,
        string $propertyName,
        mixed $value
    ): void {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    // =========================================================================
    // Mock Helpers
    // =========================================================================

    /**
     * Create a mock with specific methods configured.
     *
     * @template T of object
     * @param class-string<T> $className The class to mock
     * @param array<string, mixed> $methods Map of method name to return value
     * @return T&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createMockWithMethods(string $className, array $methods): object
    {
        $mock = $this->createMock($className);

        foreach ($methods as $method => $returnValue) {
            $mock->method($method)->willReturn($returnValue);
        }

        return $mock;
    }

    // =========================================================================
    // Custom Assertions
    // =========================================================================

    /**
     * Assert that a string contains all given substrings.
     *
     * @param string $haystack
     * @param array<int, string> $needles
     */
    protected function assertStringContainsAll(string $haystack, array $needles): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $haystack);
        }
    }

    /**
     * Assert that an array has all the given keys.
     *
     * @param array<int, string> $keys
     * @param array<string, mixed> $array
     */
    protected function assertArrayHasKeys(array $keys, array $array): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    /**
     * Assert that a class uses a specific trait.
     *
     * @param string $traitName The fully qualified trait name
     * @param object|string $classOrObject The class or object to check
     * @return void
     */
    protected function assertUsesTrait(string $traitName, object|string $classOrObject): void
    {
        $traits = $this->classUsesRecursive(
            is_object($classOrObject) ? $classOrObject::class : $classOrObject
        );

        $this->assertContains(
            $traitName,
            $traits,
            sprintf('Failed asserting that class uses trait %s', $traitName)
        );
    }

    /**
     * Assert that a class implements a specific interface.
     *
     * @param string $interfaceName The fully qualified interface name
     * @param object|string $classOrObject The class or object to check
     * @return void
     */
    protected function assertImplementsInterface(
        string $interfaceName,
        object|string $classOrObject
    ): void {
        $this->assertContains(
            $interfaceName,
            class_implements($classOrObject) ?: [],
            sprintf('Failed asserting that class implements %s', $interfaceName)
        );
    }

    /**
     * Assert that a class has a specific method.
     *
     * @param string $methodName The method name
     * @param object|string $classOrObject The class or object to check
     * @return void
     */
    protected function assertHasMethod(string $methodName, object|string $classOrObject): void
    {
        $this->assertTrue(
            method_exists($classOrObject, $methodName),
            sprintf('Failed asserting that class has method %s', $methodName)
        );
    }

    /**
     * Assert that a class has a specific property.
     *
     * @param string $propertyName The property name
     * @param object|string $classOrObject The class or object to check
     * @return void
     */
    protected function assertHasProperty(string $propertyName, object|string $classOrObject): void
    {
        $this->assertTrue(
            property_exists($classOrObject, $propertyName),
            sprintf('Failed asserting that class has property %s', $propertyName)
        );
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Get all traits used by a class, including traits used by parent classes and traits.
     *
     * @param object|string $class
     * @return array<string, string>
     */
    private function classUsesRecursive(object|string $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $currentClass) {
            $results += $this->traitUsesRecursive($currentClass);
        }

        return array_unique($results);
    }

    /**
     * Get all traits used by a trait and its traits.
     *
     * @param string $trait
     * @return array<string, string>
     */
    private function traitUsesRecursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $usedTrait) {
            $traits += $this->traitUsesRecursive($usedTrait);
        }

        return $traits;
    }
}
