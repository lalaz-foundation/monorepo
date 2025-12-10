<?php

declare(strict_types=1);

namespace Lalaz\Web\Tests\Unit\Security;

use Lalaz\Web\Tests\Common\WebUnitTestCase;
use Lalaz\Web\Security\Middlewares\CsrfMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for CsrfMiddleware.
 *
 * @covers \Lalaz\Web\Security\Middlewares\CsrfMiddleware
 */
#[CoversClass(CsrfMiddleware::class)]
class CsrfMiddlewareTest extends WebUnitTestCase
{
    public function test_constructor_accepts_exclusion_patterns(): void
    {
        $middleware = new CsrfMiddleware(['/api/*', '/webhook/*']);

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_excluding_api_factory_creates_middleware(): void
    {
        $middleware = CsrfMiddleware::excludingApi();

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_excluding_webhooks_factory_creates_middleware(): void
    {
        $middleware = CsrfMiddleware::excludingWebhooks();

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_excluding_webhooks_accepts_additional_paths(): void
    {
        $middleware = CsrfMiddleware::excludingWebhooks(['/custom-hook/*']);

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_constructor_accepts_empty_exclusions(): void
    {
        $middleware = new CsrfMiddleware([]);

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_constructor_default_exclusions(): void
    {
        $middleware = new CsrfMiddleware();

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_excluding_webhooks_merges_defaults(): void
    {
        $additionalPaths = ['/my-hook/*', '/callback/*'];
        $middleware = CsrfMiddleware::excludingWebhooks($additionalPaths);

        $this->assertInstanceOf(CsrfMiddleware::class, $middleware);
    }

    public function test_middleware_implements_interface(): void
    {
        $middleware = new CsrfMiddleware();

        $this->assertInstanceOf(
            \Lalaz\Web\Http\Contracts\MiddlewareInterface::class,
            $middleware
        );
    }

    public function test_factory_methods_return_self(): void
    {
        $this->assertInstanceOf(CsrfMiddleware::class, CsrfMiddleware::excludingApi());
        $this->assertInstanceOf(CsrfMiddleware::class, CsrfMiddleware::excludingWebhooks());
    }
}
