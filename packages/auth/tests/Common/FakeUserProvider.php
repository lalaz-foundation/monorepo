<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\ApiKeyProviderInterface;

/**
 * Fake user provider for testing authentication.
 */
class FakeUserProvider implements UserProviderInterface, RememberTokenProviderInterface, ApiKeyProviderInterface
{
    /**
     * @var array<mixed, FakeUser>
     */
    private array $users = [];

    /**
     * @var array<string, mixed>
     */
    private array $apiKeys = [];

    /**
     * @var array<string, mixed>
     */
    private array $tokens = [];

    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->addUser($user);
        }
    }

    public function addUser(FakeUser $user): self
    {
        $this->users[$user->getAuthIdentifier()] = $user;
        return $this;
    }

    public function addApiKey(string $apiKey, mixed $userId): self
    {
        $this->apiKeys[$apiKey] = $userId;
        return $this;
    }

    public function addToken(string $token, mixed $userId): self
    {
        $this->tokens[$token] = $userId;
        return $this;
    }

    public function retrieveById(mixed $identifier): mixed
    {
        return $this->users[$identifier] ?? null;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        foreach ($this->users as $user) {
            $match = true;

            foreach ($credentials as $key => $value) {
                if ($key === 'password') {
                    continue;
                }

                // For testing, we check if the user ID matches the email/username
                if ($key === 'email' || $key === 'username') {
                    if ($user->getAuthIdentifier() !== $value) {
                        $match = false;
                        break;
                    }
                }
            }

            if ($match) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if ($password === null) {
            return false;
        }

        return password_verify($password, $user->getAuthPassword());
    }

    public function updateRememberToken(mixed $user, string $token): void
    {
        $userId = $user->getAuthIdentifier();
        $this->tokens[$token] = $userId;
    }

    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        // Check if the token exists and belongs to the user
        $userId = $this->tokens[$token] ?? null;

        if ($userId === null || $userId !== $identifier) {
            return null;
        }

        return $this->users[$userId] ?? null;
    }

    public function retrieveByApiKey(string $apiKey): mixed
    {
        $userId = $this->apiKeys[$apiKey] ?? null;

        if ($userId === null) {
            return null;
        }

        return $this->users[$userId] ?? null;
    }
}
