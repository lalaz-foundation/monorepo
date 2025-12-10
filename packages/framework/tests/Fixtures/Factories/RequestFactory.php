<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Factories;

use Lalaz\Web\Http\Request;

/**
 * Factory for creating Request objects in tests.
 */
final class RequestFactory
{
    /**
     * Create a GET request.
     *
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public static function get(
        string $uri = '/',
        array $query = [],
        array $headers = [],
    ): Request {
        return self::create('GET', $uri, null, $query, $headers);
    }

    /**
     * Create a POST request with JSON body.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function postJson(
        string $uri = '/',
        ?array $json = null,
        array $headers = [],
    ): Request {
        $body = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
        $headers['Content-Type'] = 'application/json';

        return self::create('POST', $uri, $body, [], $headers);
    }

    /**
     * Create a POST request with form data.
     *
     * @param array<string, mixed> $form
     * @param array<string, string> $headers
     */
    public static function postForm(
        string $uri = '/',
        array $form = [],
        array $headers = [],
    ): Request {
        return new Request(
            [],
            [],
            $form,
            null,
            array_merge(['Host' => 'localhost', 'Content-Type' => 'application/x-www-form-urlencoded'], $headers),
            [],
            [],
            'POST',
            ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'POST'],
        );
    }

    /**
     * Create a PUT request with JSON body.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function putJson(
        string $uri = '/',
        ?array $json = null,
        array $headers = [],
    ): Request {
        $body = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
        $headers['Content-Type'] = 'application/json';

        return self::create('PUT', $uri, $body, [], $headers);
    }

    /**
     * Create a PATCH request with JSON body.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    public static function patchJson(
        string $uri = '/',
        ?array $json = null,
        array $headers = [],
    ): Request {
        $body = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
        $headers['Content-Type'] = 'application/json';

        return self::create('PATCH', $uri, $body, [], $headers);
    }

    /**
     * Create a DELETE request.
     *
     * @param array<string, string> $headers
     */
    public static function delete(
        string $uri = '/',
        array $headers = [],
    ): Request {
        return self::create('DELETE', $uri, null, [], $headers);
    }

    /**
     * Create a HEAD request.
     *
     * @param array<string, string> $headers
     */
    public static function head(
        string $uri = '/',
        array $headers = [],
    ): Request {
        return self::create('HEAD', $uri, null, [], $headers);
    }

    /**
     * Create an OPTIONS request.
     *
     * @param array<string, string> $headers
     */
    public static function options(
        string $uri = '/',
        array $headers = [],
    ): Request {
        return self::create('OPTIONS', $uri, null, [], $headers);
    }

    /**
     * Create a request with the specified method.
     *
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $uri = '/',
        ?string $body = null,
        array $query = [],
        array $headers = [],
    ): Request {
        $headers = array_merge(['Host' => 'localhost'], $headers);

        return new Request(
            [],
            $query,
            [],
            $body,
            $headers,
            [],
            [],
            strtoupper($method),
            ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => strtoupper($method)],
        );
    }
}
