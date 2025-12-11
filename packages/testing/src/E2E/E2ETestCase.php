<?php

declare(strict_types=1);

namespace Lalaz\Testing\E2E;

use Lalaz\Testing\Integration\IntegrationTestCase;

/**
 * Base TestCase for End-to-End Tests
 *
 * Extends IntegrationTestCase with HTTP client capabilities for
 * testing full request/response cycles. Use this when testing
 * complete user flows through your application.
 *
 * Features:
 * - Full application bootstrapping
 * - HTTP request simulation
 * - Response assertions
 * - Session and cookie handling
 *
 * @example
 * ```php
 * class UserRegistrationE2ETest extends E2ETestCase
 * {
 *     protected function getPackageProviders(): array
 *     {
 *         return [
 *             AuthServiceProvider::class,
 *             WebServiceProvider::class,
 *         ];
 *     }
 *
 *     public function testUserCanRegister(): void
 *     {
 *         $response = $this->post('/register', [
 *             'email' => 'user@example.com',
 *             'password' => 'secret123',
 *         ]);
 *
 *         $this->assertResponseStatus($response, 201);
 *         $this->assertAuthenticated();
 *     }
 *
 *     public function testUserCanLogin(): void
 *     {
 *         $response = $this->post('/login', [
 *             'email' => 'user@example.com',
 *             'password' => 'secret123',
 *         ]);
 *
 *         $this->assertResponseRedirects($response, '/dashboard');
 *     }
 * }
 * ```
 *
 * @package lalaz/testing
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
abstract class E2ETestCase extends IntegrationTestCase
{
    /**
     * The base URL for HTTP requests.
     *
     * @var string
     */
    protected string $baseUrl = 'http://localhost';

    /**
     * Headers to include in every request.
     *
     * @var array<string, string>
     */
    protected array $defaultHeaders = [];

    /**
     * Current session data.
     *
     * @var array<string, mixed>
     */
    protected array $session = [];

    /**
     * Current cookies.
     *
     * @var array<string, string>
     */
    protected array $cookies = [];

    /**
     * Simulate a GET request.
     *
     * @param string $uri The URI to request
     * @param array<string, mixed> $query Query parameters
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function get(
        string $uri,
        array $query = [],
        array $headers = []
    ): TestResponse {
        return $this->request('GET', $uri, [], $query, $headers);
    }

    /**
     * Simulate a POST request.
     *
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function post(
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        return $this->request('POST', $uri, $data, [], $headers);
    }

    /**
     * Simulate a PUT request.
     *
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function put(
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        return $this->request('PUT', $uri, $data, [], $headers);
    }

    /**
     * Simulate a PATCH request.
     *
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function patch(
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        return $this->request('PATCH', $uri, $data, [], $headers);
    }

    /**
     * Simulate a DELETE request.
     *
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function delete(
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        return $this->request('DELETE', $uri, $data, [], $headers);
    }

    /**
     * Simulate a JSON request.
     *
     * @param string $method HTTP method
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function json(
        string $method,
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        return $this->request($method, $uri, $data, [], $headers);
    }

    /**
     * Simulate an HTTP request.
     *
     * @param string $method HTTP method
     * @param string $uri The URI to request
     * @param array<string, mixed> $data Request body data
     * @param array<string, mixed> $query Query parameters
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function request(
        string $method,
        string $uri,
        array $data = [],
        array $query = [],
        array $headers = []
    ): TestResponse {
        // This is a placeholder implementation
        // Real implementation would use the HTTP kernel to handle the request

        $headers = array_merge($this->defaultHeaders, $headers);

        // Build the full URL
        $url = $this->baseUrl . '/' . ltrim($uri, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // Create a mock response for now
        // Real implementation would dispatch through HttpKernel
        return new TestResponse(
            statusCode: 200,
            headers: [],
            body: '',
            request: [
                'method' => $method,
                'uri' => $uri,
                'data' => $data,
                'query' => $query,
                'headers' => $headers,
            ]
        );
    }

    /**
     * Set session data for the next request.
     *
     * @param array<string, mixed> $data Session data
     * @return static
     */
    protected function withSession(array $data): static
    {
        $this->session = array_merge($this->session, $data);
        return $this;
    }

    /**
     * Set cookies for the next request.
     *
     * @param array<string, string> $cookies Cookies to set
     * @return static
     */
    protected function withCookies(array $cookies): static
    {
        $this->cookies = array_merge($this->cookies, $cookies);
        return $this;
    }

    /**
     * Set headers for the next request.
     *
     * @param array<string, string> $headers Headers to set
     * @return static
     */
    protected function withHeaders(array $headers): static
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Set the authorization header with a bearer token.
     *
     * @param string $token The bearer token
     * @return static
     */
    protected function withToken(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * Assert the response has a specific status code.
     *
     * @param TestResponse $response The response to check
     * @param int $status Expected status code
     * @return void
     */
    protected function assertResponseStatus(TestResponse $response, int $status): void
    {
        $this->assertSame(
            $status,
            $response->statusCode(),
            sprintf(
                'Expected status code %d but received %d',
                $status,
                $response->statusCode()
            )
        );
    }

    /**
     * Assert the response is successful (2xx).
     *
     * @param TestResponse $response The response to check
     * @return void
     */
    protected function assertResponseOk(TestResponse $response): void
    {
        $this->assertTrue(
            $response->isSuccessful(),
            sprintf(
                'Expected successful response but received status %d',
                $response->statusCode()
            )
        );
    }

    /**
     * Assert the response is a redirect.
     *
     * @param TestResponse $response The response to check
     * @param string|null $uri Expected redirect URI (optional)
     * @return void
     */
    protected function assertResponseRedirects(
        TestResponse $response,
        ?string $uri = null
    ): void {
        $this->assertTrue(
            $response->isRedirect(),
            sprintf(
                'Expected redirect response but received status %d',
                $response->statusCode()
            )
        );

        if ($uri !== null) {
            $this->assertSame(
                $uri,
                $response->header('Location'),
                sprintf(
                    'Expected redirect to %s but got %s',
                    $uri,
                    $response->header('Location') ?? 'no location'
                )
            );
        }
    }

    /**
     * Assert the response contains JSON.
     *
     * @param TestResponse $response The response to check
     * @param array<string, mixed> $data Expected JSON data (subset)
     * @return void
     */
    protected function assertResponseJson(TestResponse $response, array $data = []): void
    {
        $this->assertTrue(
            $response->isJson(),
            'Expected JSON response but content type was not application/json'
        );

        if (!empty($data)) {
            $json = $response->json();
            foreach ($data as $key => $value) {
                $this->assertArrayHasKey($key, $json);
                $this->assertSame($value, $json[$key]);
            }
        }
    }
}
