<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Security;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for SecurityHeadersMiddleware.
 *
 * @covers \Lalaz\Web\Security\Middlewares\SecurityHeadersMiddleware
 */
#[CoversClass(SecurityHeadersMiddleware::class)]
class SecurityHeadersMiddlewareTest extends WebUnitTestCase
{
    public function test_default_headers_are_set(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
    }

    public function test_custom_headers_override_defaults(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'X-Frame-Options' => 'DENY',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertSame('DENY', $headers['X-Frame-Options']);
    }

    public function test_with_factory_creates_instance(): void
    {
        $middleware = SecurityHeadersMiddleware::with([
            'Custom-Header' => 'value',
        ]);

        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Custom-Header', $headers);
        $this->assertSame('value', $headers['Custom-Header']);
    }

    public function test_minimal_profile_sets_minimal_headers(): void
    {
        $middleware = SecurityHeadersMiddleware::minimal();
        $headers = $middleware->getHeaders();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertFalse($headers['X-XSS-Protection']);
        $this->assertFalse($headers['Referrer-Policy']);
        $this->assertFalse($headers['Permissions-Policy']);
    }

    public function test_recommended_profile_sets_stricter_headers(): void
    {
        $middleware = SecurityHeadersMiddleware::recommended();
        $headers = $middleware->getHeaders();

        $this->assertSame('DENY', $headers['X-Frame-Options']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('1; mode=block', $headers['X-XSS-Protection']);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
        $this->assertStringContainsString('geolocation=()', $headers['Permissions-Policy']);
    }

    public function test_strict_profile_includes_hsts(): void
    {
        $middleware = SecurityHeadersMiddleware::strict();
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertStringContainsString('max-age=', $headers['Strict-Transport-Security']);
        $this->assertStringContainsString('includeSubDomains', $headers['Strict-Transport-Security']);
    }

    public function test_strict_profile_includes_csp(): void
    {
        $middleware = SecurityHeadersMiddleware::strict();
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
    }

    public function test_api_profile_sets_minimal_headers(): void
    {
        $middleware = SecurityHeadersMiddleware::api();
        $headers = $middleware->getHeaders();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
        $this->assertSame('no-referrer', $headers['Referrer-Policy']);
        $this->assertFalse($headers['X-XSS-Protection']);
    }

    public function test_handle_method_exists(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        $this->assertTrue(method_exists($middleware, 'handle'));
    }

    public function test_false_value_disables_header(): void
    {
        $middleware = SecurityHeadersMiddleware::with([
            'X-XSS-Protection' => false,
        ]);

        $headers = $middleware->getHeaders();

        $this->assertFalse($headers['X-XSS-Protection']);
    }

    public function test_null_value_disables_header(): void
    {
        $middleware = SecurityHeadersMiddleware::with([
            'X-Custom' => null,
        ]);

        $headers = $middleware->getHeaders();

        $this->assertNull($headers['X-Custom']);
    }

    public function test_get_headers_returns_all_configured_headers(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'Extra-Header' => 'extra-value',
        ]);

        $headers = $middleware->getHeaders();

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Extra-Header', $headers);
    }
}
