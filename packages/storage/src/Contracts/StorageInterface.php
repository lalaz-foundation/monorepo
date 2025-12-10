<?php

declare(strict_types=1);

namespace Lalaz\Storage\Contracts;

/**
 * StorageInterface
 *
 * Contract for storage adapters that handle file upload, download, and deletion.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
interface StorageInterface
{
    /**
     * Upload a file to storage.
     *
     * @param string $path      Target path/filename in storage
     * @param string $localPath Local file path to upload
     * @return string Public URL or path to the uploaded file
     */
    public function upload(string $path, string $localPath): string;

    /**
     * Download/retrieve a file from storage.
     *
     * @param string $path Path to the file in storage
     * @return string Absolute path to the file
     */
    public function download(string $path): string;

    /**
     * Delete a file from storage.
     *
     * @param string $path Path to the file in storage
     * @return bool True if file was deleted, false otherwise
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists in storage.
     *
     * @param string $path Path to the file in storage
     * @return bool True if file exists, false otherwise
     */
    public function exists(string $path): bool;

    /**
     * Get the public URL for a file.
     *
     * @param string $path Path to the file in storage
     * @return string Public URL to access the file
     */
    public function getPublicUrl(string $path): string;

    /**
     * Get file size in bytes.
     *
     * @param string $path Path to the file in storage
     * @return int File size in bytes
     */
    public function size(string $path): int;

    /**
     * Get file's last modified timestamp.
     *
     * @param string $path Path to the file in storage
     * @return int Unix timestamp
     */
    public function lastModified(string $path): int;

    /**
     * Get the MIME type of a file.
     *
     * @param string $path Path to the file in storage
     * @return string|null MIME type or null if unable to determine
     */
    public function mimeType(string $path): ?string;

    /**
     * Copy a file to a new location.
     *
     * @param string $from Source path
     * @param string $to   Destination path
     * @return bool True on success
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     *
     * @param string $from Source path
     * @param string $to   Destination path
     * @return bool True on success
     */
    public function move(string $from, string $to): bool;

    /**
     * Read file contents as string.
     *
     * @param string $path Path to the file in storage
     * @return string File contents
     */
    public function get(string $path): string;

    /**
     * Write contents to a file.
     *
     * @param string $path     Path to the file in storage
     * @param string $contents Contents to write
     * @return bool True on success
     */
    public function put(string $path, string $contents): bool;

    /**
     * Append contents to a file.
     *
     * @param string $path     Path to the file in storage
     * @param string $contents Contents to append
     * @return bool True on success
     */
    public function append(string $path, string $contents): bool;

    /**
     * Prepend contents to a file.
     *
     * @param string $path     Path to the file in storage
     * @param string $contents Contents to prepend
     * @return bool True on success
     */
    public function prepend(string $path, string $contents): bool;

    /**
     * List all files in a directory.
     *
     * @param string $directory Directory path
     * @param bool   $recursive Whether to list recursively
     * @return array<string> List of file paths
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * List all directories in a directory.
     *
     * @param string $directory Directory path
     * @param bool   $recursive Whether to list recursively
     * @return array<string> List of directory paths
     */
    public function directories(string $directory = '', bool $recursive = false): array;

    /**
     * Create a directory.
     *
     * @param string $path Directory path
     * @return bool True on success
     */
    public function makeDirectory(string $path): bool;

    /**
     * Delete a directory.
     *
     * @param string $directory Directory path
     * @param bool   $recursive Whether to delete recursively
     * @return bool True on success
     */
    public function deleteDirectory(string $directory, bool $recursive = false): bool;
}
