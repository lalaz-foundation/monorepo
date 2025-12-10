# Lalaz Testing

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Testing utilities and mini-runtime for Lalaz Framework packages. Provides base test cases for unit, integration, and end-to-end testing.

## Installation

```bash
composer require --dev lalaz/testing
```

## Quick Start

### Unit Tests

Use `UnitTestCase` for testing individual classes in isolation without any framework bootstrapping:

```php
<?php

use Lalaz\Testing\Unit\UnitTestCase;

class CalculatorTest extends UnitTestCase
{
    public function testAddition(): void
    {
        $calculator = new Calculator();
        $this->assertSame(30, $calculator->add(10, 20));
    }

    public function testPrivateMethod(): void
    {
        $calculator = new Calculator();
        
        // Access private methods
        $result = $this->invokeMethod($calculator, 'internalCalculation', [5]);
        $this->assertSame(25, $result);
    }

    public function testPrivateProperty(): void
    {
        $calculator = new Calculator();
        
        // Get private property
        $precision = $this->getProperty($calculator, 'precision');
        $this->assertSame(2, $precision);
        
        // Set private property
        $this->setProperty($calculator, 'precision', 4);
        $this->assertSame(4, $this->getProperty($calculator, 'precision'));
    }
}
```

### Integration Tests

Use `IntegrationTestCase` when testing how multiple classes work together with the DI container:

```php
<?php

use Lalaz\Testing\Integration\IntegrationTestCase;
use Lalaz\Auth\AuthServiceProvider;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Contracts\AuthContextInterface;

class AuthManagerIntegrationTest extends IntegrationTestCase
{
    /**
     * Register service providers for the test.
     */
    protected function getPackageProviders(): array
    {
        return [
            AuthServiceProvider::class,
        ];
    }

    /**
     * Provide configuration for the test.
     */
    protected function getPackageConfig(): array
    {
        return [
            'auth.default_guard' => 'token',
            'auth.guards' => [
                'token' => [
                    'driver' => 'token',
                    'provider' => 'users',
                ],
            ],
        ];
    }

    public function testAuthManagerResolvesFromContainer(): void
    {
        $manager = $this->resolve(AuthManager::class);
        
        $this->assertInstanceOf(AuthManager::class, $manager);
    }

    public function testAuthContextIsBound(): void
    {
        $this->assertBound(AuthContextInterface::class);
    }

    public function testResolvesToExpectedType(): void
    {
        $this->assertResolves(AuthManager::class, AuthContextInterface::class);
    }

    public function testCanMockServices(): void
    {
        // Create a mock
        $mockContext = $this->createMock(AuthContextInterface::class);
        $mockContext->method('check')->willReturn(true);
        
        // Override the service
        $this->mock(AuthContextInterface::class, $mockContext);
        
        // Now the container returns our mock
        $context = $this->resolve(AuthContextInterface::class);
        $this->assertTrue($context->check());
    }
}
```

### End-to-End Tests

Use `E2ETestCase` for testing complete HTTP request/response cycles:

```php
<?php

use Lalaz\Testing\E2E\E2ETestCase;
use App\Providers\AppServiceProvider;

class UserRegistrationE2ETest extends E2ETestCase
{
    protected function getPackageProviders(): array
    {
        return [
            AppServiceProvider::class,
        ];
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->get('/health');
        
        $this->assertResponseOk($response);
    }

    public function testUserCanRegister(): void
    {
        $response = $this->post('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $this->assertResponseStatus($response, 201);
    }

    public function testJsonApiEndpoint(): void
    {
        $response = $this->json('GET', '/api/users');
        
        $this->assertResponseOk($response);
        $this->assertResponseJson($response);
    }

    public function testAuthenticatedRequest(): void
    {
        $response = $this->withToken('my-api-token')
            ->get('/api/profile');
        
        $this->assertResponseOk($response);
    }

    public function testWithSession(): void
    {
        $response = $this->withSession(['user_id' => 123])
            ->get('/dashboard');
        
        $this->assertResponseOk($response);
    }
}
```

## Test Case Hierarchy

```
PHPUnit\Framework\TestCase
         │
         ▼
    UnitTestCase              ← For isolated unit tests
         │
         ▼
 IntegrationTestCase          ← For container/provider tests
         │
         ▼
    E2ETestCase               ← For HTTP request/response tests
```

## Available Methods

### UnitTestCase

| Method | Description |
|--------|-------------|
| `invokeMethod($object, $method, $params)` | Invoke private/protected methods |
| `getProperty($object, $property)` | Get private/protected property value |
| `setProperty($object, $property, $value)` | Set private/protected property value |
| `createConfiguredMock($class, $methods)` | Create mock with pre-configured methods |
| `assertUsesTrait($trait, $class)` | Assert class uses a trait |
| `assertImplementsInterface($interface, $class)` | Assert class implements interface |
| `assertHasMethod($method, $class)` | Assert class has a method |
| `assertHasProperty($property, $class)` | Assert class has a property |

