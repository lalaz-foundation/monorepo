<?php declare(strict_types=1);

namespace Lalaz\Auth\Tests\Common;

use Lalaz\Web\Http\Request;
use Lalaz\Web\Http\Response;

/**
 * FakeRequest for testing middlewares.
 *
 * Extends the real Request class to ensure type compatibility.
 */
class FakeRequest extends Request
{
    /**
     * The authenticated user for testing.
     */
    public mixed $user = null;

    public function __construct(string $method = 'GET', string $uri = '/')
    {
        parent::__construct(
            routeParams: [],
            queryParams: [],
            formBody: [],
            rawJsonBody: null,
            headers: [],
            cookies: [],
            files: [],
            method: $method,
            server: ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method],
        );
    }

    /**
     * Create a request with an authenticated user.
     */
    public static function withUser(mixed $user, string $method = 'GET', string $uri = '/'): self
    {
        $request = new self($method, $uri);
        $request->user = $user;
        return $request;
    }
}

/**
 * FakeResponse for testing middlewares.
 *
 * Extends the real Response class to ensure type compatibility.
 */
class FakeResponse extends Response
{
    public ?string $redirectUrl = null;

    public function __construct()
    {
        parent::__construct('localhost');
    }

    /**
     * Override redirect to track the URL without actually redirecting.
     */
    public function redirect(string $url, bool $allowExternal = false): void
    {
        $this->redirectUrl = $url;
        $this->status(302);
    }
}
