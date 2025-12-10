# Core Concepts

Understanding the architecture and design principles of Lalaz Storage.

## Architecture Overview

Lalaz Storage follows a layered architecture that provides flexibility and extensibility:

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Code                          │
│          Your business logic and controllers                 │
├─────────────────────────────────────────────────────────────┤
│                     Storage Facade                           │
│      Static API for quick access to storage operations       │
├─────────────────────────────────────────────────────────────┤
│                    StorageManager                            │
│   Multi-disk driver factory with caching and configuration   │
├─────────────────┬─────────────────┬─────────────────────────┤
│ LocalStorage    │ MemoryStorage   │    Custom Drivers       │
│ Adapter         │ Adapter         │    (S3, GCS, etc.)      │
├─────────────────┴─────────────────┴─────────────────────────┤
│                   StorageInterface                           │
│     Common contract defining all storage operations          │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### StorageInterface

The `StorageInterface` defines the contract that all storage adapters must implement:

```php
interface StorageInterface
{
    // Basic operations
    public function get(string $path): string;
    public function put(string $path, string $contents): bool;
    public function exists(string $path): bool;
    public function delete(string $path): bool;

    // Metadata
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function mimeType(string $path): ?string;

    // Advanced operations
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function append(string $path, string $contents): int;
    public function prepend(string $path, string $contents): int;

    // Upload/Download
    public function upload(string $filename, string $path): string;
    public function download(string $path): string;
    public function getPublicUrl(string $path): string;

    // Directory operations
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path, bool $recursive = false): bool;
    public function files(string $directory = '', bool $recursive = false): array;
    public function directories(string $directory = '', bool $recursive = false): array;
}
```

This interface ensures that:
- All adapters have consistent behavior
- Code is portable across different storage backends
- Testing is simplified through interface abstraction

### Storage Adapters

Adapters implement the `StorageInterface` for specific storage backends.

#### LocalStorageAdapter

The local filesystem adapter stores files on the server's filesystem:

```php
$adapter = new LocalStorageAdapter([
    'path' => '/var/www/storage',      // Root storage path
    'public_url' => 'https://...',     // Base URL for public access
]);
```

**Features:**
- Automatic directory creation
- Path traversal protection
- Unique filename generation for uploads
- File permission management

**Use cases:**
- Application files
- User uploads
- Generated content

#### MemoryStorageAdapter

The memory adapter stores files in PHP arrays:

```php
$adapter = new MemoryStorageAdapter([
    'public_url' => 'https://test.example.com',
]);
```

**Features:**
- No filesystem interaction
- Instant operations
- Perfect isolation
- Automatic cleanup

**Use cases:**
- Unit testing
- Temporary storage
- Cache layer

### StorageManager

The manager handles multiple storage disks:

```php
$manager = new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'path' => '...'],
        'memory' => ['driver' => 'memory'],
    ],
]);
```

**Responsibilities:**
- Driver instantiation
- Disk configuration management
- Driver caching
- Custom driver registration

### Storage Facade

The static facade provides convenient access:

```php
// Instead of
$manager = new StorageManager($config);
$driver = $manager->disk('local');
$driver->put('file.txt', 'content');

// You can write
Storage::setManager($manager);
Storage::put('file.txt', 'content');
```

## Design Principles

### Single Responsibility

Each component has a focused purpose:
- **Adapters**: Handle specific storage backend
- **Manager**: Coordinate disk configuration and driver creation
- **Facade**: Provide static access convenience
- **Interface**: Define the contract

### Open/Closed Principle

The system is open for extension but closed for modification:

```php
// Add new drivers without changing existing code
$manager->extend('s3', function ($config) {
    return new S3StorageAdapter($config);
});
```

### Dependency Inversion

High-level modules depend on abstractions:

```php
class FileProcessor
{
    public function __construct(
        private StorageInterface $storage
    ) {}

    public function process(string $path): void
    {
        // Works with any adapter
        $content = $this->storage->get($path);
        // ...
    }
}
```

