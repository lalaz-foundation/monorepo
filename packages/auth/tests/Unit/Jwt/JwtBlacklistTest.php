<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Unit\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;

use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Tests\Common\AuthUnitTestCase;

#[CoversClass(\Lalaz\Auth\Jwt\JwtBlacklist::class)]
final class JwtBlacklistTest extends AuthUnitTestCase
{
    private JwtBlacklist $blacklist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blacklist = new JwtBlacklist();
        // Clear static memory blacklist before each test
        $this->blacklist->clear();
    }

    // =========================================================================
    // Adding Tokens
    // =========================================================================

    public function testAddsTokenToBlacklist(): void
    {
        $jti = 'unique-token-id';
        $expiration = time() + 3600; // expires in 1 hour

        $this->blacklist->add($jti, $expiration);

        $this->assertTrue($this->blacklist->isBlacklisted($jti));
    }

    public function testAddsMultipleTokens(): void
    {
        $expiration = time() + 3600;

        $this->blacklist->add('jti-1', $expiration);
        $this->blacklist->add('jti-2', $expiration);
        $this->blacklist->add('jti-3', $expiration);

        $this->assertTrue($this->blacklist->isBlacklisted('jti-1'));
        $this->assertTrue($this->blacklist->isBlacklisted('jti-2'));
        $this->assertTrue($this->blacklist->isBlacklisted('jti-3'));
    }

    // =========================================================================
    // Checking Blacklist Status
    // =========================================================================

    public function testReturnsFalseForNonBlacklistedToken(): void
    {
        $this->assertFalse($this->blacklist->isBlacklisted('not-blacklisted-jti'));
    }

    public function testHandlesEmptyBlacklist(): void
    {
        $this->assertFalse($this->blacklist->isBlacklisted('any-jti'));
    }

    public function testHasIsAliasForIsBlacklisted(): void
    {
        $jti = 'test-jti';
        $expiration = time() + 3600;

        $this->assertFalse($this->blacklist->has($jti));

        $this->blacklist->add($jti, $expiration);

        $this->assertTrue($this->blacklist->has($jti));
    }

    // =========================================================================
    // Removing Tokens
    // =========================================================================

    public function testRemovesTokenFromBlacklist(): void
    {
        $jti = 'jti-to-remove';
        $expiration = time() + 3600;

        $this->blacklist->add($jti, $expiration);
        $this->blacklist->remove($jti);

        $this->assertFalse($this->blacklist->isBlacklisted($jti));
    }

    public function testRemoveNonExistentTokenDoesNotThrow(): void
    {
        // Should not throw an exception
        $this->blacklist->remove('nonexistent-jti');

        $this->assertFalse($this->blacklist->isBlacklisted('nonexistent-jti'));
    }

    // =========================================================================
    // Clearing Blacklist
    // =========================================================================

    public function testClearsAllTokens(): void
    {
        $expiration = time() + 3600;

        $this->blacklist->add('jti-1', $expiration);
        $this->blacklist->add('jti-2', $expiration);
        $this->blacklist->add('jti-3', $expiration);

        $this->blacklist->clear();

        $this->assertFalse($this->blacklist->isBlacklisted('jti-1'));
        $this->assertFalse($this->blacklist->isBlacklisted('jti-2'));
        $this->assertFalse($this->blacklist->isBlacklisted('jti-3'));
    }

    // =========================================================================
    // Expiration and Auto-Cleanup
    // =========================================================================

    public function testExpiredTokenIsAutomaticallyRemoved(): void
    {
        $jti = 'expired-jti';
        $expiration = time() - 1; // Already expired

        $this->blacklist->add($jti, $expiration);

        // Should return false because token is expired
        $this->assertFalse($this->blacklist->isBlacklisted($jti));
    }

    public function testCleanupReturnsNumberOfRemovedEntries(): void
    {
        $past = time() - 100;
        $future = time() + 3600;

        $this->blacklist->add('expired-1', $past);
        $this->blacklist->add('expired-2', $past);
        $this->blacklist->add('valid', $future);

        $cleaned = $this->blacklist->cleanup();

        $this->assertSame(2, $cleaned);
    }

    public function testCleanupRemovesExpiredTokens(): void
    {
        $past = time() - 100;
        $future = time() + 3600;

        $this->blacklist->add('expired', $past);
        $this->blacklist->add('valid', $future);

        $this->blacklist->cleanup();

        $this->assertFalse($this->blacklist->isBlacklisted('expired'));
        $this->assertTrue($this->blacklist->isBlacklisted('valid'));
    }

    // =========================================================================
    // Cache Integration
    // =========================================================================

    public function testWorksWithCacheInstance(): void
    {
        $cache = new class {
            private array $store = [];

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function set(string $key, mixed $value, int $ttl = 0): void
            {
                $this->store[$key] = $value;
            }

            public function delete(string $key): void
            {
                unset($this->store[$key]);
            }
        };

        $blacklist = new JwtBlacklist($cache);
        $jti = 'cached-jti';
        $expiration = time() + 3600;

        $blacklist->add($jti, $expiration);

        $this->assertTrue($blacklist->isBlacklisted($jti));

        $blacklist->remove($jti);

        $this->assertFalse($blacklist->isBlacklisted($jti));
    }

    // =========================================================================
    // Constructor
    // =========================================================================

    public function testCreatesWithNullCache(): void
    {
        $blacklist = new JwtBlacklist(null);

        $this->assertInstanceOf(JwtBlacklist::class, $blacklist);
    }

    public function testCreatesWithDefaultParameters(): void
    {
        $blacklist = new JwtBlacklist();

        $this->assertInstanceOf(JwtBlacklist::class, $blacklist);
    }
}
