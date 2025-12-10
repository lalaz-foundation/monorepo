<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Security;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Security\Fingerprint;

/**
 * Unit tests for Fingerprint.
 *
 * @covers \Lalaz\Web\Security\Fingerprint
 */
class FingerprintTest extends WebUnitTestCase
{
    public function test_for_session_generates_consistent_fingerprint(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
        ];

        $fingerprint1 = Fingerprint::forSession($server);
        $fingerprint2 = Fingerprint::forSession($server);

        $this->assertSame($fingerprint1, $fingerprint2);
    }

    public function test_for_session_generates_different_fingerprints_for_different_agents(): void
    {
        $server1 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $server2 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $fingerprint1 = Fingerprint::forSession($server1);
        $fingerprint2 = Fingerprint::forSession($server2);

        $this->assertNotSame($fingerprint1, $fingerprint2);
    }

    public function test_for_session_returns_sha256_hash(): void
    {
        $fingerprint = Fingerprint::forSession([]);

        $this->assertSame(64, strlen($fingerprint));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }

    public function test_for_session_handles_missing_headers(): void
    {
        $fingerprint = Fingerprint::forSession([]);

        $this->assertIsString($fingerprint);
        $this->assertSame(64, strlen($fingerprint));
    }

    public function test_for_device_generates_consistent_fingerprint(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
            'HTTP_ACCEPT' => 'text/html,application/json',
            'HTTP_CONNECTION' => 'keep-alive',
        ];

        $fingerprint1 = Fingerprint::forDevice($server);
        $fingerprint2 = Fingerprint::forDevice($server);

        $this->assertSame($fingerprint1, $fingerprint2);
    }

    public function test_for_device_is_more_specific_than_for_session(): void
    {
        $server1 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_CONNECTION' => 'keep-alive',
        ];

        $server2 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
            'HTTP_ACCEPT' => 'application/json',  // Different!
            'HTTP_CONNECTION' => 'keep-alive',
        ];

        $session1 = Fingerprint::forSession($server1);
        $session2 = Fingerprint::forSession($server2);

        $device1 = Fingerprint::forDevice($server1);
        $device2 = Fingerprint::forDevice($server2);

        // Session fingerprints should match (don't include HTTP_ACCEPT)
        $this->assertSame($session1, $session2);

        // Device fingerprints should differ
        $this->assertNotSame($device1, $device2);
    }

    public function test_validate_returns_true_for_matching_fingerprint(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $fingerprint = Fingerprint::forSession($server);

        $this->assertTrue(Fingerprint::validate($fingerprint, $server));
    }

    public function test_validate_returns_false_for_non_matching_fingerprint(): void
    {
        $server1 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $server2 = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $fingerprint = Fingerprint::forSession($server1);

        $this->assertFalse(Fingerprint::validate($fingerprint, $server2));
    }

    public function test_validate_uses_timing_safe_comparison(): void
    {
        $server = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];

        $fingerprint = Fingerprint::forSession($server);

        // The method should use hash_equals internally
        // We can only verify the behavior, not the implementation
        $this->assertTrue(Fingerprint::validate($fingerprint, $server));
    }
}
