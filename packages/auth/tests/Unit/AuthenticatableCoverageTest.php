<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Auth\Concerns\Authenticatable;
use Lalaz\Auth\NativePasswordHasher;
use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\Contracts\SessionInterface;

/**
 * Additional coverage tests for Authenticatable trait.
 */
#[CoversClass(\Lalaz\Auth\Concerns\Authenticatable::class)]
final class AuthenticatableCoverageTest extends AuthUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static state
        TestUserWithAuthenticatable::setPasswordHasher(new NativePasswordHasher());
    }

    // =========================================================================
    // Password Hasher
    // =========================================================================

    #[Test]
    public function it_sets_and_gets_custom_password_hasher(): void
    {
        $customHasher = new class implements PasswordHasherInterface {
            public function hash(string $plainText): string { return 'custom:' . $plainText; }
            public function verify(string $plainText, string $hash): bool { return 'custom:' . $plainText === $hash; }
            public function needsRehash(string $hash): bool { return false; }
        };

        TestUserWithAuthenticatable::setPasswordHasher($customHasher);
        $hasher = TestUserWithAuthenticatable::getPasswordHasher();

        $this->assertSame($customHasher, $hasher);
        $this->assertEquals('custom:test', $hasher->hash('test'));
    }

    #[Test]
    public function it_uses_default_hasher_when_none_set(): void
    {
        // Force null by setting null via reflection
        $reflection = new \ReflectionClass(TestUserWithAuthenticatable::class);
        $prop = $reflection->getProperty('passwordHasher');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $hasher = TestUserWithAuthenticatable::getPasswordHasher();
        $this->assertInstanceOf(NativePasswordHasher::class, $hasher);
    }

    // =========================================================================
    // Identity Methods
    // =========================================================================

    #[Test]
    public function it_gets_auth_identifier_from_id_property(): void
    {
        $user = new TestUserWithAuthenticatable();
        $user->id = 123;

        $this->assertEquals(123, $user->getAuthIdentifier());
    }

    #[Test]
    public function it_gets_auth_identifier_from_getId_method(): void
    {
        $user = new TestUserWithGetId();
        $this->assertEquals('method-id-456', $user->getAuthIdentifier());
    }

    #[Test]
    public function it_returns_null_when_id_property_is_null(): void
    {
        $user = new TestUserWithAuthenticatable();
        // id is null by default
        $this->assertNull($user->getAuthIdentifier());
    }

    #[Test]
    public function it_returns_id_as_identifier_name(): void
    {
        $user = new TestUserWithAuthenticatable();
        $this->assertEquals('id', $user->getAuthIdentifierName());
    }

    // =========================================================================
    // Password Methods
    // =========================================================================

    #[Test]
    public function it_gets_auth_password(): void
    {
        $user = new TestUserWithAuthenticatable();
        $user->password = 'hashed-password';

        $this->assertEquals('hashed-password', $user->getAuthPassword());
    }

    #[Test]
    public function it_returns_empty_string_when_no_password(): void
    {
        $user = new TestUserWithAuthenticatable();
        $this->assertEquals('', $user->getAuthPassword());
    }

    #[Test]
    public function it_verifies_correct_password(): void
    {
        $user = new TestUserWithAuthenticatable();
        $user->password = password_hash('secret123', PASSWORD_DEFAULT);

        $this->assertTrue($user->verifyPassword('secret123'));
        $this->assertFalse($user->verifyPassword('wrong'));
    }

    #[Test]
    public function it_returns_false_for_empty_password(): void
    {
        $user = new TestUserWithAuthenticatable();
        $this->assertFalse($user->verifyPassword('anything'));
    }

    #[Test]
    public function it_checks_if_password_needs_rehash(): void
    {
        $user = new TestUserWithAuthenticatable();
        $user->password = password_hash('test', PASSWORD_DEFAULT);

        // Default hasher should not need rehash for recently hashed password
        $this->assertFalse($user->passwordNeedsRehash());
    }

    #[Test]
    public function it_returns_false_for_empty_password_rehash_check(): void
    {
        $user = new TestUserWithAuthenticatable();
        $this->assertFalse($user->passwordNeedsRehash());
    }

    #[Test]
    public function it_hashes_password_statically(): void
    {
        $hash = TestUserWithAuthenticatable::hashPassword('mypassword');

        $this->assertNotEquals('mypassword', $hash);
        $this->assertTrue(password_verify('mypassword', $hash));
    }

    // =========================================================================
    // Remember Token
    // =========================================================================

    #[Test]
    public function it_gets_and_sets_remember_token(): void
    {
        $user = new TestUserWithAuthenticatable();

        $this->assertNull($user->getRememberToken());

        $user->setRememberToken('my-remember-token');
        $this->assertEquals('my-remember-token', $user->getRememberToken());

        $user->setRememberToken(null);
        $this->assertNull($user->getRememberToken());
    }

    #[Test]
    public function it_returns_remember_token_column_name(): void
    {
        $user = new TestUserWithAuthenticatable();
        $this->assertEquals('remember_token', $user->getRememberTokenName());
    }

    // =========================================================================
    // Credential Validation
    // =========================================================================

    #[Test]
    public function it_validates_credentials_with_findBy(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        TestUserWithFindBy::$mockUsers = [
            ['email' => 'user@test.com', 'password' => $hash, 'id' => 1]
        ];

        $user = TestUserWithFindBy::validateCredentials('user@test.com', 'secret');

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
    }

    #[Test]
    public function it_validates_credentials_with_findOneBy(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        TestUserWithFindOneBy::$mockUser = new TestUserWithFindOneBy();
        TestUserWithFindOneBy::$mockUser->email = 'user@test.com';
        TestUserWithFindOneBy::$mockUser->password = $hash;
        TestUserWithFindOneBy::$mockUser->id = 2;

        $user = TestUserWithFindOneBy::validateCredentials('user@test.com', 'secret');

        $this->assertNotNull($user);
        $this->assertEquals(2, $user->id);
    }

    #[Test]
    public function it_returns_null_for_invalid_username(): void
    {
        TestUserWithFindBy::$mockUsers = [];

        $user = TestUserWithFindBy::validateCredentials('nonexistent@test.com', 'secret');

        $this->assertNull($user);
    }

    #[Test]
    public function it_returns_null_for_invalid_password(): void
    {
        $hash = password_hash('correct', PASSWORD_DEFAULT);
        TestUserWithFindBy::$mockUsers = [
            ['email' => 'user@test.com', 'password' => $hash, 'id' => 1]
        ];

        $user = TestUserWithFindBy::validateCredentials('user@test.com', 'wrong');

        $this->assertNull($user);
    }

    #[Test]
    public function it_returns_null_when_no_finder_methods(): void
    {
        $user = TestUserWithAuthenticatable::validateCredentials('user@test.com', 'secret');

        $this->assertNull($user);
    }

    // =========================================================================
    // Deprecated Methods (Still Need Coverage)
    // =========================================================================

    #[Test]
    public function deprecated_authenticate_validates_and_stores_in_session(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        TestUserWithFindBy::$mockUsers = [
            ['email' => 'user@test.com', 'password' => $hash, 'id' => 1]
        ];

        $session = new FakeSessionForAuth();

        // Suppress deprecation warning for test
        @$user = TestUserWithFindBy::authenticate('user@test.com', 'secret', $session);

        $this->assertNotFalse($user);
        $this->assertTrue($session->regenerated);
        $this->assertNotNull($session->get('__luser'));
    }

    #[Test]
    public function deprecated_authenticate_returns_false_for_invalid(): void
    {
        TestUserWithFindBy::$mockUsers = [];

        @$result = TestUserWithFindBy::authenticate('nonexistent@test.com', 'secret', new FakeSessionForAuth());

        $this->assertFalse($result);
    }

    #[Test]
    public function deprecated_logout_destroys_session(): void
    {
        $session = new FakeSessionForAuth();

        @TestUserWithFindBy::logout($session);

        $this->assertTrue($session->destroyed);
    }

    #[Test]
    public function deprecated_authenticated_user_returns_from_session(): void
    {
        $session = new FakeSessionForAuth();
        $user = new TestUserWithFindBy();
        $session->set('__luser', $user);

        @$result = TestUserWithFindBy::authenticatedUser($session);

        $this->assertSame($user, $result);
    }

    #[Test]
    public function deprecated_authenticated_user_returns_null_for_empty_session(): void
    {
        $session = new FakeSessionForAuth();

        @$result = TestUserWithFindBy::authenticatedUser($session);

        $this->assertNull($result);
    }

    #[Test]
    public function deprecated_is_authenticated_returns_true_when_user_exists(): void
    {
        $session = new FakeSessionForAuth();
        $session->set('__luser', new TestUserWithFindBy());

        @$result = TestUserWithFindBy::isAuthenticated($session);

        $this->assertTrue($result);
    }

    #[Test]
    public function deprecated_is_authenticated_returns_false_when_no_user(): void
    {
        $session = new FakeSessionForAuth();

        @$result = TestUserWithFindBy::isAuthenticated($session);

        $this->assertFalse($result);
    }
}

