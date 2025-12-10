<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Controllers;

use Lalaz\Web\Http\Response;
use Lalaz\Web\Routing\Attribute\Route;

class ConfigRouteController
{
    #[Route(path: "/attr-config", method: "GET")]
    public function handle(Response $response): void
    {
        $response->setBody("attr");
    }

    #[Route(path: "/attr-config", method: "POST")]
    public function store(
        \Lalaz\Web\Http\Request $request,
        Response $response,
    ): void {
        $payload = $request->body();

        if (!is_array($payload) || ($payload["name"] ?? "") === "") {
            throw new \Lalaz\Exceptions\ValidationException([
                "name" => ["Required"],
            ]);
        }

        $response->json([
            "method" => "POST",
            "payload" => $payload,
        ]);
    }

    #[Route(path: "/attr-config/{id}", method: "PUT")]
    public function update(
        string $id,
        \Lalaz\Web\Http\Request $request,
        Response $response,
    ): void {
        $response->json([
            "method" => "PUT",
            "id" => $id,
            "payload" => $request->body(),
        ]);
    }

    #[Route(path: "/attr-config/{id}", method: "PATCH")]
    public function patch(
        string $id,
        \Lalaz\Web\Http\Request $request,
        Response $response,
    ): void {
        $response->json([
            "method" => "PATCH",
            "id" => $id,
            "payload" => $request->body(),
        ]);
    }

    #[Route(path: "/attr-config/{id}", method: "DELETE")]
    public function destroy(string $id, Response $response): void
    {
        $response->json([
            "method" => "DELETE",
            "id" => $id,
        ]);
    }

    #[Route(path: "/attr-config", method: "OPTIONS")]
    public function options(Response $response): void
    {
        $response->json([
            "method" => "OPTIONS",
        ]);
    }

    #[Route(path: "/framework-error", method: "GET")]
    public function frameworkError(): void
    {
        throw new \Lalaz\Exceptions\FrameworkException("integration failure");
    }
}
