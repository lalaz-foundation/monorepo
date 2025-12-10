<?php

declare(strict_types=1);

namespace Lalaz\Auth\Providers;

use Lalaz\Auth\Contracts\ApiKeyProviderInterface;
use Lalaz\Auth\Contracts\PasswordHasherInterface;
use Lalaz\Auth\Contracts\RememberTokenProviderInterface;
use Lalaz\Auth\Contracts\UserProviderInterface;
use Lalaz\Auth\NativePasswordHasher;

/**
 * Model User Provider
 *
 * Full-featured implementation of user provider interfaces for Lalaz ORM models.
 * Uses the Model's query builder for user retrieval.
 *
 * IMPORTANT: This provider requires either:
 * - The lalaz/orm package installed, OR
 * - A model class that implements find(), findOneBy(), or findBy() methods
 *
 * If neither is available, an exception will be thrown with clear instructions.
 *
 * For applications without ORM, consider using GenericUserProvider instead.
 *
 * Implements:
 * - UserProviderInterface - Core user retrieval and credential validation
 * - RememberTokenProviderInterface - Remember me token support
 * - ApiKeyProviderInterface - API key authentication support
 *
 * @package Lalaz\Auth\Providers
 */
class ModelUserProvider implements
    UserProviderInterface,
    RememberTokenProviderInterface,
    ApiKeyProviderInterface
{
    /**
     * The model class name.
     *
     * @var string
     */
    protected string $model;

    /**
     * The password hasher instance.
     *
     * @var PasswordHasherInterface
     */
    protected PasswordHasherInterface $hasher;

    /**
     * Whether the model's query capability has been validated.
     *
     * @var bool
     */
    private bool $validated = false;

    /**
     * Create a new model user provider.
     *
     * @param string $model The fully qualified model class name.
     * @param PasswordHasherInterface|null $hasher The password hasher (defaults to NativePasswordHasher).
     */
    public function __construct(string $model, ?PasswordHasherInterface $hasher = null)
    {
        $this->model = $model;
        $this->hasher = $hasher ?? new NativePasswordHasher();
        $this->validateModelClass();
    }

    /**
     * Validate that the model class exists and can be queried.
     *
     * @throws \InvalidArgumentException If model class doesn't exist
     * @throws \RuntimeException If model cannot be queried (no ORM or query methods)
     */
    private function validateModelClass(): void
    {
        if ($this->validated) {
            return;
        }

        // Check if model class exists
        if (!class_exists($this->model)) {
            throw new \InvalidArgumentException(
                "User model class '{$this->model}' does not exist. " .
                'Please check your AUTH_MODEL environment variable or auth.php configuration.'
            );
        }

        // Check if we have any way to query the model
        $hasFindMethod = method_exists($this->model, 'find');
        $hasFindById = method_exists($this->model, 'findById');
        $hasFindOneBy = method_exists($this->model, 'findOneBy');
        $hasFindBy = method_exists($this->model, 'findBy');
        $hasQueryMethod = method_exists($this->model, 'query') || method_exists($this->model, 'where');

        if (!$hasFindMethod && !$hasFindById && !$hasFindOneBy && !$hasFindBy && !$hasQueryMethod) {
            throw new \RuntimeException(
                "Cannot use ModelUserProvider with model '{$this->model}': no query capability available. " .
                "Either install the lalaz/orm package, or use GenericUserProvider instead.\n\n" .
                "Option 1 - Install ORM:\n" .
                "  php lalaz package:add lalaz/orm\n\n" .
                "Option 2 - Use GenericUserProvider in config/auth.php:\n" .
                "  'providers' => [\n" .
                "      'users' => [\n" .
                "          'driver' => 'generic',\n" .
                "          'callbacks' => [\n" .
                "              'byId' => fn(\$id) => /* your query */,\n" .
                "              'byCredentials' => fn(\$creds) => /* your query */,\n" .
                "          ],\n" .
                "      ],\n" .
                '  ],'
            );
        }

        $this->validated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById(mixed $identifier): mixed
    {
        $modelClass = $this->model;

        // Priority 1: Use container to resolve model and query (Lalaz ORM)
        if (function_exists('resolve')) {
            try {
                $manager = resolve(\Lalaz\Orm\ModelManager::class);
                return $modelClass::find($manager, $identifier);
            } catch (\Throwable) {
                // Continue to fallback methods
            }
        }

        // Priority 2: Try static find() method
        if (method_exists($modelClass, 'find')) {
            return $modelClass::find($identifier);
        }

        // Priority 3: Try findById() method
        if (method_exists($modelClass, 'findById')) {
            return $modelClass::findById($identifier);
        }

        // Priority 4: Fallback to findOneBy with id condition
        return $this->findOneBy(['id' => $identifier]);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        if (empty($credentials)) {
            return null;
        }

        // Build expression excluding password and remember fields
        $conditions = [];
        $excludeFields = ['password', 'remember', 'remember_me'];

        foreach ($credentials as $key => $value) {
            // Skip password-related and remember fields
            if (in_array($key, $excludeFields, true) || str_contains($key, 'password')) {
                continue;
            }

            $conditions[$key] = $value;
        }

        if (empty($conditions)) {
            return null;
        }

        return $this->findOneBy($conditions);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(mixed $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if ($password === null) {
            return false;
        }

        if (!method_exists($user, 'getAuthPassword')) {
            return false;
        }

        return $this->hasher->verify($password, $user->getAuthPassword());
    }

    /**
     * Check if a user's password needs rehashing.
     *
     * @param mixed $user The user instance.
     * @return bool True if rehashing is recommended.
     */
    public function passwordNeedsRehash(mixed $user): bool
    {
        if (!method_exists($user, 'getAuthPassword')) {
            return false;
        }

        return $this->hasher->needsRehash($user->getAuthPassword());
    }

    /**
     * Hash a plain text password.
     *
     * @param string $password The plain text password.
     * @return string The hashed password.
     */
    public function hashPassword(string $password): string
    {
        return $this->hasher->hash($password);
    }

    /**
     * Get the password hasher instance.
     *
     * @return PasswordHasherInterface
     */
    public function getHasher(): PasswordHasherInterface
    {
        return $this->hasher;
    }

    /**
     * Set the password hasher instance.
     *
     * @param PasswordHasherInterface $hasher
     * @return self
     */
    public function setHasher(PasswordHasherInterface $hasher): self
    {
        $this->hasher = $hasher;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        return $this->findOneBy([
            'id' => $identifier,
            'remember_token' => $token,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByApiKey(string $apiKey): mixed
    {
        // Hash the API key for comparison (keys are stored hashed)
        $hashedKey = hash('sha256', $apiKey);

        return $this->findOneBy([
            'api_key_hash' => $hashedKey,
            'api_key_active' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(mixed $user, string $token): void
    {
        $user->remember_token = $token;

        if (method_exists($user, 'save')) {
            $user->save();
        }
    }

    /**
     * Find one record by conditions.
     *
     * This method tries multiple query approaches in order of preference:
     * 1. Container resolution for Lalaz ORM (ModelManager + queryWith)
     * 2. findOneBy (generic ORM pattern)
     * 3. findBy (returns array, gets first)
     * 4. where/first chain (Eloquent-style)
     *
     * @param array<string, mixed> $conditions Key-value pairs for the query
     * @return mixed The found model or null
     */
    protected function findOneBy(array $conditions): mixed
    {
        $modelClass = $this->model;

        // Priority 1: Use container to resolve model and query (Lalaz ORM)
        if (function_exists('resolve')) {
            try {
                // Try to get ModelManager and query
                $manager = resolve(\Lalaz\Orm\ModelManager::class);
                $query = $modelClass::queryWith($manager);

                foreach ($conditions as $key => $value) {
                    $query = $query->where($key, '=', $value);
                }

                return $query->first();
            } catch (\Throwable) {
                // Continue to fallback methods
            }
        }

        // Priority 2: findOneBy method (common pattern)
        if (method_exists($modelClass, 'findOneBy')) {
            return $modelClass::findOneBy($conditions);
        }

        // Priority 3: findBy method (returns array)
        if (method_exists($modelClass, 'findBy')) {
            $results = $modelClass::findBy($conditions);
            return $results[0] ?? null;
        }

        // Priority 4: where/first chain (Eloquent-style)
        if (method_exists($modelClass, 'where')) {
            $query = $modelClass::query();

            foreach ($conditions as $key => $value) {
                $query = $query->where($key, '=', $value);
            }

            if (method_exists($query, 'first')) {
                return $query->first();
            }
        }

        // If we reach here, no query method worked - this shouldn't happen
        // after constructor validation, but just in case:
        throw new \RuntimeException(
            "Failed to query model '{$modelClass}'. No compatible query method found. " .
            'Consider using GenericUserProvider instead.'
        );
    }

    /**
     * Create a new instance of the model.
     *
     * @return object
     */
    protected function createModel(): object
    {
        return new $this->model();
    }

    /**
     * Get the model class name.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set the model class name.
     *
     * @param string $model
     * @return self
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }
}
