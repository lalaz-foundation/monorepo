<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Factories;

use Lalaz\Web\Http\Response;

/**
 * Factory for creating Response objects in tests.
 */
final class ResponseFactory
{
    /**
     * Create a new Response instance.
     */
    public static function create(string $host = 'localhost'): Response
    {
        return new Response($host);
    }

    /**
     * Create a Response with JSON content already set.
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200, string $host = 'localhost'): Response
    {
        $response = new Response($host);
        $response->json($data, $statusCode);

        return $response;
    }
}