## Security Model

### Path Traversal Protection

The LocalStorageAdapter prevents directory traversal attacks:

```php
// Internal check in LocalStorageAdapter
private function validatePath(string $path): string
{
    $fullPath = $this->root . '/' . $path;
    $realPath = realpath(dirname($fullPath));

    // Ensure path stays within root
    if (!str_starts_with($realPath, $this->root)) {
        throw StorageException::pathTraversal($path);
    }

    return $fullPath;
}
```

Blocked patterns:
- `../` sequences
- Absolute paths outside root
- Symbolic link escapes

### Unique Filename Generation

The `upload()` method generates collision-free filenames:

```php
// User uploads "document.pdf"
$url = $storage->upload('document.pdf', '/tmp/file');
// Stored as: abc123def456ghi789.pdf
```

This prevents:
- Filename collisions
- Filename guessing attacks
- Overwriting existing files

## Multi-Disk Architecture

### Disk Configuration

Disks are named storage configurations:

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'path' => '/var/www/storage/app',
    ],
    'public' => [
        'driver' => 'local',
        'path' => '/var/www/storage/public',
    ],
    'cache' => [
        'driver' => 'memory',
    ],
]
```

### Driver Caching

The manager caches driver instances:

```php
$disk1 = $manager->disk('local'); // Creates new instance
$disk2 = $manager->disk('local'); // Returns cached instance

assert($disk1 === $disk2); // Same instance
```

Cache can be cleared:

```php
$manager->purge(); // Clear all cached drivers
```

### Dynamic Disk Management

Add disks at runtime:

```php
$manager->addDisk('uploads', [
    'driver' => 'local',
    'path' => '/var/www/uploads',
]);
```

## Exception Handling

All storage errors throw `StorageException`:

```php
try {
    $storage->get('missing.txt');
} catch (StorageException $e) {
    // Handle error
}
```

### Exception Factory Methods

```php
StorageException::missingConfiguration('path');
StorageException::fileNotFound('file.txt');
StorageException::directoryNotFound('/path');
StorageException::pathTraversal('../etc/passwd');
StorageException::uploadFailed('/source', '/dest');
StorageException::writeFailed('/path');
StorageException::readFailed('/path', 'reason');
StorageException::deleteFailed('/path');
StorageException::copyFailed('/source', '/dest', 'reason');
StorageException::moveFailed('/source', '/dest');
StorageException::unknownDriver('s3');
```

## Best Practices

### Use Dependency Injection

```php
// Good - testable and flexible
class DocumentService
{
    public function __construct(
        private StorageInterface $storage
    ) {}
}

// Avoid - hard to test
class DocumentService
{
    public function save(): void
    {
        Storage::put(...); // Static dependency
    }
}
```

### Use Memory Adapter for Testing

```php
class MyTest extends TestCase
{
    private StorageInterface $storage;

    protected function setUp(): void
    {
        $this->storage = new MemoryStorageAdapter();
    }

    public function testUpload(): void
    {
        $this->storage->put('test.txt', 'content');
        $this->assertTrue($this->storage->exists('test.txt'));
    }
}
```

### Handle Exceptions Appropriately

```php
public function downloadFile(string $path): Response
{
    try {
        $content = $this->storage->get($path);
        return new Response($content, 200);
    } catch (StorageException $e) {
        return new Response('File not found', 404);
    }
}
```

### Use Specific Disks for Different Purposes

```php
// Separate concerns with different disks
Storage::disk('private')->put('secrets/api-key.txt', $key);
Storage::disk('public')->put('assets/logo.png', $image);
Storage::disk('temp')->put('cache/data.json', $cached);
```

## Related Topics

- [API Reference](api-reference.md) - Complete method documentation
- [Testing](testing.md) - Testing strategies
- [Glossary](glossary.md) - Terminology definitions
