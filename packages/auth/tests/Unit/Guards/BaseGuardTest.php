<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Guards;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Guards\BaseGuard;

class BaseGuardTest extends AuthUnitTestCase
{
    private TestableBaseGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new TestableBaseGuard();
    }

    // =========================================================================
    // getUserId() - Priority 1: AuthenticatableInterface
    // =========================================================================

    public function testGetUserIdWithAuthenticatableInterface(): void
    {
        $user = new class implements AuthenticatableInterface {
            public function getAuthIdentifier(): mixed { return 'auth-id-123'; }
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthPassword(): string { return 'hashed'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken(?string $value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        $this->assertEquals('auth-id-123', $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithAuthenticatableInterfaceReturnsNumericId(): void
    {
        $user = new class implements AuthenticatableInterface {
            public function getAuthIdentifier(): mixed { return 42; }
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthPassword(): string { return 'hashed'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken(?string $value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        $this->assertSame(42, $this->guard->publicGetUserId($user));
    }

    public function testAuthenticatableInterfaceHasPriorityOverGetIdMethod(): void
    {
        // Object that implements AuthenticatableInterface AND has getId()
        $user = new class implements AuthenticatableInterface {
            public function getAuthIdentifier(): mixed { return 'from-interface'; }
            public function getId(): mixed { return 'from-get-id'; }
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthPassword(): string { return 'hashed'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken(?string $value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        // Should return from interface, not from getId()
        $this->assertEquals('from-interface', $this->guard->publicGetUserId($user));
    }

    // =========================================================================
    // getUserId() - Priority 2: Arrays (JWT tokens)
    // =========================================================================

    public function testGetUserIdWithArrayIdKey(): void
    {
        $user = ['id' => 100, 'name' => 'Test'];

        $this->assertSame(100, $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithArraySubKey(): void
    {
        // JWT tokens commonly use 'sub' claim
        $user = ['sub' => 'jwt-subject-123', 'name' => 'Test'];

        $this->assertEquals('jwt-subject-123', $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithArrayUserIdKey(): void
    {
        // Some APIs use 'user_id'
        $user = ['user_id' => 'api-user-456', 'name' => 'Test'];

        $this->assertEquals('api-user-456', $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdArrayIdHasPriorityOverSub(): void
    {
        $user = ['id' => 'primary', 'sub' => 'secondary', 'user_id' => 'tertiary'];

        $this->assertEquals('primary', $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdArraySubHasPriorityOverUserId(): void
    {
        $user = ['sub' => 'secondary', 'user_id' => 'tertiary'];

        $this->assertEquals('secondary', $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithEmptyArray(): void
    {
        $this->assertNull($this->guard->publicGetUserId([]));
    }

    public function testGetUserIdWithArrayWithoutIdKeys(): void
    {
        $user = ['name' => 'Test', 'email' => 'test@example.com'];

        $this->assertNull($this->guard->publicGetUserId($user));
    }

    // =========================================================================
    // getUserId() - Priority 3: Generic Objects
    // =========================================================================

    public function testGetUserIdWithObjectGetIdMethod(): void
    {
        $user = new class {
            public function getId(): int { return 999; }
        };

        $this->assertSame(999, $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithObjectIdProperty(): void
    {
        $user = new class {
            public int $id = 777;
        };

        $this->assertSame(777, $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithObjectGetIdHasPriorityOverIdProperty(): void
    {
        $user = new class {
            public int $id = 111;
            public function getId(): int { return 222; }
        };

        // Should use getId() method over property
        $this->assertSame(222, $this->guard->publicGetUserId($user));
    }

    public function testGetUserIdWithObjectWithoutIdMethodOrProperty(): void
    {
        $user = new class {
            public string $name = 'Test';
        };

        $this->assertNull($this->guard->publicGetUserId($user));
    }

    // =========================================================================
    // getUserId() - Edge Cases
    // =========================================================================

    public function testGetUserIdWithNull(): void
    {
        $this->assertNull($this->guard->publicGetUserId(null));
    }

    public function testGetUserIdWithScalar(): void
    {
        $this->assertNull($this->guard->publicGetUserId(123));
        $this->assertNull($this->guard->publicGetUserId('string'));
        $this->assertNull($this->guard->publicGetUserId(true));
    }

    // =========================================================================
    // getUserAttribute()
    // =========================================================================

    public function testGetUserAttributeFromArray(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];

        $this->assertEquals('John', $this->guard->publicGetUserAttribute($user, 'name'));
        $this->assertEquals('john@example.com', $this->guard->publicGetUserAttribute($user, 'email'));
    }

    public function testGetUserAttributeFromArrayReturnsDefaultWhenMissing(): void
    {
        $user = ['id' => 1, 'name' => 'John'];

        $this->assertEquals('default', $this->guard->publicGetUserAttribute($user, 'missing', 'default'));
        $this->assertNull($this->guard->publicGetUserAttribute($user, 'missing'));
    }

    public function testGetUserAttributeFromObjectWithGetAttributeMethod(): void
    {
        $user = new class {
            public function getAttribute(string $key): mixed
            {
                return match($key) {
                    'name' => 'Jane',
                    'email' => 'jane@example.com',
                    default => null,
                };
            }
        };

        $this->assertEquals('Jane', $this->guard->publicGetUserAttribute($user, 'name'));
        $this->assertEquals('jane@example.com', $this->guard->publicGetUserAttribute($user, 'email'));
    }

    public function testGetUserAttributeFromObjectProperty(): void
    {
        $user = new class {
            public string $name = 'Bob';
            public string $email = 'bob@example.com';
        };

        $this->assertEquals('Bob', $this->guard->publicGetUserAttribute($user, 'name'));
        $this->assertEquals('bob@example.com', $this->guard->publicGetUserAttribute($user, 'email'));
    }

    public function testGetUserAttributeReturnsDefaultForNonObjectNonArray(): void
    {
        $this->assertEquals('default', $this->guard->publicGetUserAttribute('scalar', 'key', 'default'));
        $this->assertNull($this->guard->publicGetUserAttribute(123, 'key'));
    }

    // =========================================================================
    // check() and guest()
    // =========================================================================

    public function testCheckReturnsFalseWhenNoUser(): void
    {
        $this->assertFalse($this->guard->check());
    }

    public function testCheckReturnsTrueWhenUserIsSet(): void
    {
        $this->guard->setTestUser(['id' => 1, 'name' => 'Test']);
        $this->assertTrue($this->guard->check());
    }

    public function testGuestReturnsTrueWhenNoUser(): void
    {
        $this->assertTrue($this->guard->guest());
    }

    public function testGuestReturnsFalseWhenUserIsSet(): void
    {
        $this->guard->setTestUser(['id' => 1, 'name' => 'Test']);
        $this->assertFalse($this->guard->guest());
    }

    // =========================================================================
    // id()
    // =========================================================================

    public function testIdReturnsNullWhenNoUser(): void
    {
        $this->assertNull($this->guard->id());
    }

    public function testIdReturnsUserIdWhenUserIsSet(): void
    {
        $this->guard->setTestUser(['id' => 42, 'name' => 'Test']);
        $this->assertSame(42, $this->guard->id());
    }

    public function testIdReturnsAuthIdentifierForAuthenticatableUser(): void
    {
        $user = new class implements AuthenticatableInterface {
            public function getAuthIdentifier(): mixed { return 'uuid-123'; }
            public function getAuthIdentifierName(): string { return 'id'; }
            public function getAuthPassword(): string { return 'hashed'; }
            public function getRememberToken(): ?string { return null; }
            public function setRememberToken(?string $value): void {}
            public function getRememberTokenName(): string { return 'remember_token'; }
        };

        $this->guard->setTestUser($user);
        $this->assertEquals('uuid-123', $this->guard->id());
    }

    // =========================================================================
    // validate()
    // =========================================================================

    public function testValidateReturnsFalseWithNoProvider(): void
    {
        $this->assertFalse($this->guard->validate(['username' => 'test', 'password' => 'pass']));
    }

    public function testValidateReturnsTrueWhenProviderValidatesCredentials(): void
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $user = ['id' => 1, 'name' => 'Test'];

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['username' => 'test', 'password' => 'pass'])
            ->willReturn($user);

        $provider->expects($this->once())
            ->method('validateCredentials')
            ->with($user, ['username' => 'test', 'password' => 'pass'])
            ->willReturn(true);

        $this->guard->setProvider($provider);
        $this->assertTrue($this->guard->validate(['username' => 'test', 'password' => 'pass']));
    }

    public function testValidateReturnsFalseWhenUserNotFound(): void
    {
        $provider = $this->createMock(UserProviderInterface::class);

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->willReturn(null);

        $this->guard->setProvider($provider);
        $this->assertFalse($this->guard->validate(['username' => 'test', 'password' => 'pass']));
    }

    public function testValidateReturnsFalseWhenCredentialsInvalid(): void
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $user = ['id' => 1, 'name' => 'Test'];

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->willReturn($user);

        $provider->expects($this->once())
            ->method('validateCredentials')
            ->willReturn(false);

        $this->guard->setProvider($provider);
        $this->assertFalse($this->guard->validate(['username' => 'test', 'password' => 'wrong']));
    }
}

/**
 * Testable subclass to expose protected methods
 */
class TestableBaseGuard extends BaseGuard
{
    private string $name = 'test';

    public function getName(): string
    {
        return $this->name;
    }

    public function attempt(array $credentials): mixed
    {
        if ($this->validate($credentials) && $this->provider !== null) {
            $user = $this->provider->retrieveByCredentials($credentials);
            $this->login($user);
            return $user;
        }

        return null;
    }

    public function login(mixed $user): void
    {
        $this->user = $user;
    }

    public function user(): mixed
    {
        return $this->user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function setTestUser(mixed $user): void
    {
        $this->user = $user;
    }

    public function publicGetUserId(mixed $user): mixed
    {
        return $this->getUserId($user);
    }

    public function publicGetUserAttribute(mixed $user, string $key, mixed $default = null): mixed
    {
        return $this->getUserAttribute($user, $key, $default);
    }
}
