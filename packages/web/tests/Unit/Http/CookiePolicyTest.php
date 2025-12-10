<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Http;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Http\CookiePolicy;

/**
 * Unit tests for CookiePolicy.
 *
 * @covers \Lalaz\Web\Http\CookiePolicy
 */
class CookiePolicyTest extends WebUnitTestCase
{
    /**
     * Note: Many CookiePolicy methods cannot be fully tested in unit tests
     * because they use PHP's setcookie() function which requires
     * headers not to be sent. These tests focus on what can be tested.
     */

    public function test_get_returns_cookie_value(): void
    {
        $_COOKIE['test_cookie'] = 'test_value';

        $this->assertSame('test_value', CookiePolicy::get('test_cookie'));
    }

    public function test_get_returns_default_for_missing_cookie(): void
    {
        unset($_COOKIE['missing_cookie']);

        $this->assertNull(CookiePolicy::get('missing_cookie'));
    }

    public function test_get_returns_provided_default_for_missing_cookie(): void
    {
        unset($_COOKIE['missing_cookie']);

        $this->assertSame('default_value', CookiePolicy::get('missing_cookie', 'default_value'));
    }

    public function test_has_returns_true_for_existing_cookie(): void
    {
        $_COOKIE['existing_cookie'] = 'value';

        $this->assertTrue(CookiePolicy::has('existing_cookie'));
    }

    public function test_has_returns_false_for_missing_cookie(): void
    {
        unset($_COOKIE['missing_cookie']);

        $this->assertFalse(CookiePolicy::has('missing_cookie'));
    }

    public function test_has_returns_true_for_empty_string_cookie(): void
    {
        $_COOKIE['empty_cookie'] = '';

        $this->assertTrue(CookiePolicy::has('empty_cookie'));
    }

    public function test_has_returns_true_for_zero_value_cookie(): void
    {
        $_COOKIE['zero_cookie'] = '0';

        $this->assertTrue(CookiePolicy::has('zero_cookie'));
    }
}
