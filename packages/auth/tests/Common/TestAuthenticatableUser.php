<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Auth\Concerns\Authenticatable;

/**
 * Test user class that uses the Authenticatable trait.
 */
class TestAuthenticatableUser
{
    use Authenticatable;

    public int $id;
    public string $email;
    public string $password;
    public ?string $remember_token = null;

    /** @var array<int, self> */
    private static array $users = [];

    public function __construct(int $id, string $email, string $password)
    {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
    }

    protected static function usernamePropertyName(): string
    {
        return 'email';
    }

    protected static function passwordPropertyName(): string
    {
        return 'password';
    }

    /**
     * Simulates findOneBy for testing.
     */
    public static function findOneBy(array $criteria): ?self
    {
        foreach (self::$users as $user) {
            foreach ($criteria as $property => $value) {
                if ($user->{$property} === $value) {
                    return $user;
                }
            }
        }
        return null;
    }

    /**
     * Add user to the "database" for testing.
     */
    public static function addUser(self $user): void
    {
        self::$users[$user->id] = $user;
    }

    /**
     * Clear all users.
     */
    public static function clearUsers(): void
    {
        self::$users = [];
    }
}
