<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Http;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Http\HttpEnvironment;

/**
 * Unit tests for HttpEnvironment.
 *
 * @covers \Lalaz\Web\Http\HttpEnvironment
 */
class HttpEnvironmentTest extends WebUnitTestCase
{
    public function test_is_secure_returns_true_when_https_is_on(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_true_when_https_is_any_truthy_value(): void
    {
        $_SERVER['HTTPS'] = '1';

        $this->assertTrue(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_false_when_https_is_off(): void
    {
        $_SERVER['HTTPS'] = 'off';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_X_FORWARDED_SSL']);
        $_SERVER['SERVER_PORT'] = 80;

        $this->assertFalse(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_true_when_forwarded_proto_is_https(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $this->assertTrue(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_true_when_forwarded_ssl_is_on(): void
    {
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';

        $this->assertTrue(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_true_when_server_port_is_443(): void
    {
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_X_FORWARDED_SSL']);
        $_SERVER['SERVER_PORT'] = 443;

        $this->assertTrue(HttpEnvironment::isSecure());
    }

    public function test_is_secure_returns_false_in_plain_http(): void
    {
        $this->setInsecureEnvironment();
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_X_FORWARDED_SSL']);

        $this->assertFalse(HttpEnvironment::isSecure());
    }

    public function test_is_json_request_returns_true_when_accept_is_json(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $this->assertTrue(HttpEnvironment::isJsonRequest());
    }

    public function test_is_json_request_returns_true_for_ajax_requests(): void
    {
        $this->setAjaxRequest();

        $this->assertTrue(HttpEnvironment::isJsonRequest());
    }

    public function test_is_json_request_returns_false_for_html_requests(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $this->assertFalse(HttpEnvironment::isJsonRequest());
    }

    public function test_is_ajax_returns_true_for_ajax_request(): void
    {
        $this->setAjaxRequest();

        $this->assertTrue(HttpEnvironment::isAjax());
    }

    public function test_is_ajax_returns_false_for_regular_request(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $this->assertFalse(HttpEnvironment::isAjax());
    }

    public function test_get_client_ip_returns_remote_addr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);

        $this->assertSame('192.168.1.100', HttpEnvironment::getClientIp());
    }

    public function test_get_client_ip_prefers_cloudflare_header(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->assertSame('1.2.3.4', HttpEnvironment::getClientIp());
    }

    public function test_get_client_ip_uses_x_forwarded_for(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);

        $this->assertSame('5.6.7.8', HttpEnvironment::getClientIp());
    }

    public function test_get_client_ip_uses_x_real_ip(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = '9.10.11.12';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertSame('9.10.11.12', HttpEnvironment::getClientIp());
    }

    public function test_get_client_ip_returns_default_when_no_ip_found(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['REMOTE_ADDR']);

        $this->assertSame('0.0.0.0', HttpEnvironment::getClientIp());
    }

    public function test_get_method_returns_request_method(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertSame('POST', HttpEnvironment::getMethod());
    }

    public function test_get_method_returns_uppercase(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';

        $this->assertSame('POST', HttpEnvironment::getMethod());
    }

    public function test_get_method_defaults_to_get(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $this->assertSame('GET', HttpEnvironment::getMethod());
    }

    public function test_get_uri_returns_request_uri(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/123';

        $this->assertSame('/users/123', HttpEnvironment::getUri());
    }

    public function test_get_uri_defaults_to_slash(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->assertSame('/', HttpEnvironment::getUri());
    }

    public function test_get_host_returns_http_host(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->assertSame('example.com', HttpEnvironment::getHost());
    }

    public function test_get_host_falls_back_to_server_name(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'api.example.com';

        $this->assertSame('api.example.com', HttpEnvironment::getHost());
    }

    public function test_get_host_defaults_to_localhost(): void
    {
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_NAME']);

        $this->assertSame('localhost', HttpEnvironment::getHost());
    }

    public function test_get_user_agent_returns_user_agent_string(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';

        $this->assertSame('Mozilla/5.0 Test', HttpEnvironment::getUserAgent());
    }

    public function test_get_user_agent_returns_empty_string_when_not_set(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);

        $this->assertSame('', HttpEnvironment::getUserAgent());
    }
}
