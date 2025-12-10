<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Stubs;

use Lalaz\Exceptions\FrameworkException;
use Lalaz\Exceptions\ValidationException;
use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Attribute\Route;

/**
 * Stub controller for testing attribute-based routing.
 *
 * Provides endpoints for all HTTP methods and common scenarios.
 */
class StubController
{
    #[Route(path: '/attr-config', method: 'GET')]
    public function index(Response $response): void
    {
        $response->setBody('attr');
    }

    #[Route(path: '/attr-config', method: 'POST')]
    public function store(Request $request, Response $response): void
    {
        $payload = $request->body();

        if (!is_array($payload) || ($payload['name'] ?? '') === '') {
            throw new ValidationException(['name' => ['Required']]);
        }

        $response->json([
            'method' => 'POST',
            'payload' => $payload,
        ]);
    }

    #[Route(path: '/attr-config/{id}', method: 'PUT')]
    public function update(string $id, Request $request, Response $response): void
    {
        $response->json([
            'method' => 'PUT',
            'id' => $id,
            'payload' => $request->body(),
        ]);
    }

    #[Route(path: '/attr-config/{id}', method: 'PATCH')]
    public function patch(string $id, Request $request, Response $response): void
    {
        $response->json([
            'method' => 'PATCH',
            'id' => $id,
            'payload' => $request->body(),
        ]);
    }

    #[Route(path: '/attr-config/{id}', method: 'DELETE')]
    public function destroy(string $id, Response $response): void
    {
        $response->json([
            'method' => 'DELETE',
            'id' => $id,
        ]);
    }

    #[Route(path: '/attr-config', method: 'OPTIONS')]
    public function options(Response $response): void
    {
        $response->json([
            'method' => 'OPTIONS',
        ]);
    }

    #[Route(path: '/framework-error', method: 'GET')]
    public function frameworkError(): void
    {
        throw new FrameworkException('integration failure');
    }
}
