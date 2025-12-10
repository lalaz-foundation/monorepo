<?php

declare(strict_types=1);

namespace Lalaz\Storage\Adapters;

use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * MemoryStorageAdapter
 *
 * In-memory storage adapter for testing purposes.
 * All files are stored in memory and lost when the process ends.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class MemoryStorageAdapter implements StorageInterface
{
    /**
     * In-memory file storage.
     *
     * @var array<string, array{contents: string, mtime: int}>
     */
    protected array $files = [];

    /**
     * In-memory directory storage.
     *
     * @var array<string, bool>
     */
    protected array $directories = [];

    /**
     * Public URL prefix.
     */
    protected string $publicUrl;

    /**
     * Create a new MemoryStorageAdapter instance.
     *
     * @param array{public_url?: string} $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->publicUrl = rtrim($config['public_url'] ?? '', '/');
    }

    /**
     * {@inheritdoc}
     */
    public function upload(string $path, string $localPath): string
    {
        if (!is_file($localPath)) {
            throw StorageException::uploadFailed($localPath, $path, 'Source file does not exist');
        }

        $contents = @file_get_contents($localPath);
        if ($contents === false) {
            throw StorageException::uploadFailed($localPath, $path, 'Could not read source file');
        }

        $uniqueFileName = $this->generateUniqueFileName($path);
        $this->files[$uniqueFileName] = [
            'contents' => $contents,
            'mtime' => time(),
        ];

        $this->ensureParentDirectories($uniqueFileName);

        return $this->getPublicUrl($uniqueFileName);
    }

    /**
     * {@inheritdoc}
     */
    public function download(string $path): string
    {
        if (!isset($this->files[$path])) {
            throw StorageException::fileNotFound($path);
        }

        // Create a temp file for compatibility
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'storage_' . md5($path);
        file_put_contents($tempFile, $this->files[$path]['contents']);

        return $tempFile;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool
    {
        if (!isset($this->files[$path])) {
            return false;
        }

        unset($this->files[$path]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool
    {
        return isset($this->files[$path]) || isset($this->directories[$path]);
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicUrl(string $path): string
    {
        if ($this->publicUrl === '') {
            return $path;
        }

        return $this->publicUrl . '/' . ltrim($path, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $path): int
    {
        if (!isset($this->files[$path])) {
            throw StorageException::fileNotFound($path);
        }

        return strlen($this->files[$path]['contents']);
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): int
    {
        if (!isset($this->files[$path])) {
            throw StorageException::fileNotFound($path);
        }

        return $this->files[$path]['mtime'];
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): ?string
    {
        if (!isset($this->files[$path])) {
            throw StorageException::fileNotFound($path);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($this->files[$path]['contents']);

        return $mimeType !== false ? $mimeType : null;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $from, string $to): bool
    {
        if (!isset($this->files[$from])) {
            throw StorageException::fileNotFound($from);
        }

        $this->files[$to] = [
            'contents' => $this->files[$from]['contents'],
            'mtime' => time(),
        ];

        $this->ensureParentDirectories($to);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): bool
    {
        if (!isset($this->files[$from])) {
            throw StorageException::fileNotFound($from);
        }

        $this->files[$to] = $this->files[$from];
        $this->files[$to]['mtime'] = time();
        unset($this->files[$from]);

        $this->ensureParentDirectories($to);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): string
    {
        if (!isset($this->files[$path])) {
            throw StorageException::fileNotFound($path);
        }

        return $this->files[$path]['contents'];
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): bool
    {
        $this->files[$path] = [
            'contents' => $contents,
            'mtime' => time(),
        ];

        $this->ensureParentDirectories($path);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function append(string $path, string $contents): bool
    {
        if (!isset($this->files[$path])) {
            return $this->put($path, $contents);
        }

        $this->files[$path]['contents'] .= $contents;
        $this->files[$path]['mtime'] = time();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(string $path, string $contents): bool
    {
        if (!isset($this->files[$path])) {
            return $this->put($path, $contents);
        }

        $this->files[$path]['contents'] = $contents . $this->files[$path]['contents'];
        $this->files[$path]['mtime'] = time();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $directory = trim($directory, '/');
        $files = [];

        foreach (array_keys($this->files) as $path) {
            if ($directory === '') {
                if ($recursive) {
                    $files[] = $path;
                } else {
                    // Only files in root directory
                    if (strpos($path, '/') === false) {
                        $files[] = $path;
                    }
                }
            } else {
                if (str_starts_with($path, $directory . '/')) {
                    $relativePath = substr($path, strlen($directory) + 1);
                    if ($recursive) {
                        $files[] = $path;
                    } else {
                        // Only direct children
                        if (strpos($relativePath, '/') === false) {
                            $files[] = $path;
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * {@inheritdoc}
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $directory = trim($directory, '/');
        $dirs = [];

        foreach (array_keys($this->directories) as $path) {
            if ($directory === '') {
                if ($recursive) {
                    $dirs[] = $path;
                } else {
                    // Only directories in root
                    if (strpos($path, '/') === false) {
                        $dirs[] = $path;
                    }
                }
            } else {
                if (str_starts_with($path, $directory . '/')) {
                    $relativePath = substr($path, strlen($directory) + 1);
                    if ($recursive) {
                        $dirs[] = $path;
                    } else {
                        // Only direct children
                        if (strpos($relativePath, '/') === false) {
                            $dirs[] = $path;
                        }
                    }
                }
            }
        }

        return $dirs;
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory(string $path): bool
    {
        $path = trim($path, '/');
        $this->directories[$path] = true;
        $this->ensureParentDirectories($path . '/dummy');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $directory, bool $recursive = false): bool
    {
        $directory = trim($directory, '/');

        if (!isset($this->directories[$directory])) {
            return false;
        }

        if ($recursive) {
            // Delete all files and subdirectories
            foreach (array_keys($this->files) as $path) {
                if (str_starts_with($path, $directory . '/')) {
                    unset($this->files[$path]);
                }
            }

            foreach (array_keys($this->directories) as $path) {
                if (str_starts_with($path, $directory . '/') || $path === $directory) {
                    unset($this->directories[$path]);
                }
            }

            return true;
        }

        // Check if directory is empty
        foreach (array_keys($this->files) as $path) {
            if (str_starts_with($path, $directory . '/')) {
                return false;
            }
        }

        foreach (array_keys($this->directories) as $path) {
            if (str_starts_with($path, $directory . '/')) {
                return false;
            }
        }

        unset($this->directories[$directory]);
        return true;
    }

    /**
     * Clear all files and directories.
     */
    public function clear(): void
    {
        $this->files = [];
        $this->directories = [];
    }

    /**
     * Get all stored files (for testing).
     *
     * @return array<string, array{contents: string, mtime: int}>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Get all stored directories (for testing).
     *
     * @return array<string, bool>
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    /**
     * Ensure parent directories exist.
     */
    protected function ensureParentDirectories(string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        array_pop($parts); // Remove filename

        $current = '';
        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $this->directories[$current] = true;
        }
    }

    /**
     * Generate a unique filename.
     */
    protected function generateUniqueFileName(string $originalName): string
    {
        $pathInfo = pathinfo(basename($originalName));

        $extension = '';
        if (isset($pathInfo['extension'])) {
            $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', $pathInfo['extension']);
            if ($safeExtension !== null && $safeExtension !== '') {
                $extension = '.' . strtolower($safeExtension);
            }
        }

        $uniqueName = bin2hex(random_bytes(16));

        return $uniqueName . $extension;
    }
}
