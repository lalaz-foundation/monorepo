<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;

use InvalidArgumentException;
use Lalaz\Auth\AuthManager;
use Lalaz\Auth\Contracts\GuardInterface;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\AuthManager::class)]
final class AuthManagerTest extends AuthUnitTestCase
{
    private AuthManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new AuthManager();
    }

    // =========================================================================
    // Guard Resolution
    // =========================================================================

    public function testThrowsExceptionForUndefinedGuard(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->guard('undefined');
    }

    public function testResolvesRegisteredGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));

        $guard = $this->manager->guard('session');

        $this->assertInstanceOf(GuardInterface::class, $guard);
        $this->assertInstanceOf(SessionGuard::class, $guard);
    }

    public function testCachesGuardInstances(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));

        $guard1 = $this->manager->guard('session');
        $guard2 = $this->manager->guard('session');

        $this->assertSame($guard1, $guard2);
    }

    public function testReturnsDefaultGuardWhenNoNameSpecified(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $guard = $this->manager->guard();

        $this->assertInstanceOf(SessionGuard::class, $guard);
    }

    // =========================================================================
    // Guard Registration
    // =========================================================================

    public function testExtendsWithClosure(): void
    {
        $this->manager->extend('custom', fn() => new SessionGuard($this->fakeSession(), $this->fakeUserProvider()));

        $this->assertTrue($this->manager->hasGuard('custom'));
    }

    public function testRegistersGuardInstanceDirectly(): void
    {
        $guard = new SessionGuard($this->fakeSession(), $this->fakeUserProvider());

        $this->manager->register('session', $guard);

        $this->assertSame($guard, $this->manager->guard('session'));
    }

    public function testListsGuardNames(): void
    {
        $this->manager->extend('session', fn() => new SessionGuard($this->fakeSession(), $this->fakeUserProvider()));
        $this->manager->extend('jwt', fn() => new JwtGuard(
            new JwtEncoder('secret-32-characters-long!!!!!'),
            new JwtBlacklist(),
            $this->fakeUserProvider()
        ));

        $names = $this->manager->getGuardNames();

        $this->assertContains('session', $names);
        $this->assertContains('jwt', $names);
    }

    // =========================================================================
    // Default Guard
    // =========================================================================

    public function testHasSessionAsDefaultGuard(): void
    {
        $this->assertSame('session', $this->manager->getDefaultGuard());
    }

    public function testSetsDefaultGuard(): void
    {
        $this->manager->setDefaultGuard('jwt');

        $this->assertSame('jwt', $this->manager->getDefaultGuard());
    }

    // =========================================================================
    // Forget Guards
    // =========================================================================

    public function testForgetsSingleGuard(): void
    {
        $this->manager->extend('session', fn() => new SessionGuard($this->fakeSession(), $this->fakeUserProvider()));

        $this->manager->guard('session'); // Resolve it first
        $this->manager->forgetGuard('session');

        // Guard creator still exists, but instance is cleared
        $this->assertTrue($this->manager->hasGuard('session'));
    }

    public function testForgetsAllGuards(): void
    {
        $this->manager->extend('session', fn() => new SessionGuard($this->fakeSession(), $this->fakeUserProvider()));

        $this->manager->guard('session');
        $this->manager->forgetGuards();

        $this->assertTrue($this->manager->hasGuard('session'));
    }

    // =========================================================================
    // Delegation Methods
    // =========================================================================

    public function testDelegatesCheckToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->assertFalse($this->manager->check());

        $this->manager->login($user);

        $this->assertTrue($this->manager->check());
    }

    public function testDelegatesGuestToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->assertTrue($this->manager->guest());

        $this->manager->login($user);

        $this->assertFalse($this->manager->guest());
    }

    public function testDelegatesUserToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->assertNull($this->manager->user());

        $this->manager->login($user);

        $this->assertSame($user, $this->manager->user());
    }

    public function testDelegatesIdToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->assertNull($this->manager->id());

        $this->manager->login($user);

        $this->assertSame(123, $this->manager->id());
    }

    public function testDelegatesLoginToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->manager->login($user);

        $this->assertTrue($this->manager->check());
    }

    public function testDelegatesLogoutToDefaultGuard(): void
    {
        $session = $this->fakeSession();
        $provider = $this->fakeUserProvider();
        $user = $this->fakeUser(123);
        $provider->addUser($user);

        $this->manager->extend('session', fn() => new SessionGuard($session, $provider));
        $this->manager->setDefaultGuard('session');

        $this->manager->login($user);
        $this->manager->logout();

        $this->assertFalse($this->manager->check());
    }

    // =========================================================================
    // Multiple Guards
    // =========================================================================

    public function testManagesMultipleGuardsIndependently(): void
    {
        $sessionProvider = $this->fakeUserProvider();
        $jwtProvider = $this->fakeUserProvider();

        $sessionUser = $this->fakeUser(1);
        $jwtUser = $this->fakeUser(2);

        $sessionProvider->addUser($sessionUser);
        $jwtProvider->addUser($jwtUser);

        $this->manager->extend('session', fn() => new SessionGuard($this->fakeSession(), $sessionProvider));
        $this->manager->extend('jwt', fn() => new JwtGuard(
            new JwtEncoder('secret-32-characters-long!!!!!'),
            new JwtBlacklist(),
            $jwtProvider
        ));

        // Login different users to different guards
        $this->manager->guard('session')->login($sessionUser);
        $this->manager->guard('jwt')->login($jwtUser);

        $this->assertSame(1, $this->manager->guard('session')->id());
        $this->assertSame(2, $this->manager->guard('jwt')->id());
    }
}