// ============================================================================
// Test Doubles
// ============================================================================

class TestUserWithAuthenticatable
{
    use Authenticatable;

    public mixed $id = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $remember_token = null;

    public function getId(): mixed
    {
        return $this->id;
    }

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }
}

class TestUserWithGetId
{
    use Authenticatable;

    public ?string $email = null;
    public ?string $password = null;
    public ?string $remember_token = null;

    public function getId(): string
    {
        return 'method-id-456';
    }

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }
}

class TestUserWithoutId
{
    use Authenticatable;

    public ?string $email = null;
    public ?string $password = null;
    public ?string $remember_token = null;

    public function getId(): ?string
    {
        return null;
    }

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }
}

class TestUserWithFindBy
{
    use Authenticatable;

    public static array $mockUsers = [];

    public mixed $id = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $remember_token = null;

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }

    public static function findBy(array $conditions): array
    {
        $results = [];
        foreach (static::$mockUsers as $data) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if (($data[$key] ?? null) !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $user = new static();
                foreach ($data as $k => $v) {
                    $user->{$k} = $v;
                }
                $results[] = $user;
            }
        }
        return $results;
    }
}

class TestUserWithFindOneBy
{
    use Authenticatable;

    public static ?TestUserWithFindOneBy $mockUser = null;

    public mixed $id = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $remember_token = null;

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }

    public static function findOneBy(array $conditions): ?static
    {
        if (static::$mockUser === null) {
            return null;
        }
        foreach ($conditions as $key => $value) {
            if ((static::$mockUser->{$key} ?? null) !== $value) {
                return null;
            }
        }
        return static::$mockUser;
    }
}

class FakeSessionForAuth implements SessionInterface
{
    private array $data = [];
    public bool $regenerated = false;
    public bool $destroyed = false;

    public function start(): void {}
    public function regenerate(bool $deleteOldSession = true): void { $this->regenerated = true; }
    public function destroy(): void { $this->destroyed = true; $this->data = []; }
    public function getId(): string { return 'fake-session-id'; }
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function has(string $key): bool { return isset($this->data[$key]); }
    public function remove(string $key): void { unset($this->data[$key]); }
    public function all(): array { return $this->data; }
    public function clear(): void { $this->data = []; }
    public function flash(string $key, mixed $value): void { $this->data['_flash'][$key] = $value; }
    public function getFlash(string $key, mixed $default = null): mixed { return $this->data['_flash'][$key] ?? $default; }
}
