# Testing Guide

This document describes the testing infrastructure for the Lalaz Auth package and how to run and write tests.

## Test Suite Overview

The Auth package has comprehensive test coverage with **589 tests** and **1081 assertions**.

### Test Categories

| Category | Tests | Description |
|----------|-------|-------------|
| Unit Tests | ~434 | Test individual classes in isolation |
| Integration Tests | 155 | Test components working together |

### Integration Test Suites

| Suite | Tests | Coverage |
|-------|-------|----------|
| JwtFlowIntegrationTest | 24 | Complete JWT authentication flow |
| AuthManagerGuardsIntegrationTest | 32 | All guards integration with AuthManager |
| MiddlewareChainIntegrationTest | 22 | Authentication, Authorization, Permission middlewares |
| SessionAuthFlowIntegrationTest | 22 | Complete session authentication lifecycle |
| ProviderIntegrationTest | 30 | GenericUserProvider and ModelUserProvider |
| AuthServiceRegistrationIntegrationTest | 25 | Service assembly and wiring |

## Running Tests

### All Tests

```bash
cd packages/auth
./vendor/bin/phpunit
```

### Unit Tests Only

```bash
./vendor/bin/phpunit tests/Unit/
```

### Integration Tests Only

```bash
./vendor/bin/phpunit tests/Integration/
```

### Specific Test File

```bash
./vendor/bin/phpunit tests/Integration/JwtFlowIntegrationTest.php
```

### With Coverage Report

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Writing Tests

### Unit Test Example

```php
<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Guards\SessionGuard;

#[CoversClass(SessionGuard::class)]
class SessionGuardTest extends TestCase
{
    #[Test]
    public function it_authenticates_user_with_valid_credentials(): void
    {
        // Arrange
        $session = $this->createMock(SessionInterface::class);
        $provider = $this->createMock(UserProviderInterface::class);
        $guard = new SessionGuard($session, $provider);
        
        // Act
        $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
        
        // Assert
        $this->assertNotNull($result);
    }
}
```

### Integration Test Example

```php
<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Guards\SessionGuard;

#[Group('integration')]
class MyIntegrationTest extends TestCase
{
    #[Test]
    public function full_authentication_flow(): void
    {
        // Setup real components (not mocks)
        $manager = new AuthManager();
        $session = new FakeSession();
        $provider = new GenericUserProvider();
        
        // Configure
        $manager->extend('web', fn() => new SessionGuard($session, $provider));
        
        // Test the full flow
        $guard = $manager->guard('web');
        $user = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
        
        $this->assertNotNull($user);
        $this->assertTrue($guard->check());
    }
}
```

## Test Helpers

The test suite includes several helper classes for testing:

### FakeSession

A simple in-memory session implementation:

```php
class FakeSession implements SessionInterface
{
    private array $data = [];
    
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
    
    // ... other methods
}
```

### FakeUser

A simple authenticatable user implementation:

```php
class FakeUser implements AuthenticatableInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $password = '',
    ) {}
    
    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }
    
    public function getAuthPassword(): string
    {
        return $this->password;
    }
    
    // ... other methods
}
```

## Best Practices

1. **Use Attributes**: Use PHPUnit 11 attributes (`#[Test]`, `#[CoversClass]`) instead of annotations
2. **Descriptive Names**: Use snake_case test method names that describe the scenario
3. **Arrange-Act-Assert**: Structure tests with clear sections
4. **One Assertion Focus**: Each test should focus on one behavior
5. **Integration Tests**: Use real components, not mocks, for integration tests
6. **Group Tests**: Use `#[Group('integration')]` for integration tests

## Continuous Integration

Tests are run automatically on:
- Every push to `main` and `release/*` branches
- Every pull request

The CI pipeline runs:
1. All unit tests
2. All integration tests
3. Static analysis (PHPStan)
4. Code style checks (PHP CS Fixer)
