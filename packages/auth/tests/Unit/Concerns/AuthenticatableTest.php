<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\TestAuthenticatableUser;

#[CoversClass(\Lalaz\Auth\Concerns\Authenticatable::class)]
final class AuthenticatableTest extends AuthUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestAuthenticatableUser::clearUsers();
    }

    protected function tearDown(): void
    {
        TestAuthenticatableUser::clearUsers();
        parent::tearDown();
    }

    // =========================================================================
    // Identifier Methods
    // =========================================================================

    public function testReturnsAuthIdentifierName(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $this->assertSame('id', $user->getAuthIdentifierName());
    }

    public function testReturnsAuthIdentifier(): void
    {
        $user = new TestAuthenticatableUser(42, 'john@example.com', 'secret');

        $this->assertSame(42, $user->getAuthIdentifier());
    }

    // =========================================================================
    // Password Methods
    // =========================================================================

    public function testReturnsAuthPassword(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret123');

        $this->assertSame('secret123', $user->getAuthPassword());
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $hashedPassword = password_hash('correct-password', PASSWORD_DEFAULT);
        $user = new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword);

        $this->assertTrue($user->verifyPassword('correct-password'));
    }

    public function testVerifyPasswordReturnsFalseForIncorrectPassword(): void
    {
        $hashedPassword = password_hash('correct-password', PASSWORD_DEFAULT);
        $user = new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword);

        $this->assertFalse($user->verifyPassword('wrong-password'));
    }

    public function testVerifyPasswordReturnsFalseForEmptyStoredPassword(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', '');

        $this->assertFalse($user->verifyPassword('any-password'));
    }

    // =========================================================================
    // Remember Token
    // =========================================================================

    public function testGetRememberTokenName(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $this->assertSame('remember_token', $user->getRememberTokenName());
    }

    public function testGetRememberTokenReturnsNull(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $this->assertNull($user->getRememberToken());
    }

    public function testSetRememberToken(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');
        $token = 'some-remember-token';

        $user->setRememberToken($token);

        $this->assertSame($token, $user->getRememberToken());
    }

    // =========================================================================
    // FindOneBy Method
    // =========================================================================

    public function testFindOneByReturnsUserWhenFound(): void
    {
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', 'secret'));
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(2, 'jane@example.com', 'password'));

        $user = TestAuthenticatableUser::findOneBy(['email' => 'jane@example.com']);

        $this->assertNotNull($user);
        $this->assertSame(2, $user->id);
        $this->assertSame('jane@example.com', $user->email);
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', 'secret'));

        $user = TestAuthenticatableUser::findOneBy(['email' => 'nonexistent@example.com']);

        $this->assertNull($user);
    }

    public function testFindOneBySearchesByDifferentFields(): void
    {
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', 'secret'));
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(2, 'jane@example.com', 'password'));

        $user = TestAuthenticatableUser::findOneBy(['id' => 1]);

        $this->assertNotNull($user);
        $this->assertSame('john@example.com', $user->email);
    }

    // =========================================================================
    // Validate Credentials
    // =========================================================================

    public function testValidateCredentialsReturnsUserForValidCredentials(): void
    {
        $hashedPassword = password_hash('secret123', PASSWORD_DEFAULT);
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword));

        $user = TestAuthenticatableUser::validateCredentials('john@example.com', 'secret123');

        $this->assertNotNull($user);
        $this->assertSame(1, $user->id);
    }

    public function testValidateCredentialsReturnsNullForInvalidPassword(): void
    {
        $hashedPassword = password_hash('secret123', PASSWORD_DEFAULT);
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword));

        $user = TestAuthenticatableUser::validateCredentials('john@example.com', 'wrong-password');

        $this->assertNull($user);
    }

    public function testValidateCredentialsReturnsNullForNonexistentUser(): void
    {
        $user = TestAuthenticatableUser::validateCredentials('nonexistent@example.com', 'any-password');

        $this->assertNull($user);
    }

    // =========================================================================
    // Clear Users
    // =========================================================================

    public function testClearUsersRemovesAllUsers(): void
    {
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(1, 'john@example.com', 'secret'));
        TestAuthenticatableUser::addUser(new TestAuthenticatableUser(2, 'jane@example.com', 'password'));

        TestAuthenticatableUser::clearUsers();

        $this->assertNull(TestAuthenticatableUser::findOneBy(['id' => 1]));
        $this->assertNull(TestAuthenticatableUser::findOneBy(['id' => 2]));
    }
}
