<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Security;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Security\CsrfProtection;

/**
 * Unit tests for CsrfProtection.
 *
 * @covers \Lalaz\Web\Security\CsrfProtection
 */
class CsrfProtectionTest extends WebUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing CSRF cookies
        unset($_COOKIE['__csrf_token']);
    }

    public function test_generate_token_creates_64_character_hex_string(): void
    {
        $token = CsrfProtection::generateToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_generate_token_sets_cookie(): void
    {
        $token = CsrfProtection::generateToken();

        // Cookie should be set in $_COOKIE superglobal
        $this->assertSame($token, $_COOKIE['__csrf_token']);
    }

    public function test_generate_token_creates_unique_tokens(): void
    {
        $token1 = CsrfProtection::generateToken();

        // Clear and regenerate
        unset($_COOKIE['__csrf_token']);
        $token2 = CsrfProtection::generateToken();

        $this->assertNotSame($token1, $token2);
    }

    public function test_get_token_returns_existing_cookie_token(): void
    {
        $_COOKIE['__csrf_token'] = 'existing_token_value';

        $token = CsrfProtection::getToken();

        $this->assertSame('existing_token_value', $token);
    }

    public function test_get_token_generates_new_token_when_cookie_missing(): void
    {
        unset($_COOKIE['__csrf_token']);

        $token = CsrfProtection::getToken();

        $this->assertSame(64, strlen($token));
    }

    public function test_validate_token_returns_true_for_valid_token_in_body(): void
    {
        $token = CsrfProtection::generateToken();

        $body = ['csrfToken' => $token];

        $this->assertTrue(CsrfProtection::validateToken($body));
    }

    public function test_validate_token_returns_true_for_valid_token_in_headers(): void
    {
        $token = CsrfProtection::generateToken();

        $headers = ['X-CSRF-Token' => $token];

        $this->assertTrue(CsrfProtection::validateToken([], $headers));
    }

    public function test_validate_token_returns_false_when_no_cookie(): void
    {
        unset($_COOKIE['__csrf_token']);

        $this->assertFalse(CsrfProtection::validateToken(['csrfToken' => 'some_token']));
    }

    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        CsrfProtection::generateToken();

        $body = ['csrfToken' => 'wrong_token'];

        $this->assertFalse(CsrfProtection::validateToken($body));
    }

    public function test_validate_token_returns_false_when_no_token_provided(): void
    {
        CsrfProtection::generateToken();

        $this->assertFalse(CsrfProtection::validateToken([]));
    }

    public function test_validate_token_works_with_object_body(): void
    {
        $token = CsrfProtection::generateToken();

        $body = new \stdClass();
        $body->csrfToken = $token;

        $this->assertTrue(CsrfProtection::validateToken($body));
    }

    public function test_validate_token_header_is_case_insensitive(): void
    {
        $token = CsrfProtection::generateToken();

        $headers = ['x-csrf-token' => $token];

        $this->assertTrue(CsrfProtection::validateToken([], $headers));
    }

    public function test_rotate_token_generates_new_token(): void
    {
        $oldToken = CsrfProtection::generateToken();
        $newToken = CsrfProtection::rotateToken();

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(64, strlen($newToken));
    }

    public function test_delete_token_removes_cookie(): void
    {
        CsrfProtection::generateToken();
        $this->assertTrue(isset($_COOKIE['__csrf_token']));

        CsrfProtection::deleteToken();

        $this->assertFalse(isset($_COOKIE['__csrf_token']));
    }

    public function test_get_token_field_name_returns_csrf_token(): void
    {
        $this->assertSame('csrfToken', CsrfProtection::getTokenFieldName());
    }

    public function test_get_token_header_name_returns_x_csrf_token(): void
    {
        $this->assertSame('X-CSRF-Token', CsrfProtection::getTokenHeaderName());
    }
}
