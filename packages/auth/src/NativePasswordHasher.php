<?php

declare(strict_types=1);

namespace Lalaz\Auth;

use Lalaz\Auth\Contracts\PasswordHasherInterface;

/**
 * Native Password Hasher
 *
 * Configurable password hasher using PHP's native password_hash/password_verify.
 * Supports pepper for additional security layer.
 *
 * @package Lalaz\Auth
 *
 * @example
 * ```php
 * // Basic usage with defaults
 * $hasher = new NativePasswordHasher();
 *
 * // With pepper for extra security
 * $hasher = new NativePasswordHasher(pepper: $_ENV['PASSWORD_PEPPER']);
 *
 * // Argon2id with custom options
 * $hasher = NativePasswordHasher::argon2id(
 *     memoryCost: 65536,
 *     timeCost: 4,
 *     pepper: $_ENV['PASSWORD_PEPPER']
 * );
 *
 * // From config array
 * $hasher = NativePasswordHasher::fromConfig([
 *     'algorithm' => PASSWORD_ARGON2ID,
 *     'pepper' => $_ENV['PASSWORD_PEPPER'],
 *     'options' => ['memory_cost' => 65536],
 * ]);
 * ```
 */
final class NativePasswordHasher implements PasswordHasherInterface
{
    /**
     * The hashing algorithm.
     */
    private string|int|null $algorithm;

    /**
     * Optional pepper for additional security.
     */
    private ?string $pepper;

    /**
     * The hashing options.
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * Create a new native password hasher.
     *
     * @param string|int|null $algorithm PASSWORD_DEFAULT, PASSWORD_BCRYPT, PASSWORD_ARGON2ID, etc.
     * @param string|null $pepper Optional pepper to add to passwords before hashing.
     * @param array<string, mixed> $options Algorithm-specific options (cost, memory_cost, etc.)
     */
    public function __construct(
        string|int|null $algorithm = PASSWORD_DEFAULT,
        ?string $pepper = null,
        array $options = [],
    ) {
        $this->algorithm = $algorithm;
        $this->pepper = $pepper !== null && $pepper !== '' ? $pepper : null;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(string $plainText): string
    {
        return password_hash($this->preparePassword($plainText), $this->algorithm, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $plainText, string $hash): bool
    {
        return password_verify($this->preparePassword($plainText), $hash);
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }

    /**
     * Check if this hasher has a pepper configured.
     *
     * @return bool
     */
    public function hasPepper(): bool
    {
        return $this->pepper !== null;
    }

    /**
     * Get the algorithm being used.
     *
     * @return string|int|null
     */
    public function getAlgorithm(): string|int|null
    {
        return $this->algorithm;
    }

    /**
     * Get the options being used.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Prepare password by applying pepper if configured.
     *
     * @param string $password The plain text password.
     * @return string The prepared password.
     */
    private function preparePassword(string $password): string
    {
        if ($this->pepper === null) {
            return $password;
        }

        return hash_hmac('sha256', $password, $this->pepper);
    }

    /**
     * Create a hasher with bcrypt.
     *
     * @param int $cost The cost factor (4-31).
     * @param string|null $pepper Optional pepper.
     * @return self
     */
    public static function bcrypt(int $cost = 12, ?string $pepper = null): self
    {
        return new self(PASSWORD_BCRYPT, $pepper, ['cost' => $cost]);
    }

    /**
     * Create a hasher with Argon2id.
     *
     * @param int $memoryCost Memory cost in KiB.
     * @param int $timeCost Time cost iterations.
     * @param int $threads Number of threads.
     * @param string|null $pepper Optional pepper.
     * @return self
     */
    public static function argon2id(
        int $memoryCost = 65536,
        int $timeCost = 4,
        int $threads = 1,
        ?string $pepper = null,
    ): self {
        return new self(PASSWORD_ARGON2ID, $pepper, [
            'memory_cost' => $memoryCost,
            'time_cost' => $timeCost,
            'threads' => $threads,
        ]);
    }

    /**
     * Create a hasher from configuration array.
     *
     * @param array<string, mixed> $config Configuration with keys: algorithm, pepper, options
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            algorithm: $config['algorithm'] ?? PASSWORD_DEFAULT,
            pepper: $config['pepper'] ?? null,
            options: $config['options'] ?? [],
        );
    }
}
