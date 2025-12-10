<?php

declare(strict_types=1);

namespace Lalaz\Cache\Stores;

use Lalaz\Cache\CacheException;
use Lalaz\Cache\Contracts\CacheStoreInterface;

/**
 * File-based cache with atomic writes and TTL.
 */
final class FileStore implements CacheStoreInterface
{
    private string $directory;
    private string $prefix;

    public function __construct(string $directory, string $prefix = 'lalaz_')
    {
        $this->directory = rtrim($directory, '/\\');
        $this->prefix = $prefix;
        $this->ensureDirectory();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return $default;
        }

        $fp = @fopen($path, 'r');
        if ($fp === false) {
            return $default;
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                fclose($fp);
                return $default;
            }

            $contents = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            if ($contents === false || $contents === '') {
                $this->safeUnlink($path);
                return $default;
            }

            try {
                $payload = eval('?>' . $contents);
            } catch (\Throwable) {
                $this->safeUnlink($path);
                return $default;
            }

            if (
                !is_array($payload) ||
                !array_key_exists('value', $payload) ||
                !array_key_exists('expires_at', $payload)
            ) {
                $this->safeUnlink($path);
                return $default;
            }

            $expiresAt = $payload['expires_at'];
            if (is_int($expiresAt) && $expiresAt < time()) {
                $this->safeUnlink($path);
                return $default;
            }

            return $payload['value'];
        } finally {
            if (isset($fp) && is_resource($fp)) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
        }
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateInterval|null $ttl = null,
    ): bool {
        $path = $this->pathForKey($key);
        $expiresAt = $this->normalizeTtl($ttl);

        if ($expiresAt !== null && $expiresAt <= time()) {
            $this->safeUnlink($path);
            return true;
        }

        $payload = ['value' => $value, 'expires_at' => $expiresAt];
        $content = "<?php\n\nreturn " . var_export($payload, true) . ";\n";

        $tempPath = $path . '.tmp.' . uniqid('', true);

        if (file_put_contents($tempPath, $content, LOCK_EX) === false) {
            @unlink($tempPath);
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $marker = new \stdClass();
        return $this->get($key, $marker) !== $marker;
    }

    public function delete(string $key): bool
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return false;
        }

        return $this->safeUnlink($path);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $pattern = $this->directory . DIRECTORY_SEPARATOR . $this->prefix . '*';
        $files = glob($pattern);
        if ($files === false) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (is_file($file) && !$this->safeUnlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function remember(
        string $key,
        int|\DateInterval|null $ttl,
        callable $callback,
    ): mixed {
        $marker = new \stdClass();
        $existing = $this->get($key, $marker);
        if ($existing !== $marker) {
            return $existing;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new CacheException(
                "Unable to create cache directory: {$this->directory}",
            );
        }
    }

    private function pathForKey(string $key): string
    {
        $hash = sha1($key);
        $safeKey = preg_replace('/[^A-Za-z0-9_:\\-]/', '_', $key) ?: 'key';
        $file = $this->prefix . $safeKey . '_' . $hash . '.php';
        return $this->directory . DIRECTORY_SEPARATOR . $file;
    }

    private function normalizeTtl(int|\DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return time() - 1;
        }

        return time() + $ttl;
    }

    private function safeUnlink(string $path): bool
    {
        try {
            return is_file($path) ? unlink($path) : false;
        } catch (\Throwable) {
            return false;
        }
    }
}
