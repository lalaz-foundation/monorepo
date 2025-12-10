<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Runtime\Http;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Runtime\Http\ExceptionHandler;
use Lalaz\Web\Http\Contracts\ExceptionRendererInterface;
use Lalaz\Web\Http\Contracts\ExceptionReporterInterface;
use Lalaz\Exceptions\ExceptionResponse;
use Lalaz\Exceptions\ValidationException;
use Lalaz\Web\Http\Request;

class CustomRenderer implements ExceptionRendererInterface
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

class CustomReporter implements ExceptionReporterInterface
{
    public static bool $reported = false;

    public function report(\Throwable $e, Request $request, array $context): void
    {
        self::$reported = true;
    }
}

class ExceptionHandlerTest extends FrameworkUnitTestCase
{
    private function createRequest(array $headers = []): Request
    {
        return new Request(
            [],
            [],
            [],
            null,
            array_merge(["Host" => "example.test"], $headers),
            [],
            [],
            "GET",
            ["REQUEST_URI" => "/", "REQUEST_METHOD" => "GET"],
        );
    }

    // ============================================
    // JSON Response Tests
    // ============================================

    public function testreturnsJsonResponseForJsonAcceptHeader(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $request = $this->createRequest(["Accept" => "application/json"]);

        $response = $handler->handle(new \Exception('Test'), $request);

        $this->assertSame(500, $response->statusCode());
    }

    // ============================================
    // Debug Mode Tests
    // ============================================

    public function testincludesContextInDebugMode(): void
    {
        $handler = new ExceptionHandler(debug: true);
        $request = $this->createRequest(["Accept" => "application/json"]);

        $response = $handler->handle(new \Exception('Test'), $request);

        $this->assertSame(500, $response->statusCode());
    }

    public function testhandlesExceptionInProductionMode(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $request = $this->createRequest(["Accept" => "application/json"]);

        $response = $handler->handle(new \Exception('Test'), $request);

        $this->assertSame(500, $response->statusCode());
    }

    // ============================================
    // Custom Renderer Tests
    // ============================================

    public function testcustomRendererCanBePrepended(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $handler->prependRenderer(new CustomRenderer());
        $request = $this->createRequest();

        $response = $handler->handle(new \Exception('Test'), $request);

        $this->assertSame(200, $response->statusCode());
        $this->assertSame('custom', $response->body());
    }

    public function testcustomRendererCanBeAdded(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $handler->addRenderer(new CustomRenderer());
        $request = $this->createRequest();

        $response = $handler->handle(new \Exception('Test'), $request);

        // The default renderers will handle it first
        $this->assertSame(500, $response->statusCode());
    }

    // ============================================
    // Custom Reporter Tests
    // ============================================

    public function testcustomReporterCanBeRegistered(): void
    {
        CustomReporter::$reported = false;
        $handler = new ExceptionHandler(debug: false);
        $handler->addReporter(new CustomReporter());
        $request = $this->createRequest(["Accept" => "application/json"]);

        $handler->handle(new \Exception('Test'), $request);

        $this->assertTrue(CustomReporter::$reported);
    }

    // ============================================
    // Validation Exception Tests
    // ============================================

    public function test_validation_exception_returns_422_status(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $request = $this->createRequest(["Accept" => "application/json"]);

        $response = $handler->handle(
            new ValidationException(['email' => 'Invalid email']),
            $request
        );

        $this->assertSame(422, $response->statusCode());
    }

    public function testvalidationExceptionBodyContainsErrors(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $request = $this->createRequest(["Accept" => "application/json"]);

        $response = $handler->handle(
            new ValidationException(['email' => 'Invalid email']),
            $request
        );

        $body = $response->body();
        $this->assertIsArray($body);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('email', $body['errors']);
    }
}
