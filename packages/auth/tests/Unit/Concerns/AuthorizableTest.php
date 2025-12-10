<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Concerns;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use Lalaz\Auth\Tests\Common\TestAuthorizableUser;

#[CoversClass(\Lalaz\Auth\Concerns\Authorizable::class)]
final class AuthorizableTest extends AuthUnitTestCase
{
    // =========================================================================
    // Role Checking
    // =========================================================================

    public function testHasRoleReturnsTrueWhenUserHasRole(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['admin', 'editor']);

        $this->assertTrue($user->hasRole('admin'));
    }

    public function testHasRoleReturnsFalseWhenUserDoesNotHaveRole(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['user']);

        $this->assertFalse($user->hasRole('admin'));
    }

    public function testHasRoleWithEmptyRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles([]);

        $this->assertFalse($user->hasRole('admin'));
    }

    // =========================================================================
    // Multiple Role Checking
    // =========================================================================

    public function testHasAnyRoleReturnsTrueWhenUserHasOneOfTheRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['editor']);

        $this->assertTrue($user->hasAnyRole(['admin', 'editor', 'user']));
    }

    public function testHasAnyRoleReturnsFalseWhenUserHasNoneOfTheRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['guest']);

        $this->assertFalse($user->hasAnyRole(['admin', 'editor', 'user']));
    }

    public function testHasAllRolesReturnsTrueWhenUserHasAllRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['admin', 'editor', 'manager']);

        $this->assertTrue($user->hasAllRoles(['admin', 'editor']));
    }

    public function testHasAllRolesReturnsFalseWhenUserIsMissingSomeRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['admin']);

        $this->assertFalse($user->hasAllRoles(['admin', 'editor']));
    }

    // =========================================================================
    // Permission Checking
    // =========================================================================

    public function testHasPermissionReturnsTrueWhenUserHasPermission(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['edit', 'delete']);

        $this->assertTrue($user->hasPermission('edit'));
    }

    public function testHasPermissionReturnsFalseWhenUserDoesNotHavePermission(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['view']);

        $this->assertFalse($user->hasPermission('edit'));
    }

    // =========================================================================
    // Multiple Permission Checking
    // =========================================================================

    public function testHasAnyPermissionReturnsTrueWhenUserHasOneOfThePermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['view']);

        $this->assertTrue($user->hasAnyPermission(['edit', 'view', 'delete']));
    }

    public function testHasAnyPermissionReturnsFalseWhenUserHasNoneOfThePermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['comment']);

        $this->assertFalse($user->hasAnyPermission(['edit', 'view', 'delete']));
    }

    public function testHasAllPermissionsReturnsTrueWhenUserHasAllPermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['edit', 'view', 'delete']);

        $this->assertTrue($user->hasAllPermissions(['edit', 'view']));
    }

    public function testHasAllPermissionsReturnsFalseWhenUserIsMissingSomePermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['edit']);

        $this->assertFalse($user->hasAllPermissions(['edit', 'view']));
    }

    // =========================================================================
    // Getting Roles and Permissions
    // =========================================================================

    public function testGetRolesReturnsAllRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['admin', 'editor']);

        $roles = $user->getRoles();

        $this->assertContains('admin', $roles);
        $this->assertContains('editor', $roles);
        $this->assertCount(2, $roles);
    }

    public function testGetPermissionsReturnsAllPermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['edit', 'delete', 'view']);

        $permissions = $user->getPermissions();

        $this->assertContains('edit', $permissions);
        $this->assertContains('delete', $permissions);
        $this->assertContains('view', $permissions);
        $this->assertCount(3, $permissions);
    }

    // =========================================================================
    // Authorization Cache
    // =========================================================================

    public function testClearAuthorizationCacheClearsRoles(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestRoles(['admin']);

        // Access roles to cache them
        $user->getRoles();

        // Change underlying roles
        $user->setTestRoles(['editor']);

        // Still returns cached admin
        $this->assertTrue($user->hasRole('admin'));

        // Clear cache
        $user->clearAuthorizationCache();

        // Now returns new roles
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    public function testClearAuthorizationCacheClearsPermissions(): void
    {
        $user = new TestAuthorizableUser();
        $user->setTestPermissions(['edit']);

        // Access permissions to cache them
        $user->getPermissions();

        // Change underlying permissions
        $user->setTestPermissions(['delete']);

        // Still returns cached edit
        $this->assertTrue($user->hasPermission('edit'));

        // Clear cache
        $user->clearAuthorizationCache();

        // Now returns new permissions
        $this->assertFalse($user->hasPermission('edit'));
        $this->assertTrue($user->hasPermission('delete'));
    }
}
