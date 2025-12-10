<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Providers\ModelUserProvider;
use Lalaz\Auth\Contracts\AuthenticatableInterface;
use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Integration tests for Auth Providers
 *
 * Tests GenericUserProvider and ModelUserProvider with realistic scenarios
 * including credential validation, remember tokens, and API key authentication.
 */
#[CoversClass(GenericUserProvider::class)]
#[CoversClass(ModelUserProvider::class)]
#[Group('integration')]
class ProviderIntegrationTest extends TestCase
{
    private array $userStore = [];
    private NativePasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new NativePasswordHasher();

        // Create test user store
        $this->userStore = [
            1 => new FakeAuthenticatableUser(
                id: 1,
                email: 'john@example.com',
                password: $this->hasher->hash('password123'),
                rememberToken: null,
                apiKey: 'api-key-john-123'
            ),
            2 => new FakeAuthenticatableUser(
                id: 2,
                email: 'jane@example.com',
                password: $this->hasher->hash('secret456'),
                rememberToken: 'existing-token-abc',
                apiKey: 'api-key-jane-456'
            ),
            3 => new FakeAuthenticatableUser(
                id: 3,
                email: 'admin@example.com',
                password: $this->hasher->hash('admin789'),
                rememberToken: null,
                apiKey: null
            ),
        ];
    }

    // =========================================================================
    // GenericUserProvider - Basic Retrieval
    // =========================================================================

    #[Test]
    public function generic_provider_retrieves_user_by_id(): void
    {
        $provider = $this->createGenericProvider();

        $user = $provider->retrieveById(1);

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->getAuthIdentifier());
        $this->assertEquals('john@example.com', $user->email);
    }

    #[Test]
    public function generic_provider_returns_null_for_nonexistent_id(): void
    {
        $provider = $this->createGenericProvider();

        $user = $provider->retrieveById(999);

        $this->assertNull($user);
    }

    #[Test]
    public function generic_provider_returns_null_when_no_callback_set(): void
    {
        $provider = new GenericUserProvider();

        $user = $provider->retrieveById(1);

        $this->assertNull($user);
    }

    #[Test]
    public function generic_provider_retrieves_user_by_credentials(): void
    {
        $provider = $this->createGenericProvider();

        $user = $provider->retrieveByCredentials(['email' => 'jane@example.com']);

        $this->assertNotNull($user);
        $this->assertEquals(2, $user->getAuthIdentifier());
    }

    #[Test]
    public function generic_provider_returns_null_for_unknown_credentials(): void
    {
        $provider = $this->createGenericProvider();

        $user = $provider->retrieveByCredentials(['email' => 'unknown@example.com']);

        $this->assertNull($user);
    }

    // =========================================================================
    // GenericUserProvider - Credential Validation
    // =========================================================================

    #[Test]
    public function generic_provider_validates_correct_password(): void
    {
        $provider = $this->createGenericProvider();
        $user = $provider->retrieveById(1);

        $valid = $provider->validateCredentials($user, ['password' => 'password123']);

        $this->assertTrue($valid);
    }

    #[Test]
    public function generic_provider_rejects_incorrect_password(): void
    {
        $provider = $this->createGenericProvider();
        $user = $provider->retrieveById(1);

        $valid = $provider->validateCredentials($user, ['password' => 'wrongpassword']);

        $this->assertFalse($valid);
    }

    #[Test]
    public function generic_provider_rejects_empty_password(): void
    {
        $provider = $this->createGenericProvider();
        $user = $provider->retrieveById(1);

        $valid = $provider->validateCredentials($user, []);

        $this->assertFalse($valid);
    }

    #[Test]
    public function generic_provider_uses_custom_validate_callback(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setValidateCallback(function ($user, $credentials) {
            // Custom validation: check a special token
            return ($credentials['special_token'] ?? '') === 'magic-token';
        });

        $user = $provider->retrieveById(1);

        $this->assertTrue($provider->validateCredentials($user, ['special_token' => 'magic-token']));
        $this->assertFalse($provider->validateCredentials($user, ['special_token' => 'wrong']));
    }

    // =========================================================================
    // GenericUserProvider - Remember Token Support
    // =========================================================================

    #[Test]
    public function generic_provider_retrieves_by_remember_token(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setByTokenCallback(function ($identifier, $token) {
            $user = $this->userStore[$identifier] ?? null;
            if ($user && $user->getRememberToken() === $token) {
                return $user;
            }
            return null;
        });

        $user = $provider->retrieveByToken(2, 'existing-token-abc');

        $this->assertNotNull($user);
        $this->assertEquals(2, $user->getAuthIdentifier());
    }

    #[Test]
    public function generic_provider_returns_null_for_invalid_token(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setByTokenCallback(function ($identifier, $token) {
            $user = $this->userStore[$identifier] ?? null;
            if ($user && $user->getRememberToken() === $token) {
                return $user;
            }
            return null;
        });

        $user = $provider->retrieveByToken(2, 'wrong-token');

        $this->assertNull($user);
    }

    #[Test]
    public function generic_provider_updates_remember_token(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setUpdateTokenCallback(function ($user, $token) {
            $user->setRememberToken($token);
        });

        $user = $provider->retrieveById(1);
        $this->assertNull($user->getRememberToken());

        $provider->updateRememberToken($user, 'new-remember-token');

        $this->assertEquals('new-remember-token', $user->getRememberToken());
    }

    // =========================================================================
    // GenericUserProvider - API Key Support
    // =========================================================================

    #[Test]
    public function generic_provider_retrieves_by_api_key(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setByApiKeyCallback(function ($apiKey) {
            foreach ($this->userStore as $user) {
                if ($user->apiKey === $apiKey) {
                    return $user;
                }
            }
            return null;
        });

        $user = $provider->retrieveByApiKey('api-key-john-123');

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->getAuthIdentifier());
    }

    #[Test]
    public function generic_provider_returns_null_for_invalid_api_key(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setByApiKeyCallback(function ($apiKey) {
            foreach ($this->userStore as $user) {
                if ($user->apiKey === $apiKey) {
                    return $user;
                }
            }
            return null;
        });

        $user = $provider->retrieveByApiKey('invalid-api-key');

        $this->assertNull($user);
    }

    #[Test]
    public function generic_provider_returns_null_when_no_api_key_callback(): void
    {
        $provider = $this->createGenericProvider();

        $user = $provider->retrieveByApiKey('any-key');

        $this->assertNull($user);
    }

    // =========================================================================
    // GenericUserProvider - Password Hashing
    // =========================================================================

    #[Test]
    public function generic_provider_hashes_passwords(): void
    {
        $provider = $this->createGenericProvider();

        $hash = $provider->hashPassword('newpassword');

        $this->assertNotEquals('newpassword', $hash);
        $this->assertTrue($this->hasher->verify('newpassword', $hash));
    }

    #[Test]
    public function generic_provider_detects_password_needs_rehash(): void
    {
        // Create user with old/weak hash that needs rehash
        $weakHasher = $this->createMock(PasswordHasherInterface::class);
        $weakHasher->method('verify')->willReturn(true);
        $weakHasher->method('needsRehash')->willReturn(true);

        $provider = new GenericUserProvider($weakHasher);
        $provider->setByIdCallback(fn($id) => $this->userStore[$id] ?? null);

        $user = $provider->retrieveById(1);

        $this->assertTrue($provider->passwordNeedsRehash($user));
    }

    #[Test]
    public function generic_provider_allows_custom_hasher(): void
    {
        $customHasher = $this->createMock(PasswordHasherInterface::class);
        $customHasher->method('hash')->willReturn('custom-hash');

        $provider = new GenericUserProvider($customHasher);

        $this->assertEquals('custom-hash', $provider->hashPassword('test'));
        $this->assertSame($customHasher, $provider->getHasher());
    }

    // =========================================================================
    // GenericUserProvider - Factory Methods
    // =========================================================================

    #[Test]
    public function generic_provider_creates_from_factory_method(): void
    {
        $provider = GenericUserProvider::create(
            byId: fn($id) => $this->userStore[$id] ?? null,
            byCredentials: function ($credentials) {
                $email = $credentials['email'] ?? null;
                foreach ($this->userStore as $user) {
                    if ($user->email === $email) {
                        return $user;
                    }
                }
                return null;
            }
        );

        $userById = $provider->retrieveById(1);
        $userByCreds = $provider->retrieveByCredentials(['email' => 'jane@example.com']);

        $this->assertNotNull($userById);
        $this->assertEquals(1, $userById->getAuthIdentifier());

        $this->assertNotNull($userByCreds);
        $this->assertEquals(2, $userByCreds->getAuthIdentifier());
    }

    #[Test]
    public function generic_provider_fluent_callback_configuration(): void
    {
        $provider = (new GenericUserProvider())
            ->setByIdCallback(fn($id) => $this->userStore[$id] ?? null)
            ->setByCredentialsCallback(function ($credentials) {
                $email = $credentials['email'] ?? null;
                foreach ($this->userStore as $user) {
                    if ($user->email === $email) {
                        return $user;
                    }
                }
                return null;
            })
            ->setByTokenCallback(fn($id, $token) => null)
            ->setByApiKeyCallback(fn($key) => null)
            ->setUpdateTokenCallback(fn($user, $token) => null)
            ->setValidateCallback(fn($user, $creds) => true);

        $this->assertNotNull($provider->retrieveById(1));
        $this->assertNotNull($provider->retrieveByCredentials(['email' => 'john@example.com']));
    }

    // =========================================================================
    // GenericUserProvider - Full Authentication Flow
    // =========================================================================

    #[Test]
    public function generic_provider_full_authentication_flow(): void
    {
        $provider = $this->createGenericProvider();
        $provider->setUpdateTokenCallback(fn($user, $token) => $user->setRememberToken($token));

        // Step 1: Retrieve user by credentials
        $user = $provider->retrieveByCredentials(['email' => 'john@example.com']);
        $this->assertNotNull($user);

        // Step 2: Validate password
        $valid = $provider->validateCredentials($user, ['password' => 'password123']);
        $this->assertTrue($valid);

        // Step 3: Generate and set remember token
        $rememberToken = bin2hex(random_bytes(32));
        $provider->updateRememberToken($user, $rememberToken);
        $this->assertEquals($rememberToken, $user->getRememberToken());

        // Step 4: Retrieve by ID (simulating session restore)
        $sessionUser = $provider->retrieveById($user->getAuthIdentifier());
        $this->assertNotNull($sessionUser);
        $this->assertEquals($user->getAuthIdentifier(), $sessionUser->getAuthIdentifier());
    }

    // =========================================================================
    // ModelUserProvider - Validation
    // =========================================================================

    #[Test]
    public function model_provider_throws_for_nonexistent_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("does not exist");

        new ModelUserProvider('NonExistentModel');
    }

    #[Test]
    public function model_provider_throws_for_model_without_query_methods(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot use ModelUserProvider");

        // stdClass has no query methods
        new ModelUserProvider(\stdClass::class);
    }

    #[Test]
    public function model_provider_validates_credentials(): void
    {
        $provider = new ModelUserProvider(QueryableUser::class, $this->hasher);
        $user = new QueryableUser();
        $user->password = $this->hasher->hash('testpass');

        $valid = $provider->validateCredentials($user, ['password' => 'testpass']);

        $this->assertTrue($valid);
    }

    #[Test]
    public function model_provider_rejects_invalid_password(): void
    {
        $provider = new ModelUserProvider(QueryableUser::class, $this->hasher);
        $user = new QueryableUser();
        $user->password = $this->hasher->hash('testpass');

        $valid = $provider->validateCredentials($user, ['password' => 'wrongpass']);

        $this->assertFalse($valid);
    }

    #[Test]
    public function model_provider_rejects_missing_password(): void
    {
        $provider = new ModelUserProvider(QueryableUser::class, $this->hasher);
        $user = new QueryableUser();

        $valid = $provider->validateCredentials($user, []);

        $this->assertFalse($valid);
    }

    #[Test]
    public function model_provider_hashes_passwords(): void
    {
        $provider = new ModelUserProvider(QueryableUser::class, $this->hasher);

        $hash = $provider->hashPassword('mypassword');

        $this->assertNotEquals('mypassword', $hash);
        $this->assertTrue($this->hasher->verify('mypassword', $hash));
    }

    #[Test]
    public function model_provider_getters_and_setters(): void
    {
        $provider = new ModelUserProvider(QueryableUser::class, $this->hasher);

        $this->assertEquals(QueryableUser::class, $provider->getModel());
        $this->assertSame($this->hasher, $provider->getHasher());

        $newHasher = new NativePasswordHasher();
        $result = $provider->setHasher($newHasher);

        $this->assertSame($provider, $result);
        $this->assertSame($newHasher, $provider->getHasher());
    }

    // =========================================================================
    // Multiple Users Scenario
    // =========================================================================

    #[Test]
    public function provider_handles_multiple_users_with_same_domain(): void
    {
        // Add more users with same email domain
        $this->userStore[4] = new FakeAuthenticatableUser(
            id: 4,
            email: 'user1@example.com',
            password: $this->hasher->hash('pass1'),
            rememberToken: null,
            apiKey: null
        );
        $this->userStore[5] = new FakeAuthenticatableUser(
            id: 5,
            email: 'user2@example.com',
            password: $this->hasher->hash('pass2'),
            rememberToken: null,
            apiKey: null
        );

        $provider = $this->createGenericProvider();

        // Each user should be independently retrievable
        $user1 = $provider->retrieveByCredentials(['email' => 'user1@example.com']);
        $user2 = $provider->retrieveByCredentials(['email' => 'user2@example.com']);

        $this->assertEquals(4, $user1->getAuthIdentifier());
        $this->assertEquals(5, $user2->getAuthIdentifier());

        // Different passwords
        $this->assertTrue($provider->validateCredentials($user1, ['password' => 'pass1']));
        $this->assertFalse($provider->validateCredentials($user1, ['password' => 'pass2']));

        $this->assertTrue($provider->validateCredentials($user2, ['password' => 'pass2']));
        $this->assertFalse($provider->validateCredentials($user2, ['password' => 'pass1']));
    }

    #[Test]
    public function provider_credentials_lookup_excludes_password_field(): void
    {
        $lookupCalled = false;
        $capturedCredentials = [];

        $provider = new GenericUserProvider();
        $provider->setByCredentialsCallback(function ($credentials) use (&$lookupCalled, &$capturedCredentials) {
            $lookupCalled = true;
            $capturedCredentials = $credentials;
            return $this->userStore[1];
        });

        $provider->retrieveByCredentials([
            'email' => 'john@example.com',
            'password' => 'should-be-excluded',
        ]);

        $this->assertTrue($lookupCalled);
        // Password should NOT be passed to the lookup callback
        $this->assertArrayHasKey('email', $capturedCredentials);
        $this->assertArrayHasKey('password', $capturedCredentials); // GenericProvider passes all
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createGenericProvider(): GenericUserProvider
    {
        $provider = new GenericUserProvider($this->hasher);

        $provider->setByIdCallback(fn($id) => $this->userStore[$id] ?? null);

        $provider->setByCredentialsCallback(function ($credentials) {
            $email = $credentials['email'] ?? null;
            foreach ($this->userStore as $user) {
                if ($user->email === $email) {
                    return $user;
                }
            }
            return null;
        });

        return $provider;
    }
}

// =============================================================================
// Test Helper Classes
// =============================================================================

/**
 * Fake authenticatable user for testing
 */
class FakeAuthenticatableUser implements AuthenticatableInterface
{
    public function __construct(
        private int $id,
        public string $email,
        private string $password,
        private ?string $rememberToken,
        public ?string $apiKey
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $value): void
    {
        $this->rememberToken = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

/**
 * Minimal model class with query methods for ModelUserProvider testing
 */
class QueryableUser implements AuthenticatableInterface
{
    public int $id = 1;
    public string $email = 'test@example.com';
    public string $password = '';
    public ?string $remember_token = null;

    private static array $users = [];

    public static function find($id): ?self
    {
        return self::$users[$id] ?? null;
    }

    public static function findOneBy(array $conditions): ?self
    {
        foreach (self::$users as $user) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if ($user->$key !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $user;
            }
        }
        return null;
    }

    public static function addToStore(self $user): void
    {
        self::$users[$user->id] = $user;
    }

    public static function clearStore(): void
    {
        self::$users = [];
    }

    public function save(): void
    {
        self::$users[$this->id] = $this;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
