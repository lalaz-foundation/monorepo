<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Providers;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\ApiKeyProviderInterface;

/**
 * Tests for ModelUserProvider ORM fallback behavior.
 *
 * These tests verify that ModelUserProvider works correctly
 * with or without the ORM package installed.
 */
#[CoversClass(ModelUserProvider::class)]
final class ModelUserProviderTest extends AuthUnitTestCase
{
    #[Test]
    public function it_implements_all_required_interfaces(): void
    {
        $provider = new ModelUserProvider(MockUserModel::class);

        $this->assertInstanceOf(UserProviderInterface::class, $provider);
        $this->assertInstanceOf(RememberTokenProviderInterface::class, $provider);
        $this->assertInstanceOf(ApiKeyProviderInterface::class, $provider);
    }

    #[Test]
    public function it_can_get_and_set_model(): void
    {
        $provider = new ModelUserProvider(MockUserModel::class);

        $this->assertSame(MockUserModel::class, $provider->getModel());

        $provider->setModel(AnotherMockModel::class);
        $this->assertSame(AnotherMockModel::class, $provider->getModel());
    }

    #[Test]
    public function it_can_chain_set_model(): void
    {
        $provider = new ModelUserProvider(MockUserModel::class);

        $result = $provider->setModel(AnotherMockModel::class);

        $this->assertSame($provider, $result);
    }

    #[Test]
    public function it_retrieves_by_id_using_find_method(): void
    {
        MockUserModel::reset();
        MockUserModel::$findResult = new MockUserModel();

        $provider = new ModelUserProvider(MockUserModel::class);
        $result = $provider->retrieveById(123);

        $this->assertInstanceOf(MockUserModel::class, $result);
        $this->assertSame(123, MockUserModel::$lastFindId);
    }

    #[Test]
    public function it_retrieves_by_id_using_find_by_id_fallback(): void
    {
        MockModelWithFindById::reset();
        MockModelWithFindById::$findByIdResult = new MockModelWithFindById();

        $provider = new ModelUserProvider(MockModelWithFindById::class);
        $result = $provider->retrieveById(456);

        $this->assertInstanceOf(MockModelWithFindById::class, $result);
        $this->assertSame(456, MockModelWithFindById::$lastFindByIdArg);
    }

    #[Test]
    public function it_returns_null_when_user_not_found_by_id(): void
    {
        MockUserModel::reset();
        MockUserModel::$findResult = null;

        $provider = new ModelUserProvider(MockUserModel::class);
        $result = $provider->retrieveById(999);

        $this->assertNull($result);
    }

    #[Test]
    public function it_retrieves_by_credentials_excluding_password(): void
    {
        MockUserModelWithFindOneBy::reset();
        MockUserModelWithFindOneBy::$findOneByResult = new MockUserModelWithFindOneBy();

        $provider = new ModelUserProvider(MockUserModelWithFindOneBy::class);
        $result = $provider->retrieveByCredentials([
            'email' => 'user@example.com',
            'password' => 'secret123',
            'active' => true,
        ]);

        $this->assertInstanceOf(MockUserModelWithFindOneBy::class, $result);

        // Password should be excluded from the query
        $this->assertArrayNotHasKey('password', MockUserModelWithFindOneBy::$lastFindOneByConditions);
        $this->assertArrayHasKey('email', MockUserModelWithFindOneBy::$lastFindOneByConditions);
        $this->assertArrayHasKey('active', MockUserModelWithFindOneBy::$lastFindOneByConditions);
    }

