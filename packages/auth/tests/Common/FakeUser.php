<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Auth\Contracts\AuthenticatableInterface;

/**
 * Fake user for testing authentication.
 */
class FakeUser implements AuthenticatableInterface
{
    private ?string $rememberToken = null;

    public function __construct(
        private mixed $id = 1,
        private string $password = 'hashed_password',
        private array $roles = [],
        private array $permissions = [],
    ) {
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
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

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function setId(mixed $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }
}
