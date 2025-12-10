<?php

declare(strict_types=1);

namespace Lalaz\Auth\Guards;

use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use Lalaz\Auth\Contracts\StatelessGuardInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Web\Http\Contracts\RequestInterface;

/**
 * API Key Guard
 *
 * Stateless authentication guard using API keys.
 * Ideal for server-to-server communication and integrations.
 *
 * Note: The provider should implement ApiKeyProviderInterface
 * for API key authentication to work.
 *
 * @package Lalaz\Auth\Guards
 */
class ApiKeyGuard extends BaseGuard implements StatelessGuardInterface
{
    /**
     * Guard name.
     */
    private const string NAME = 'api_key';

    /**
     * HTTP header name for API key.
     *
     * @var string
     */
    private string $headerName = 'X-API-Key';

    /**
     * Query parameter name for API key.
     *
     * @var string
     */
    private string $queryParam = 'api_key';

    /**
     * The current API key.
     *
     * @var string|null
     */
    private ?string $apiKey = null;

    /**
     * The HTTP request instance for key extraction.
     *
     * @var RequestInterface|null
     */
    private ?RequestInterface $request = null;

    /**
     * Create a new API key guard.
     *
     * @param UserProviderInterface|null $provider
     * @param RequestInterface|null $request The HTTP request for key extraction.
     */
    public function __construct(
        ?UserProviderInterface $provider = null,
        ?RequestInterface $request = null
    ) {
        parent::__construct($provider);
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function attempt(array $credentials): mixed
    {
        $apiKey = $credentials['api_key'] ?? null;

        // Check if provider supports API key authentication
        if ($apiKey === null || !$this->provider instanceof ApiKeyProviderInterface) {
            return null;
        }

        $user = $this->provider->retrieveByApiKey($apiKey);

        if ($user !== null) {
            $this->user = $user;
            $this->apiKey = $apiKey;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function login(mixed $user): void
    {
        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void
    {
        $this->user = null;
        $this->apiKey = null;
    }

    /**
     * {@inheritdoc}
     */
    public function user(): mixed
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $apiKey = $this->getTokenFromRequest();

        if ($apiKey !== null) {
            $this->user = $this->authenticateToken($apiKey);
        }

        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function createToken(mixed $user, array $claims = []): string
    {
        // Generate a secure API key
        $prefix = $claims['prefix'] ?? 'lz';
        $key = bin2hex(random_bytes(32));

        return $prefix . '_' . $key;
    }

    /**
     * Generate a new API key with metadata.
     *
     * @param string $prefix Key prefix (e.g., 'lz_live', 'lz_test').
     * @return array{key: string, hash: string}
     */
    public function generateApiKey(string $prefix = 'lz'): array
    {
        $key = $this->createToken(null, ['prefix' => $prefix]);

        return [
            'key' => $key,
            'hash' => $this->hashApiKey($key),
        ];
    }

    /**
     * Hash an API key for storage.
     *
     * @param string $apiKey
     * @return string
     */
    public function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    /**
     * {@inheritdoc}
     */
    public function authenticateToken(string $token): mixed
    {
        // Check if provider supports API key authentication
        if (!$this->provider instanceof ApiKeyProviderInterface) {
            return null;
        }

        $user = $this->provider->retrieveByApiKey($token);

        if ($user !== null) {
            $this->apiKey = $token;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function revokeToken(string $token): bool
    {
        // API key revocation should be handled by the application
        // (e.g., marking it as revoked in the database)
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshToken(string $token): ?string
    {
        // API keys don't refresh - generate a new one instead
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenFromRequest(): ?string
    {
        // Use injected request if available
        if ($this->request !== null) {
            // Try custom header first
            $apiKey = $this->request->header($this->headerName);

            if ($apiKey !== null) {
                return $apiKey;
            }

            // Try Authorization header with ApiKey scheme
            $authHeader = $this->request->header('Authorization');

            if ($authHeader !== null && str_starts_with($authHeader, 'ApiKey ')) {
                return substr($authHeader, 7);
            }

            // Try query parameter
            return $this->request->queryParam($this->queryParam);
        }

        // Fallback to superglobals for backwards compatibility
        $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper($this->headerName));
        $apiKey = $_SERVER[$headerKey] ?? null;

        if ($apiKey !== null) {
            return $apiKey;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if ($authHeader !== null && str_starts_with($authHeader, 'ApiKey ')) {
            return substr($authHeader, 7);
        }

        return $_GET[$this->queryParam] ?? null;
    }

    /**
     * Set the request instance for key extraction.
     *
     * @param RequestInterface $request
     * @return void
     */
    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Set the header name for API key extraction.
     *
     * @param string $name
     * @return void
     */
    public function setHeaderName(string $name): void
    {
        $this->headerName = $name;
    }

    /**
     * Set the query parameter name.
     *
     * @param string $param
     * @return void
     */
    public function setQueryParam(string $param): void
    {
        $this->queryParam = $param;
    }

    /**
     * Get the current API key.
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Validate an API key format.
     *
     * @param string $apiKey
     * @return bool
     */
    public static function isValidFormat(string $apiKey): bool
    {
        // Expected format: prefix_hexstring (e.g., lz_abc123...)
        return (bool) preg_match('/^[a-z]+_[a-f0-9]{64}$/i', $apiKey);
    }
}
