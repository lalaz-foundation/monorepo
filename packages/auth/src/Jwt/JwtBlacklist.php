<?php

declare(strict_types=1);

namespace Lalaz\Auth\Jwt;

/**
 * JWT Blacklist
 *
 * Simple in-memory/cache-based token blacklist for revoking JWT tokens.
 * In production, use Redis or database for persistence.
 *
 * @package Lalaz\Auth\Jwt
 */
class JwtBlacklist
{
    /**
     * Cache key prefix.
     */
    private const string CACHE_PREFIX = 'jwt_blacklist:';

    /**
     * The cache store instance.
     *
     * @var object|null Cache instance with get/set methods
     */
    private ?object $cache = null;

    /**
     * In-memory blacklist (fallback).
     *
     * @var array<string, int>
     */
    private static array $memoryBlacklist = [];

    /**
     * Create a new JwtBlacklist instance.
     *
     * @param object|null $cache Cache store with get/set/delete methods.
     */
    public function __construct(?object $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Add a token to the blacklist.
     *
     * @param string $jti The JWT ID to blacklist.
     * @param int $expiration When the token expires (for auto-cleanup).
     * @return void
     */
    public function add(string $jti, int $expiration): void
    {
        $ttl = max(0, $expiration - time());

        if ($this->cache !== null && method_exists($this->cache, 'set')) {
            $this->cache->set(self::CACHE_PREFIX . $jti, true, $ttl);
        } else {
            self::$memoryBlacklist[$jti] = $expiration;
        }
    }

    /**
     * Check if a token is blacklisted.
     *
     * @param string $jti The JWT ID to check.
     * @return bool
     */
    public function isBlacklisted(string $jti): bool
    {
        if ($this->cache !== null && method_exists($this->cache, 'get')) {
            return $this->cache->get(self::CACHE_PREFIX . $jti) !== null;
        }

        // Check memory blacklist
        if (!isset(self::$memoryBlacklist[$jti])) {
            return false;
        }

        // Auto-cleanup expired entries
        if (self::$memoryBlacklist[$jti] < time()) {
            unset(self::$memoryBlacklist[$jti]);
            return false;
        }

        return true;
    }

    /**
     * Alias for isBlacklisted.
     *
     * @param string $jti The JWT ID to check.
     * @return bool
     */
    public function has(string $jti): bool
    {
        return $this->isBlacklisted($jti);
    }

    /**
     * Remove a token from the blacklist.
     *
     * @param string $jti The JWT ID to remove.
     * @return void
     */
    public function remove(string $jti): void
    {
        if ($this->cache !== null && method_exists($this->cache, 'delete')) {
            $this->cache->delete(self::CACHE_PREFIX . $jti);
        } else {
            unset(self::$memoryBlacklist[$jti]);
        }
    }

    /**
     * Clear all blacklisted tokens (memory only).
     *
     * @return void
     */
    public function clear(): void
    {
        self::$memoryBlacklist = [];
    }

    /**
     * Cleanup expired entries from memory blacklist.
     *
     * @return int Number of entries cleaned up.
     */
    public function cleanup(): int
    {
        $now = time();
        $cleaned = 0;

        foreach (self::$memoryBlacklist as $jti => $expiration) {
            if ($expiration < $now) {
                unset(self::$memoryBlacklist[$jti]);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
