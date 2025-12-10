<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Providers;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\TestAuthenticatableUser;

#[CoversClass(\Lalaz\Auth\Providers\GenericUserProvider::class)]
final class GenericUserProviderTest extends AuthUnitTestCase
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
    // Interface Implementation
    // =========================================================================

    public function testImplementsUserProviderInterface(): void
    {
        $provider = new GenericUserProvider();

        $this->assertInstanceOf(UserProviderInterface::class, $provider);
    }

    public function testImplementsRememberTokenProviderInterface(): void
    {
        $provider = new GenericUserProvider();

        $this->assertInstanceOf(RememberTokenProviderInterface::class, $provider);
    }

    public function testImplementsApiKeyProviderInterface(): void
    {
        $provider = new GenericUserProvider();

        $this->assertInstanceOf(ApiKeyProviderInterface::class, $provider);
    }

    // =========================================================================
    // RetrieveById
    // =========================================================================

    public function testRetrievesByIdReturnsNullWithoutCallback(): void
    {
        $provider = new GenericUserProvider();

        $found = $provider->retrieveById(123);

        $this->assertNull($found);
    }

    public function testRetrievesByIdUsesCallback(): void
    {
        $user = new TestAuthenticatableUser(123, 'john@example.com', 'secret');
        TestAuthenticatableUser::addUser($user);

        $provider = new GenericUserProvider();
        $provider->setByIdCallback(function ($id) {
            return TestAuthenticatableUser::findOneBy(['id' => $id]);
        });

        $found = $provider->retrieveById(123);

        $this->assertSame($user, $found);
    }

    public function testRetrievesByIdReturnsNullWhenUserNotFound(): void
    {
        $provider = new GenericUserProvider();
        $provider->setByIdCallback(function ($id) {
            return TestAuthenticatableUser::findOneBy(['id' => $id]);
        });

        $found = $provider->retrieveById(999);

        $this->assertNull($found);
    }

    // =========================================================================
    // RetrieveByCredentials
    // =========================================================================

    public function testRetrievesByCredentialsReturnsNullWithoutCallback(): void
    {
        $provider = new GenericUserProvider();

        $found = $provider->retrieveByCredentials(['email' => 'john@example.com']);

        $this->assertNull($found);
    }

    public function testRetrievesByCredentialsUsesCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');
        TestAuthenticatableUser::addUser($user);

        $provider = new GenericUserProvider();
        $provider->setByCredentialsCallback(function (array $credentials) {
            return TestAuthenticatableUser::findOneBy(['email' => $credentials['email'] ?? null]);
        });

        $found = $provider->retrieveByCredentials(['email' => 'john@example.com']);

        $this->assertSame($user, $found);
    }

    public function testRetrievesByCredentialsReturnsNullWhenUserNotFound(): void
    {
        $provider = new GenericUserProvider();
        $provider->setByCredentialsCallback(function (array $credentials) {
            return TestAuthenticatableUser::findOneBy(['email' => $credentials['email'] ?? null]);
        });

        $found = $provider->retrieveByCredentials(['email' => 'nonexistent@example.com']);

        $this->assertNull($found);
    }

    // =========================================================================
    // ValidateCredentials
    // =========================================================================

    public function testValidatesCredentialsWithDefaultBehavior(): void
    {
        $hashedPassword = password_hash('secret', PASSWORD_DEFAULT);
        $user = new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword);

        $provider = new GenericUserProvider();

        $result = $provider->validateCredentials($user, ['password' => 'secret']);

        $this->assertTrue($result);
    }

    public function testValidatesCredentialsWithIncorrectPassword(): void
    {
        $hashedPassword = password_hash('secret', PASSWORD_DEFAULT);
        $user = new TestAuthenticatableUser(1, 'john@example.com', $hashedPassword);

        $provider = new GenericUserProvider();

        $result = $provider->validateCredentials($user, ['password' => 'wrong']);

        $this->assertFalse($result);
    }

    public function testValidatesCredentialsWithMissingPassword(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'hashed');

        $provider = new GenericUserProvider();

        $result = $provider->validateCredentials($user, []);

        $this->assertFalse($result);
    }

    public function testValidatesCredentialsWithCustomCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $provider = new GenericUserProvider();
        $provider->setValidateCallback(function ($user, array $credentials) {
            return ($credentials['password'] ?? '') === 'secret';
        });

        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
    }

    // =========================================================================
    // RetrieveByToken
    // =========================================================================

    public function testRetrievesByTokenReturnsNullWithoutCallback(): void
    {
        $provider = new GenericUserProvider();

        $found = $provider->retrieveByToken(1, 'some-token');

        $this->assertNull($found);
    }

    public function testRetrievesByTokenUsesCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');
        $user->setRememberToken('valid-token');

        $provider = new GenericUserProvider();
        $provider->setByTokenCallback(function ($identifier, $token) use ($user) {
            if ($user->id === $identifier && $user->getRememberToken() === $token) {
                return $user;
            }
            return null;
        });

        $found = $provider->retrieveByToken(1, 'valid-token');

        $this->assertSame($user, $found);
    }

    // =========================================================================
    // UpdateRememberToken
    // =========================================================================

    public function testUpdateRememberTokenDoesNothingWithoutCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $provider = new GenericUserProvider();

        // Should not throw an exception
        $provider->updateRememberToken($user, 'new-token');

        $this->assertTrue(true);
    }

    public function testUpdateRememberTokenUsesCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');
        $tokenUpdated = false;

        $provider = new GenericUserProvider();
        $provider->setUpdateTokenCallback(function ($u, $token) use (&$tokenUpdated, $user) {
            $user->setRememberToken($token);
            $tokenUpdated = true;
        });

        $provider->updateRememberToken($user, 'new-token');

        $this->assertTrue($tokenUpdated);
        $this->assertSame('new-token', $user->getRememberToken());
    }

    // =========================================================================
    // RetrieveByApiKey
    // =========================================================================

    public function testRetrievesByApiKeyReturnsNullWithoutCallback(): void
    {
        $provider = new GenericUserProvider();

        $found = $provider->retrieveByApiKey('api-key-123');

        $this->assertNull($found);
    }

    public function testRetrievesByApiKeyUsesCallback(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');

        $provider = new GenericUserProvider();
        $provider->setByApiKeyCallback(function ($apiKey) use ($user) {
            if ($apiKey === 'valid-api-key') {
                return $user;
            }
            return null;
        });

        $found = $provider->retrieveByApiKey('valid-api-key');

        $this->assertSame($user, $found);
    }

    // =========================================================================
    // Fluent Interface
    // =========================================================================

    public function testSetByIdCallbackReturnsChainableSelf(): void
    {
        $provider = new GenericUserProvider();

        $result = $provider->setByIdCallback(fn($id) => null);

        $this->assertSame($provider, $result);
    }

    public function testSetByCredentialsCallbackReturnsChainableSelf(): void
    {
        $provider = new GenericUserProvider();

        $result = $provider->setByCredentialsCallback(fn($creds) => null);

        $this->assertSame($provider, $result);
    }

    public function testSetValidateCallbackReturnsChainableSelf(): void
    {
        $provider = new GenericUserProvider();

        $result = $provider->setValidateCallback(fn($user, $creds) => true);

        $this->assertSame($provider, $result);
    }

    // =========================================================================
    // Factory Method
    // =========================================================================

    public function testCreateFactoryCreatesConfiguredProvider(): void
    {
        $user = new TestAuthenticatableUser(1, 'john@example.com', 'secret');
        TestAuthenticatableUser::addUser($user);

        $provider = GenericUserProvider::create(
            byId: fn($id) => TestAuthenticatableUser::findOneBy(['id' => $id]),
            byCredentials: fn($creds) => TestAuthenticatableUser::findOneBy(['email' => $creds['email'] ?? null])
        );

        $this->assertSame($user, $provider->retrieveById(1));
        $this->assertSame($user, $provider->retrieveByCredentials(['email' => 'john@example.com']));
    }
}
