<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\AuthContext;
use Lalaz\Auth\GuardContext;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\AuthContext::class)]
final class AuthContextMultiGuardTest extends AuthUnitTestCase
{
    private AuthContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new AuthContext();
    }

    // =========================================================================
    // Multi-Guard Support
    // =========================================================================

    public function testManagesUsersForDifferentGuardsIndependently(): void
    {
        $webUser = $this->fakeUser(1);
        $apiUser = $this->fakeUser(2);

        $this->context->setUser($webUser, 'web');
        $this->context->setUser($apiUser, 'api');

        $this->assertSame($webUser, $this->context->user('web'));
        $this->assertSame($apiUser, $this->context->user('api'));
    }

    public function testUsesCurrentGuardWhenNoGuardSpecified(): void
    {
        $user = $this->fakeUser(1);

        $this->context->setCurrentGuard('api');
        $this->context->setUser($user);

        $this->assertSame($user, $this->context->user());
        $this->assertSame($user, $this->context->user('api'));
        $this->assertNull($this->context->user('web'));
    }

    public function testSetsAndGetsCurrentGuard(): void
    {
        $this->assertSame('web', $this->context->getCurrentGuard()); // default

        $this->context->setCurrentGuard('api');

        $this->assertSame('api', $this->context->getCurrentGuard());
    }

    public function testSetsAndGetsDefaultGuard(): void
    {
        $this->assertSame('web', $this->context->getDefaultGuard());

        $this->context->setDefaultGuard('jwt');

        $this->assertSame('jwt', $this->context->getDefaultGuard());
    }

    public function testClearsSingleGuard(): void
    {
        $this->context->setUser($this->fakeUser(1), 'web');
        $this->context->setUser($this->fakeUser(2), 'api');

        $this->context->clear('web');

        $this->assertNull($this->context->user('web'));
        $this->assertNotNull($this->context->user('api'));
    }

    public function testClearsAllGuards(): void
    {
        $this->context->setUser($this->fakeUser(1), 'web');
        $this->context->setUser($this->fakeUser(2), 'api');

        $this->context->clear();

        $this->assertNull($this->context->user('web'));
        $this->assertNull($this->context->user('api'));
    }

    // =========================================================================
    // Guard Scoped Access
    // =========================================================================

    public function testReturnsGuardContextInstance(): void
    {
        $guard = $this->context->guard('api');

        $this->assertInstanceOf(GuardContext::class, $guard);
    }

    public function testGuardProvidesChainedAccessToUser(): void
    {
        $user = $this->fakeUser(42);
        $this->context->setUser($user, 'api');

        $this->assertSame($user, $this->context->guard('api')->user());
        $this->assertSame(42, $this->context->guard('api')->id());
    }

    public function testGuardIsolatesDataBetweenGuards(): void
    {
        $webUser = $this->fakeUser(1)->setRoles(['user']);
        $apiUser = $this->fakeUser(2)->setRoles(['admin']);

        $this->context->setUser($webUser, 'web');
        $this->context->setUser($apiUser, 'api');

        $this->assertTrue($this->context->guard('web')->hasRole('user'));
        $this->assertFalse($this->context->guard('web')->hasRole('admin'));
        $this->assertTrue($this->context->guard('api')->hasRole('admin'));
        $this->assertFalse($this->context->guard('api')->hasRole('user'));
    }

    // =========================================================================
    // Check/Guest Aliases
    // =========================================================================

    public function testCheckIsAliasForIsAuthenticated(): void
    {
        $this->assertFalse($this->context->check());
        $this->assertFalse($this->context->check('api'));

        $this->context->setUser($this->fakeUser(1), 'api');

        $this->assertTrue($this->context->check('api'));
        $this->assertFalse($this->context->check('web'));
    }

    public function testGuestIsAliasForIsGuest(): void
    {
        $this->assertTrue($this->context->guest());
        $this->assertTrue($this->context->guest('api'));

        $this->context->setUser($this->fakeUser(1), 'api');

        $this->assertFalse($this->context->guest('api'));
        $this->assertTrue($this->context->guest('web'));
    }

    // =========================================================================
    // Multi-Guard Role/Permission Checking
    // =========================================================================

    public function testChecksRoleForSpecificGuard(): void
    {
        $user = $this->fakeUser(1)->setRoles(['admin']);
        $this->context->setUser($user, 'api');

        $this->assertTrue($this->context->hasRole('admin', 'api'));
        $this->assertFalse($this->context->hasRole('admin', 'web'));
    }

    public function testChecksAnyRoleForSpecificGuard(): void
    {
        $user = $this->fakeUser(1)->setRoles(['editor']);
        $this->context->setUser($user, 'web');

        $this->assertTrue($this->context->hasAnyRole(['admin', 'editor'], 'web'));
        $this->assertFalse($this->context->hasAnyRole(['admin', 'editor'], 'api'));
    }

    public function testChecksPermissionForSpecificGuard(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.create']);
        $this->context->setUser($user, 'api');

        $this->assertTrue($this->context->hasPermission('posts.create', 'api'));
        $this->assertFalse($this->context->hasPermission('posts.create', 'web'));
    }

    public function testChecksAnyPermissionForSpecificGuard(): void
    {
        $user = $this->fakeUser(1)->setPermissions(['posts.view']);
        $this->context->setUser($user, 'web');

        $this->assertTrue($this->context->hasAnyPermission(['posts.view', 'posts.edit'], 'web'));
        $this->assertFalse($this->context->hasAnyPermission(['posts.view', 'posts.edit'], 'api'));
    }

    public function testExtractsIdForSpecificGuard(): void
    {
        $this->context->setUser($this->fakeUser(10), 'web');
        $this->context->setUser($this->fakeUser(20), 'api');

        $this->assertSame(10, $this->context->id('web'));
        $this->assertSame(20, $this->context->id('api'));
        $this->assertNull($this->context->id('unknown'));
    }
}
