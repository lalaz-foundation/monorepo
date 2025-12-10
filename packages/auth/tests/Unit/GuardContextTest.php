<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\GuardContext;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\GuardContext::class)]
final class GuardContextTest extends AuthUnitTestCase
{
    // =========================================================================
    // Constructor and Initial State
    // =========================================================================

    public function testCreatesWithAuthContextAndGuardName(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertSame('web', $guardContext->name());
    }

    // =========================================================================
    // Guard Name Access
    // =========================================================================

    public function testReturnsGuardName(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'api');

        $this->assertSame('api', $guardContext->name());
    }

    // =========================================================================
    // User Management
    // =========================================================================

    public function testSetUserReturnsChainableSelf(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(123);

        $result = $guardContext->setUser($user);

        $this->assertSame($guardContext, $result);
    }

    public function testUserReturnsNullWhenNoUserSet(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertNull($guardContext->user());
    }

    public function testUserReturnsUserWhenSet(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(123);

        $guardContext->setUser($user);

        $this->assertSame($user, $guardContext->user());
    }

    public function testClearReturnsChainableSelf(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(123);
        $guardContext->setUser($user);

        $result = $guardContext->clear();

        $this->assertSame($guardContext, $result);
    }

    public function testClearRemovesUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(123);
        $guardContext->setUser($user);

        $guardContext->clear();

        $this->assertNull($guardContext->user());
    }

    // =========================================================================
    // Authentication Checks
    // =========================================================================

    public function testIsAuthenticatedReturnsFalseWhenNoUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertFalse($guardContext->isAuthenticated());
    }

    public function testIsAuthenticatedReturnsTrueWhenUserSet(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $guardContext->setUser($this->fakeUser(123));

        $this->assertTrue($guardContext->isAuthenticated());
    }

    public function testCheckIsAliasForIsAuthenticated(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertFalse($guardContext->check());

        $guardContext->setUser($this->fakeUser(123));

        $this->assertTrue($guardContext->check());
    }

    public function testIsGuestReturnsTrueWhenNoUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertTrue($guardContext->isGuest());
    }

    public function testIsGuestReturnsFalseWhenUserSet(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $guardContext->setUser($this->fakeUser(123));

        $this->assertFalse($guardContext->isGuest());
    }

    public function testGuestIsAliasForIsGuest(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertTrue($guardContext->guest());

        $guardContext->setUser($this->fakeUser(123));

        $this->assertFalse($guardContext->guest());
    }

    // =========================================================================
    // ID Access
    // =========================================================================

    public function testIdReturnsNullWhenNoUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertNull($guardContext->id());
    }

    public function testIdReturnsUserIdWhenSet(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $guardContext->setUser($this->fakeUser(456));

        $this->assertSame(456, $guardContext->id());
    }

    // =========================================================================
    // Role Checks
    // =========================================================================

    public function testHasRoleReturnsFalseWhenNoUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertFalse($guardContext->hasRole('admin'));
    }

    public function testHasRoleReturnsTrueWhenUserHasRole(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', ['admin', 'editor']);
        $guardContext->setUser($user);

        $this->assertTrue($guardContext->hasRole('admin'));
    }

    public function testHasRoleReturnsFalseWhenUserDoesNotHaveRole(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', ['editor']);
        $guardContext->setUser($user);

        $this->assertFalse($guardContext->hasRole('admin'));
    }

    public function testHasAnyRoleReturnsTrueWhenUserHasAnyRole(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', ['editor']);
        $guardContext->setUser($user);

        $this->assertTrue($guardContext->hasAnyRole(['admin', 'editor', 'viewer']));
    }

    public function testHasAnyRoleReturnsFalseWhenUserHasNoMatchingRole(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', ['guest']);
        $guardContext->setUser($user);

        $this->assertFalse($guardContext->hasAnyRole(['admin', 'editor']));
    }

    // =========================================================================
    // Permission Checks
    // =========================================================================

    public function testHasPermissionReturnsFalseWhenNoUser(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');

        $this->assertFalse($guardContext->hasPermission('posts.create'));
    }

    public function testHasPermissionReturnsTrueWhenUserHasPermission(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', [], ['posts.create', 'posts.edit']);
        $guardContext->setUser($user);

        $this->assertTrue($guardContext->hasPermission('posts.create'));
    }

    public function testHasAnyPermissionReturnsTrueWhenUserHasAnyPermission(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', [], ['posts.view']);
        $guardContext->setUser($user);

        $this->assertTrue($guardContext->hasAnyPermission(['posts.create', 'posts.view']));
    }

    public function testHasAnyPermissionReturnsFalseWhenUserHasNoMatchingPermission(): void
    {
        $authContext = $this->createAuthContext();
        $guardContext = new GuardContext($authContext, 'web');
        $user = $this->fakeUser(1, 'password', [], ['posts.delete']);
        $guardContext->setUser($user);

        $this->assertFalse($guardContext->hasAnyPermission(['posts.create', 'posts.edit']));
    }

    // =========================================================================
    // Guard Isolation
    // =========================================================================

    public function testDifferentGuardContextsAreIsolated(): void
    {
        $authContext = $this->createAuthContext();
        $webContext = new GuardContext($authContext, 'web');
        $apiContext = new GuardContext($authContext, 'api');

        $webUser = $this->fakeUser(1);
        $apiUser = $this->fakeUser(2);

        $webContext->setUser($webUser);
        $apiContext->setUser($apiUser);

        $this->assertSame(1, $webContext->id());
        $this->assertSame(2, $apiContext->id());
        $this->assertNotSame($webContext->user(), $apiContext->user());
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createAuthContext(): AuthContext
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();

        return new AuthContext($session, $provider);
    }
}
