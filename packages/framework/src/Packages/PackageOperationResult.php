<?php

declare(strict_types=1);

namespace Lalaz\Packages;

/**
 * Result of a package operation (install, remove, etc.).
 *
 * Encapsulates the outcome of package management operations,
 * including success status, messages, and optional manifest data.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageOperationResult
{
    /**
     * Create a new package operation result.
     *
     * @param bool $success Whether the operation succeeded.
     * @param array<int, string> $messages Operation messages/output.
     * @param PackageManifest|null $manifest The package manifest if applicable.
     */
    public function __construct(
        public bool $success,
        public array $messages = [],
        public ?PackageManifest $manifest = null,
    ) {
    }
}
