<?php

declare(strict_types=1);

namespace Lalaz\Web\Security\Middlewares;

use Lalaz\Config\Config;
use Lalaz\Web\Http\Contracts\MiddlewareInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;
use Lalaz\Web\Http\Contracts\ResponseInterface;

/**
 * Security Headers Middleware
 *
 * Adds configurable security headers (HSTS, CSP, frame/XSS/content-type policies).
 * Defaults can be overridden via config or constructor-provided custom headers.
 *
 * @package Lalaz\Web\Security\Middlewares
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Headers to be applied to responses.
     *
     * @var array<string, string|null|bool>
     */
    private array $headers = [];

    /**
     * Create a new SecurityHeadersMiddleware instance.
     *
     * @param array<string, string|null|bool> $customHeaders Custom headers to override defaults.
     */
    public function __construct(array $customHeaders = [])
    {
        $defaults = [
            'X-Frame-Options' =>
                Config::get('security.headers.frame_options') ?:
                Config::get('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
            'X-Content-Type-Options' =>
                Config::get('security.headers.content_type_options') ?:
                Config::get('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
            'X-XSS-Protection' =>
                Config::get('security.headers.xss_protection') ?:
                Config::get('SECURITY_XSS_PROTECTION', '1; mode=block'),
            'Referrer-Policy' =>
                Config::get('security.headers.referrer_policy') ?:
                Config::get(
                    'SECURITY_REFERRER_POLICY',
                    'strict-origin-when-cross-origin',
                ),
            'Permissions-Policy' =>
                Config::get('security.headers.permissions_policy') ?:
                Config::get('SECURITY_PERMISSIONS_POLICY'),
        ];

        $hstsEnabled =
            Config::get('security.hsts.enabled') ??
            Config::get('SECURITY_HSTS_ENABLED', false);

        if ($hstsEnabled) {
            $defaults['Strict-Transport-Security'] =
                Config::get('security.hsts.value') ?:
                Config::get(
                    'SECURITY_HSTS_VALUE',
                    'max-age=31536000; includeSubDomains',
                );
        }

        $csp = Config::get('security.csp') ?: Config::get('SECURITY_CSP');
        if ($csp) {
            $defaults['Content-Security-Policy'] = $csp;
        }

        $this->headers = array_merge($defaults, $customHeaders);
    }

    /**
     * Apply headers before passing control to the next middleware.
     *
     * @param RequestInterface $request The HTTP request.
     * @param ResponseInterface $response The HTTP response.
     * @param callable $next The next middleware in the chain.
     * @return mixed
     */
    public function handle(
        RequestInterface $request,
        ResponseInterface $response,
        callable $next,
    ): mixed {
        foreach ($this->headers as $header => $value) {
            if ($value !== null && $value !== false) {
                header("$header: $value", true);
            }
        }

        return $next($request, $response);
    }

    /**
     * Build an instance with explicit headers (overrides defaults).
     *
     * @param array<string, string|null|bool> $headers Headers to set.
     * @return self
     */
    public static function with(array $headers): self
    {
        return new self($headers);
    }

    /**
     * Minimal but safe defaults for most sites.
     *
     * @return self
     */
    public static function minimal(): self
    {
        return new self([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => false,
            'Referrer-Policy' => false,
            'Permissions-Policy' => false,
        ]);
    }

    /**
     * Recommended stricter defaults (DENY framing, basic CSP-like stance).
     *
     * @return self
     */
    public static function recommended(): self
    {
        return new self([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ]);
    }

    /**
     * Strict profile with HSTS and CSP suited for apps controlling all assets.
     *
     * @return self
     */
    public static function strict(): self
    {
        return new self([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' =>
                'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()',
            'Strict-Transport-Security' =>
                'max-age=63072000; includeSubDomains; preload',
            'Content-Security-Policy' =>
                "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        ]);
    }

    /**
     * Profile tailored for JSON APIs (no CSP, minimal headers).
     *
     * @return self
     */
    public static function api(): self
    {
        return new self([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-XSS-Protection' => false,
            'Permissions-Policy' => false,
        ]);
    }

    /**
     * Expose the headers configured on this instance.
     *
     * @return array<string, string|null|bool>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
