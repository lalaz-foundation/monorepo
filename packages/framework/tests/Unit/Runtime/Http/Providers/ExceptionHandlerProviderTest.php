<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Runtime\Http\Providers;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Container\Container;
use Lalaz\Runtime\Http\Providers\ExceptionHandlerProvider;
use Lalaz\Runtime\Http\ExceptionHandler;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Web\Http\Request;

class ProviderRenderer implements ExceptionRendererInterface
{
    public function canRender(\Throwable $e, Request $request): bool
    {
        return true;
    }

    public function render(\Throwable $e, Request $request): ExceptionResponse
    {
        return new ExceptionResponse(200, [], 'custom', false);
    }
}

class ProviderReporter implements ExceptionReporterInterface
{
    public function report(\Throwable $e, Request $request, array $context): void
    {
        // noop
    }
}

class ExceptionHandlerProviderTest extends FrameworkUnitTestCase
{
    public function testregistersRenderersFromConfig(): void
    {
        $container = new Container();
        $container->instance('config', [
            'errors' => [
                'renderers' => [ProviderRenderer::class],
                'reporters' => [],
            ],
        ]);

        $provider = new ExceptionHandlerProvider($container);
        $provider->register();

        $handler = $container->resolve(ExceptionHandler::class);
        $this->assertNotNull($handler);
    }

    public function testregistersReportersFromConfig(): void
    {
        $container = new Container();
        $container->instance('config', [
            'errors' => [
                'renderers' => [],
                'reporters' => [ProviderReporter::class],
            ],
        ]);

        $provider = new ExceptionHandlerProvider($container);
        $provider->register();

        $handler = $container->resolve(ExceptionHandler::class);
        $this->assertNotNull($handler);
    }
}
