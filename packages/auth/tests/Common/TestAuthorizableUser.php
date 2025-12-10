<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Auth\Concerns\Authorizable;

/**
 * Test class that uses the Authorizable trait.
 */
class TestAuthorizableUser
{
    use Authorizable;

    private array $roles;
    private array $permissions;

    public function __construct(array $roles = [], array $permissions = [])
    {
        $this->roles = $roles;
        $this->permissions = $permissions;
    }

    protected function fetchRoles(): array
    {
        return $this->roles;
    }

    protected function fetchPermissions(): array
    {
        return $this->permissions;
    }

    public function setTestRoles(array $roles): void
    {
        $this->roles = $roles;
        // Note: Don't clear cache here to allow testing the cache behavior
    }

    public function setTestPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
        // Note: Don't clear cache here to allow testing the cache behavior
    }
}