### IntegrationTestCase

Inherits all `UnitTestCase` methods, plus:

| Method | Description |
|--------|-------------|
| `app()` | Get the TestApplication instance |
| `resolve($abstract, $params)` | Resolve a service from container |
| `bound($abstract)` | Check if service is bound |
| `mock($abstract, $mock)` | Override service with mock |
| `instance($abstract, $instance)` | Register instance in container |
| `bind($abstract, $concrete)` | Bind service in container |
| `singleton($abstract, $concrete)` | Bind singleton in container |
| `assertBound($abstract)` | Assert service is bound |
| `assertNotBound($abstract)` | Assert service is NOT bound |
| `assertResolves($expected, $abstract)` | Assert service resolves to type |
| `refreshApplication()` | Destroy and recreate application |

**Hooks:**
- `getPackageProviders()` - Override to register providers
- `getPackageConfig()` - Override to provide configuration
- `beforeApplicationBoot()` - Called before providers boot
- `afterApplicationBoot()` - Called after providers boot

### E2ETestCase

Inherits all `IntegrationTestCase` methods, plus:

| Method | Description |
|--------|-------------|
| `get($uri, $query, $headers)` | Simulate GET request |
| `post($uri, $data, $headers)` | Simulate POST request |
| `put($uri, $data, $headers)` | Simulate PUT request |
| `patch($uri, $data, $headers)` | Simulate PATCH request |
| `delete($uri, $data, $headers)` | Simulate DELETE request |
| `json($method, $uri, $data, $headers)` | Simulate JSON request |
| `withSession($data)` | Set session data |
| `withCookies($cookies)` | Set cookies |
| `withHeaders($headers)` | Set headers |
| `withToken($token)` | Set Bearer token |

**Response Assertions:**

| Method | Description |
|--------|-------------|
| `assertResponseStatus($response, $status)` | Assert specific status code |
| `assertResponseOk($response)` | Assert 2xx response |
| `assertResponseRedirects($response, $uri)` | Assert redirect response |
| `assertResponseJson($response, $data)` | Assert JSON response |

### TestResponse

| Method | Description |
|--------|-------------|
| `statusCode()` | Get HTTP status code |
| `headers()` | Get all headers |
| `header($name)` | Get specific header |
| `body()` | Get response body |
| `json()` | Get body as decoded JSON |
| `isSuccessful()` | Check if 2xx |
| `isRedirect()` | Check if 3xx |
| `isClientError()` | Check if 4xx |
| `isServerError()` | Check if 5xx |
| `isJson()` | Check if JSON content type |
| `isOk()` | Check if 200 |
| `isCreated()` | Check if 201 |
| `isNotFound()` | Check if 404 |
| `isUnauthorized()` | Check if 401 |
| `isForbidden()` | Check if 403 |

## TestApplication

The `TestApplication` class provides a mini-runtime for integration tests:

```php
use Lalaz\Testing\Integration\TestApplication;

// Create manually (usually done automatically by IntegrationTestCase)
$app = TestApplication::create(
    providers: [MyServiceProvider::class],
    config: ['key' => 'value'],
    beforeBoot: fn($app) => $app->bind('foo', 'bar'),
    afterBoot: fn($app) => doSomething()
);

// Resolve services
$service = $app->resolve(MyService::class);

// Check bindings
$app->bound(MyService::class); // true

// Mock services
$app->mock(SomeInterface::class, $mockInstance);

// Clean up
$app->flush();

// Or use static destroy
TestApplication::destroy();
```

**Features:**
- Automatically detects if full Lalaz framework is available
- Falls back to simple container when framework is not installed
- Integrates with `Application::setInstance()` for helper functions
- Automatic cleanup between tests

## Directory Structure

Recommended test directory structure for your package:

```
your-package/
├── src/
├── tests/
│   ├── Unit/           ← UnitTestCase tests
│   │   └── MyClassTest.php
│   ├── Integration/    ← IntegrationTestCase tests
│   │   └── MyServiceIntegrationTest.php
│   └── E2E/            ← E2ETestCase tests (if applicable)
│       └── MyFeatureE2ETest.php
└── phpunit.xml
```

**phpunit.xml configuration:**

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="E2E">
        <directory>tests/E2E</directory>
    </testsuite>
</testsuites>
```

Run specific test suites:

```bash
# Run only unit tests
./vendor/bin/phpunit --testsuite=Unit

# Run only integration tests
./vendor/bin/phpunit --testsuite=Integration

# Run all tests
./vendor/bin/phpunit
```

## Requirements

- PHP 8.3+
- PHPUnit 11.0+ or 12.0+

**Optional:**
- `lalaz/framework` - Enables full integration features (DI container, service providers)

> **Note:** This package works standalone without the framework. When `lalaz/framework` is not installed, `IntegrationTestCase` uses a simple array-based container, allowing you to test the framework itself or other standalone packages.

## License

MIT License. See [LICENSE](LICENSE) for details.
