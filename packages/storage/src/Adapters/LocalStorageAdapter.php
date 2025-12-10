<?php

declare(strict_types=1);

namespace Lalaz\Storage\Adapters;

use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * LocalStorageAdapter
 *
 * Local filesystem storage adapter with path traversal protection and secure file handling.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class LocalStorageAdapter implements StorageInterface
{
    /**
     * Base path for storage.
     */
    protected string $basePath;

    /**
     * Public URL prefix for files.
     */
    protected string $publicUrl;

    /**
     * Default directory permissions.
     */
    protected int $directoryPermissions = 0755;

    /**
     * Default file permissions.
     */
    protected int $filePermissions = 0644;

    /**
     * Create a new LocalStorageAdapter instance.
     *
     * @param array{
     *     path: string,
     *     public_url?: string,
     *     directory_permissions?: int,
     *     file_permissions?: int
     * } $config Configuration options
     *
     * @throws StorageException If path configuration is missing
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['path']) || $config['path'] === '') {
            throw StorageException::missingConfiguration('storage.path');
        }

        $this->basePath = rtrim($config['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->publicUrl = rtrim($config['public_url'] ?? '', '/');

        if (isset($config['directory_permissions'])) {
            $this->directoryPermissions = $config['directory_permissions'];
        }

        if (isset($config['file_permissions'])) {
            $this->filePermissions = $config['file_permissions'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function upload(string $path, string $localPath): string
    {
        $uniqueFileName = $this->generateUniqueFileName($path);
        $destination = $this->getFullPath($uniqueFileName);

        $this->validatePathWithinBase($destination);
        $this->ensureDirectoryExists(dirname($destination));

        if (!is_file($localPath)) {
            throw StorageException::uploadFailed($localPath, $destination, 'Source file does not exist');
        }

        if (!@copy($localPath, $destination)) {
            throw StorageException::uploadFailed($localPath, $destination);
        }

        @chmod($destination, $this->filePermissions);

        return $this->getPublicUrl($uniqueFileName);
    }

    /**
     * {@inheritdoc}
     */
    public function download(string $path): string
    {
        $fullPath = $this->getFullPath($path);
        $this->validatePathWithinBase($fullPath);

        $realPath = realpath($fullPath);
        if ($realPath === false || !is_file($realPath)) {
            throw StorageException::fileNotFound($path);
        }

        return $realPath;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        try {
            $this->validatePathWithinBase($fullPath);
        } catch (StorageException) {
            return false;
        }

        $realPath = realpath($fullPath);
        if ($realPath === false || !is_file($realPath)) {
            return false;
        }

        return @unlink($realPath);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        try {
            $this->validatePathWithinBase($fullPath);
        } catch (StorageException) {
            return false;
        }

        return file_exists($fullPath);
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
        $fullPath = $this->download($path);
        $size = @filesize($fullPath);

        if ($size === false) {
            throw StorageException::readFailed($path, 'Could not get file size');
        }

        return $size;
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->download($path);
        $mtime = @filemtime($fullPath);

        if ($mtime === false) {
            throw StorageException::readFailed($path, 'Could not get last modified time');
        }

        return $mtime;
    }

    /**
     * {@inheritdoc}
     */
    public function mimeType(string $path): ?string
    {
        $fullPath = $this->download($path);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath);

        return $mimeType !== false ? $mimeType : null;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $from, string $to): bool
    {
        $sourcePath = $this->getFullPath($from);
        $destPath = $this->getFullPath($to);

        $this->validatePathWithinBase($sourcePath);
        $this->validatePathWithinBase($destPath);

        if (!file_exists($sourcePath)) {
            throw StorageException::fileNotFound($from);
        }

        $this->ensureDirectoryExists(dirname($destPath));

        if (!@copy($sourcePath, $destPath)) {
            throw StorageException::copyFailed($from, $to);
        }

        @chmod($destPath, $this->filePermissions);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): bool
    {
        $sourcePath = $this->getFullPath($from);
        $destPath = $this->getFullPath($to);

        $this->validatePathWithinBase($sourcePath);
        $this->validatePathWithinBase($destPath);

        if (!file_exists($sourcePath)) {
            throw StorageException::fileNotFound($from);
        }

        $this->ensureDirectoryExists(dirname($destPath));

        if (!@rename($sourcePath, $destPath)) {
            throw StorageException::moveFailed($from, $to);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): string
    {
        $fullPath = $this->download($path);
        $contents = @file_get_contents($fullPath);

        if ($contents === false) {
            throw StorageException::readFailed($path);
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $this->validatePathWithinBase($fullPath);
        $this->ensureDirectoryExists(dirname($fullPath));

        $result = @file_put_contents($fullPath, $contents);

        if ($result === false) {
            throw StorageException::writeFailed($path);
        }

        @chmod($fullPath, $this->filePermissions);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function append(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $this->validatePathWithinBase($fullPath);
        $this->ensureDirectoryExists(dirname($fullPath));

        $result = @file_put_contents($fullPath, $contents, FILE_APPEND);

        if ($result === false) {
            throw StorageException::writeFailed($path);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(string $path, string $contents): bool
    {
        if ($this->exists($path)) {
            $existingContents = $this->get($path);
            return $this->put($path, $contents . $existingContents);
        }

        return $this->put($path, $contents);
    }

    /**
     * {@inheritdoc}
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $this->getRelativePath($file->getPathname());
                }
            }
        } else {
            $items = @scandir($fullPath);
            if ($items === false) {
                return [];
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                if (is_file($itemPath)) {
                    $files[] = $this->getRelativePath($itemPath);
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
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $directories[] = $this->getRelativePath($file->getPathname());
                }
            }
        } else {
            $items = @scandir($fullPath);
            if ($items === false) {
                return [];
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                if (is_dir($itemPath)) {
                    $directories[] = $this->getRelativePath($itemPath);
                }
            }
        }

        return $directories;
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        $this->validatePathWithinBase($fullPath);

        if (is_dir($fullPath)) {
            return true;
        }

        return @mkdir($fullPath, $this->directoryPermissions, true);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory(string $directory, bool $recursive = false): bool
    {
        $fullPath = $this->getFullPath($directory);

        try {
            $this->validatePathWithinBase($fullPath);
        } catch (StorageException) {
            return false;
        }

        if (!is_dir($fullPath)) {
            return false;
        }

        if ($recursive) {
            return $this->deleteDirectoryRecursive($fullPath);
        }

        // Only delete if empty
        $items = @scandir($fullPath);
        if ($items === false) {
            return false;
        }

        $items = array_diff($items, ['.', '..']);
        if (count($items) > 0) {
            return false;
        }

        return @rmdir($fullPath);
    }

    /**
     * Get full path for a given relative path.
     */
    protected function getFullPath(string $path): string
    {
        $path = ltrim($path, DIRECTORY_SEPARATOR);
        return $this->basePath . $path;
    }

    /**
     * Get relative path from full path.
     */
    protected function getRelativePath(string $fullPath): string
    {
        return ltrim(str_replace($this->basePath, '', $fullPath), DIRECTORY_SEPARATOR);
    }

    /**
     * Validate that a path is within the base storage directory.
     *
     * @throws StorageException If path traversal is detected
     */
    protected function validatePathWithinBase(string $path): void
    {
        $realBasePath = realpath($this->basePath);

        if ($realBasePath === false) {
            // Base path doesn't exist yet, create it
            if (!@mkdir($this->basePath, $this->directoryPermissions, true)) {
                throw StorageException::invalidPath($this->basePath, 'Base path does not exist and could not be created');
            }
            $realBasePath = realpath($this->basePath);
        }

        // For paths that don't exist yet, check the parent directory
        $checkPath = $path;
        while (!file_exists($checkPath) && $checkPath !== dirname($checkPath)) {
            $checkPath = dirname($checkPath);
        }

        $realPath = realpath($checkPath);
        if ($realPath === false) {
            // If we can't resolve the path at all, just do a string check
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $normalizedBase = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->basePath);

            if (!str_starts_with($normalizedPath, $normalizedBase)) {
                throw StorageException::pathTraversal($path);
            }
            return;
        }

        if (!str_starts_with($realPath, $realBasePath)) {
            throw StorageException::pathTraversal($path);
        }
    }

    /**
     * Ensure a directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, $this->directoryPermissions, true)) {
                throw StorageException::writeFailed($directory, 'Could not create directory');
            }
        }
    }

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectoryRecursive(string $directory): bool
    {
        $items = @scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($directory);
    }

    /**
     * Generate a unique filename for storage.
     *
     * Security: Only uses the extension from original name.
     * Directory structure from client input is ignored to prevent path traversal.
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
