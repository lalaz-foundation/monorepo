<?php

declare(strict_types=1);

namespace Lalaz\Auth\Guards;

use Lalaz\Auth\Contracts\StatelessGuardInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Web\Http\Contracts\RequestInterface;

/**
 * JWT Guard
 *
 * Stateless authentication guard using JSON Web Tokens.
 * Ideal for APIs, mobile apps, and SPAs.
 *
 * @package Lalaz\Auth\Guards
 */
class JwtGuard extends BaseGuard implements StatelessGuardInterface
{
    /**
     * Guard name.
     */
    private const string NAME = 'jwt';

    /**
     * The JWT encoder instance.
     *
     * @var JwtEncoder
     */
    private JwtEncoder $encoder;

    /**
     * The JWT blacklist instance.
     *
     * @var JwtBlacklist
     */
    private JwtBlacklist $blacklist;

    /**
     * The HTTP request instance for token extraction.
     *
     * @var RequestInterface|null
     */
    private ?RequestInterface $request = null;

    /**
     * The current token.
     *
     * @var string|null
     */
    private ?string $token = null;

    /**
     * HTTP header name for the token.
     *
     * @var string
     */
    private string $headerName = 'Authorization';

    /**
     * Token prefix in header.
     *
     * @var string
     */
    private string $tokenPrefix = 'Bearer';

    /**
     * Query parameter name for token.
     *
     * @var string
     */
    private string $queryParam = 'token';

    /**
     * Token TTL in seconds.
     *
     * @var int
     */
    private int $ttl;

    /**
     * Create a new JwtGuard instance.
     *
     * @param JwtEncoder $encoder
     * @param JwtBlacklist|null $blacklist
     * @param UserProviderInterface|null $provider
     * @param RequestInterface|null $request The HTTP request for token extraction.
     * @param int $ttl Token TTL in seconds
     */
    public function __construct(
        JwtEncoder $encoder,
        ?JwtBlacklist $blacklist = null,
        ?UserProviderInterface $provider = null,
        ?RequestInterface $request = null,
        int $ttl = 3600
    ) {
        parent::__construct($provider);
        $this->encoder = $encoder;
        $this->blacklist = $blacklist ?? new JwtBlacklist();
        $this->request = $request;
        $this->ttl = $ttl;
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
        if ($this->provider === null) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return null;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            return null;
        }

        $this->user = $user;

        return $user;
    }

    /**
     * Attempt authentication and return tokens.
     *
     * @param array<string, mixed> $credentials
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function attemptWithTokens(array $credentials): ?array
    {
        $user = $this->attempt($credentials);

        if ($user === null) {
            return null;
        }

        return $this->createTokenPair($user);
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
        // Revoke current token if exists
        if ($this->token !== null) {
            $this->revokeToken($this->token);
        }

        $this->user = null;
        $this->token = null;
    }

    /**
     * {@inheritdoc}
     */
    public function user(): mixed
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if ($token !== null) {
            $this->user = $this->authenticateToken($token);
        }

        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function createToken(mixed $user, array $claims = []): string
    {
        $userId = $this->getUserId($user);

        return $this->encoder->encode(array_merge($claims, [
            'sub' => $userId,
        ]), $this->ttl);
    }

    /**
     * Create an access and refresh token pair.
     *
     * @param mixed $user
     * @param array<string, mixed> $claims
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function createTokenPair(mixed $user, array $claims = []): array
    {
        $userId = $this->getUserId($user);

        return [
            'access_token' => $this->encoder->createAccessToken($userId, $claims),
            'refresh_token' => $this->encoder->createRefreshToken($userId),
            'token_type' => 'Bearer',
            'expires_in' => $this->ttl,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function authenticateToken(string $token): mixed
    {
        // Check if token is blacklisted
        if ($this->blacklist->has($token)) {
            return null;
        }

        // Validate and decode token
        $payload = $this->encoder->decode($token);

        if ($payload === null) {
            return null;
        }

        // Get user from provider
        $userId = $payload['sub'] ?? null;

        if ($userId === null || $this->provider === null) {
            return null;
        }

        $this->token = $token;

        return $this->provider->retrieveById($userId);
    }

    /**
     * {@inheritdoc}
     */
    public function revokeToken(string $token): bool
    {
        $claims = $this->encoder->getClaims($token);

        if ($claims === null) {
            return false;
        }

        $exp = $claims['exp'] ?? time() + $this->ttl;

        $this->blacklist->add($token, $exp);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshToken(string $token): ?string
    {
        // Check if blacklisted
        if ($this->blacklist->has($token)) {
            return null;
        }

        // Decode and validate
        $payload = $this->encoder->decode($token);

        if ($payload === null) {
            return null;
        }

        $userId = $payload['sub'] ?? null;

        if ($userId === null || $this->provider === null) {
            return null;
        }

        // Verify user still exists
        $user = $this->provider->retrieveById($userId);

        if ($user === null) {
            return null;
        }

        // Revoke old token
        $this->revokeToken($token);

        // Create new access token
        return $this->createToken($user);
    }

    /**
     * Refresh and get new token pair.
     *
     * @param string $refreshToken
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refreshTokenPair(string $refreshToken): ?array
    {
        // Validate it's a refresh token
        if (!$this->encoder->isRefreshToken($refreshToken)) {
            return null;
        }

        $payload = $this->encoder->decode($refreshToken);

        if ($payload === null) {
            return null;
        }

        // Check if refresh token is blacklisted
        if ($this->blacklist->isBlacklisted($refreshToken)) {
            return null;
        }

        $userId = $payload['sub'] ?? null;

        if ($userId === null || $this->provider === null) {
            return null;
        }

        $user = $this->provider->retrieveById($userId);

        if ($user === null) {
            return null;
        }

        // Revoke old refresh token
        $this->revokeToken($refreshToken);

        // Create new pair
        return $this->createTokenPair($user);
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenFromRequest(): ?string
    {
        // Use injected request if available
        if ($this->request !== null) {
            // Try configured header name
            $header = $this->request->header($this->headerName)
                ?? $this->request->header('Redirect-' . $this->headerName);

            if ($header !== null) {
                $prefix = $this->tokenPrefix . ' ';

                if (str_starts_with($header, $prefix)) {
                    return substr($header, strlen($prefix));
                }
            }

            // Try query parameter
            return $this->request->queryParam($this->queryParam);
        }

        // Fallback to superglobals for backwards compatibility
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($header !== null) {
            $prefix = $this->tokenPrefix . ' ';

            if (str_starts_with($header, $prefix)) {
                return substr($header, strlen($prefix));
            }
        }

        return $_GET[$this->queryParam] ?? null;
    }

    /**
     * Set the request instance for token extraction.
     *
     * @param RequestInterface $request
     * @return void
     */
    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Set the header name for token extraction.
     *
     * @param string $name
     * @return void
     */
    public function setHeaderName(string $name): void
    {
        $this->headerName = $name;
    }

    /**
     * Set the token prefix.
     *
     * @param string $prefix
     * @return void
     */
    public function setTokenPrefix(string $prefix): void
    {
        $this->tokenPrefix = $prefix;
    }

    /**
     * Get the JWT encoder instance.
     *
     * @return JwtEncoder
     */
    public function getEncoder(): JwtEncoder
    {
        return $this->encoder;
    }

    /**
     * Get the current token.
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }
}