    #[Test]
    public function it_returns_null_for_empty_credentials(): void
    {
        $provider = new ModelUserProvider(MockUserModel::class);
        $result = $provider->retrieveByCredentials([]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_password_only_credentials(): void
    {
        $provider = new ModelUserProvider(MockUserModel::class);
        $result = $provider->retrieveByCredentials([
            'password' => 'secret123',
        ]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_validates_credentials_with_password_verify(): void
    {
        $hashedPassword = password_hash('correct-password', PASSWORD_DEFAULT);
        $user = new MockUserWithPassword($hashedPassword);

        $provider = new ModelUserProvider(MockUserModel::class);

        $this->assertTrue($provider->validateCredentials($user, ['password' => 'correct-password']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong-password']));
    }

    #[Test]
    public function it_returns_false_when_no_password_in_credentials(): void
    {
        $user = new MockUserWithPassword('hashed');

        $provider = new ModelUserProvider(MockUserModel::class);

        $this->assertFalse($provider->validateCredentials($user, ['email' => 'test@example.com']));
    }

    #[Test]
    public function it_returns_false_when_user_has_no_get_auth_password(): void
    {
        $user = new \stdClass();

        $provider = new ModelUserProvider(MockUserModel::class);

        $this->assertFalse($provider->validateCredentials($user, ['password' => 'secret']));
    }

    #[Test]
    public function it_retrieves_by_token(): void
    {
        MockUserModelWithFindOneBy::reset();
        MockUserModelWithFindOneBy::$findOneByResult = new MockUserModelWithFindOneBy();

        $provider = new ModelUserProvider(MockUserModelWithFindOneBy::class);
        $result = $provider->retrieveByToken(123, 'remember-me-token');

        $this->assertInstanceOf(MockUserModelWithFindOneBy::class, $result);
        $this->assertSame([
            'id' => 123,
            'remember_token' => 'remember-me-token',
        ], MockUserModelWithFindOneBy::$lastFindOneByConditions);
    }

    #[Test]
    public function it_retrieves_by_api_key_with_hashed_key(): void
    {
        MockUserModelWithFindOneBy::reset();
        MockUserModelWithFindOneBy::$findOneByResult = new MockUserModelWithFindOneBy();

        $provider = new ModelUserProvider(MockUserModelWithFindOneBy::class);
        $result = $provider->retrieveByApiKey('my-api-key');

        $this->assertInstanceOf(MockUserModelWithFindOneBy::class, $result);

        $expectedHashedKey = hash('sha256', 'my-api-key');
        $this->assertSame([
            'api_key_hash' => $expectedHashedKey,
            'api_key_active' => true,
        ], MockUserModelWithFindOneBy::$lastFindOneByConditions);
    }

    #[Test]
    public function it_updates_remember_token(): void
    {
        $user = new MockSavableUser();

        $provider = new ModelUserProvider(MockUserModel::class);
        $provider->updateRememberToken($user, 'new-token');

        $this->assertSame('new-token', $user->remember_token);
        $this->assertTrue($user->saved);
    }

    #[Test]
    public function it_handles_user_without_save_method(): void
    {
        $user = new \stdClass();

        $provider = new ModelUserProvider(MockUserModel::class);
        $provider->updateRememberToken($user, 'new-token');

        // Should not throw, just set the property
        $this->assertSame('new-token', $user->remember_token);
    }

    #[Test]
    public function it_uses_find_by_fallback_when_find_one_by_not_available(): void
    {
        MockUserModelWithFindBy::reset();
        MockUserModelWithFindBy::$findByResult = [new MockUserModelWithFindBy()];

        $provider = new ModelUserProvider(MockUserModelWithFindBy::class);
        $result = $provider->retrieveByCredentials(['email' => 'test@example.com']);

        $this->assertInstanceOf(MockUserModelWithFindBy::class, $result);
        $this->assertSame(['email' => 'test@example.com'], MockUserModelWithFindBy::$lastFindByConditions);
    }

    #[Test]
    public function it_returns_null_when_find_by_returns_empty_array(): void
    {
        MockUserModelWithFindBy::reset();
        MockUserModelWithFindBy::$findByResult = [];

        $provider = new ModelUserProvider(MockUserModelWithFindBy::class);
        $result = $provider->retrieveByCredentials(['email' => 'nonexistent@example.com']);

        $this->assertNull($result);
    }
}

// ===== Mock Classes =====

/**
 * Mock model with find() method.
 */
class MockUserModel
{
    public static ?object $findResult = null;
    public static mixed $lastFindId = null;

    public static function reset(): void
    {
        self::$findResult = null;
        self::$lastFindId = null;
    }

    public static function find(mixed $id): ?object
    {
        self::$lastFindId = $id;
        return self::$findResult;
    }
}

/**
 * Mock model with findById() but no find().
 */
class MockModelWithFindById
{
    public static ?object $findByIdResult = null;
    public static mixed $lastFindByIdArg = null;

    public static function reset(): void
    {
        self::$findByIdResult = null;
        self::$lastFindByIdArg = null;
    }

    public static function findById(mixed $id): ?object
    {
        self::$lastFindByIdArg = $id;
        return self::$findByIdResult;
    }
}

/**
 * Mock model with findOneBy() method.
 */
class MockUserModelWithFindOneBy
{
    public static ?object $findOneByResult = null;
    public static ?array $lastFindOneByConditions = null;

    public static function reset(): void
    {
        self::$findOneByResult = null;
        self::$lastFindOneByConditions = null;
    }

    public static function findOneBy(array $conditions): ?object
    {
        self::$lastFindOneByConditions = $conditions;
        return self::$findOneByResult;
    }
}

/**
 * Mock model with findBy() method (returns array).
 */
class MockUserModelWithFindBy
{
    public static array $findByResult = [];
    public static ?array $lastFindByConditions = null;

    public static function reset(): void
    {
        self::$findByResult = [];
        self::$lastFindByConditions = null;
    }

    public static function findBy(array $conditions): array
    {
        self::$lastFindByConditions = $conditions;
        return self::$findByResult;
    }
}

/**
 * Another mock model for setModel test.
 */
class AnotherMockModel
{
}

/**
 * Mock user with getAuthPassword().
 */
class MockUserWithPassword
{
    private string $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }
}

/**
 * Mock user with save() method.
 */
class MockSavableUser
{
    public ?string $remember_token = null;
    public bool $saved = false;

    public function save(): void
    {
        $this->saved = true;
    }
}
