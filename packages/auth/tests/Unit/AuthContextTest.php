<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;
use stdClass;

#[CoversClass(\Lalaz\Auth\AuthContext::class)]
final class AuthContextTest extends AuthUnitTestCase
{
    private AuthContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new AuthContext();
    }

    // =========================================================================
    // User Management
    // =========================================================================

    public function testStartsWithNoUser(): void
    {
        $this->assertNull($this->context->user());
    }

    public function testSetsAndGetsUser(): void
    {
        $user = $this->fakeUser(123);
        $this->context->setUser($user);

        $this->assertSame($user, $this->context->user());
    }

    public function testClearsUser(): void
    {
        $user = $this->fakeUser(123);
        $this->context->setUser($user);
        $this->context->clear();

        $this->assertNull($this->context->user());
    }

    // =========================================================================
    // Authentication State
    // =========================================================================

    public function testReturnsFalseForIsAuthenticatedWhenNoUser(): void
    {
        $this->assertFalse($this->context->isAuthenticated());
    }

    public function testReturnsTrueForIsAuthenticatedWhenUserSet(): void
    {
        $this->context->setUser($this->fakeUser(123));

        $this->assertTrue($this->context->isAuthenticated());
    }

    public function testReturnsTrueForIsGuestWhenNoUser(): void
    {
        $this->assertTrue($this->context->isGuest());
    }

    public function testReturnsFalseForIsGuestWhenUserSet(): void
    {
        $this->context->setUser($this->fakeUser(123));

        $this->assertFalse($this->context->isGuest());
    }

    // =========================================================================
    // User ID Extraction
    // =========================================================================

    public function testReturnsNullIdWhenNoUser(): void
    {
        $this->assertNull($this->context->id());
    }

    public function testExtractsIdFromFakeUserUsingGetAuthIdentifier(): void
    {
        $user = $this->fakeUser(456);
        $this->context->setUser($user);

        $this->assertSame(456, $this->context->id());
    }

    public function testExtractsIdFromArrayWithIdKey(): void
    {
        $user = ['id' => 789, 'name' => 'Test'];
        $this->context->setUser($user);

        $this->assertSame(789, $this->context->id());
    }

    public function testExtractsIdFromObjectWithPublicIdProperty(): void
    {
        $user = new stdClass();
        $user->id = 999;
        $this->context->setUser($user);

        $this->assertSame(999, $this->context->id());
    }

    public function testExtractsIdFromObjectWithGetIdMethod(): void
    {
        $user = new class {
            public function getId(): int
            {
                return 111;
            }
        };
        $this->context->setUser($user);

        $this->assertSame(111, $this->context->id());
    }

    public function testReturnsNullWhenNoIdAvailable(): void
    {
        $user = new stdClass();
        $user->name = 'Test';
        $this->context->setUser($user);

        $this->assertNull($this->context->id());
    }

    // =========================================================================
    // Role Checking
    // =========================================================================

    public function testReturnsFalseForHasRoleWhenNoUser(): void
    {
        $this->assertFalse($this->context->hasRole('admin'));
    }

    public function testReturnsTrueWhenUserHasRole(): void
    {
        $user = $this->fakeUser(1)->setRoles(['admin', 'moderator']);
        $this->context->setUser($user);

        $this->assertTrue($this->context->hasRole('admin'));
    }

    public function testReturnsFalseWhenUserDoesNotHaveRole(): void
    {
        $user = $this->fakeUser(1)->setRoles(['user']);
        $this->context->setUser($user);

        $this->assertFalse($this->context->hasRole('admin'));
    }

    public function testReturnsFalseForHasAnyRoleWhenNoUser(): void
    {
        $this->assertFalse($this->context->hasAnyRole(['admin', 'moderator']));
    }

    public function testReturnsTrueWhenUserHasAnyOfTheRoles(): void
    {
        $user = $this->fakeUser(1)->setRoles(['moderator']);
        $this->context->setUser($user);

        $this->assertTrue($this->context->hasAnyRole(['admin', 'moderator']));
    }

    public function testReturnsFalseWhenUserHasNoneOfTheRoles(): void
    {
        $user = $this->fakeUser(1)->setRoles(['user']);
        $this->context->setUser($user);

        $this->assertFalse($this->context->hasAnyRole(['admin', 'moderator']));
    }

    // =========================================================================
    // Permission Checking
    // =========================================================================

    public function testReturnsFalseForHasPermissionWhenNoUser(): void
    {
        $this->assertFalse($this->context->hasPermission('posts.create'));
    }

    public function testReturnsTrueWhenUserHasPermission(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.create', 'posts.edit']);
        $this->context->setUser($user);

        $this->assertTrue($this->context->hasPermission('posts.create'));
    }

    public function testReturnsFalseWhenUserDoesNotHavePermission(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.view']);
        $this->context->setUser($user);

        $this->assertFalse($this->context->hasPermission('posts.create'));
    }

    public function testReturnsFalseForHasAnyPermissionWhenNoUser(): void
    {
        $this->assertFalse($this->context->hasAnyPermission(['posts.create', 'posts.delete']));
    }

    public function testReturnsTrueWhenUserHasAnyOfThePermissions(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.delete']);
        $this->context->setUser($user);

        $this->assertTrue($this->context->hasAnyPermission(['posts.create', 'posts.delete']));
    }

    public function testReturnsFalseWhenUserHasNoneOfThePermissions(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.view']);
        $this->context->setUser($user);

        $this->assertFalse($this->context->hasAnyPermission(['posts.create', 'posts.delete']));
    }
}
