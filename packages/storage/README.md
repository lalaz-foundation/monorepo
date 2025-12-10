# Lalaz Storage

A unified file storage abstraction layer for the Lalaz Framework with support for multiple drivers and seamless disk switching.

## Features

- **Multiple Storage Drivers**: Built-in support for local filesystem and in-memory storage
- **Unified API**: Consistent interface across all storage drivers
- **Multi-Disk Support**: Configure and switch between multiple storage disks
- **Path Traversal Protection**: Security-first design preventing directory traversal attacks
- **Unique Filename Generation**: Automatic collision-free filename generation for uploads
- **Automatic Directory Creation**: Nested directories created automatically on write
- **Static Facade**: Simple static API for quick storage operations
- **Extensible**: Easy to add custom storage drivers

## Requirements

- PHP 8.3 or higher
- Lalaz Framework (optional, for service provider)

## Installation

```bash
composer require lalaz/storage
```

## Quick Start

### Using the Static Facade

```php
use Lalaz\Storage\Storage;

// Store a file
Storage::put('documents/readme.txt', 'Hello World');

// Check if file exists
if (Storage::exists('documents/readme.txt')) {
    // Read the file
    $content = Storage::get('documents/readme.txt');
}

// Delete the file
Storage::delete('documents/readme.txt');
```

### Using the Storage Manager

```php
use Lalaz\Storage\StorageManager;

$manager = new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/var/www/storage',
            'public_url' => 'https://example.com/storage',
        ],
        'temp' => [
            'driver' => 'memory',
            'public_url' => 'https://temp.example.com',
        ],
    ],
]);

// Use default disk
$manager->put('file.txt', 'content');

// Use specific disk
$manager->disk('temp')->put('temp-file.txt', 'temporary data');
```

### Using Adapters Directly

```php
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;

// Local filesystem
$local = new LocalStorageAdapter([
    'path' => '/var/www/storage',
    'public_url' => 'https://example.com/storage',
]);

// In-memory (great for testing)
$memory = new MemoryStorageAdapter([
    'public_url' => 'https://test.example.com',
]);

$local->put('files/document.txt', 'Document content');
$memory->put('temp/data.json', '{"key":"value"}');
```

## Configuration

### Disk Configuration

```php
$config = [
    'default' => 'local',  // Default disk name
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/path/to/storage',
            'public_url' => 'https://example.com/storage',
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0600],
                'directory' => ['public' => 0755, 'private' => 0700],
            ],
        ],
        'uploads' => [
            'driver' => 'local',
            'path' => '/path/to/uploads',
            'public_url' => 'https://example.com/uploads',
        ],
        'cache' => [
            'driver' => 'memory',
        ],
    ],
];
```

## API Reference

### Basic Operations

```php
// Write operations
$storage->put('path/file.txt', 'content');      // Write file
$storage->append('path/file.txt', ' more');     // Append to file
$storage->prepend('path/file.txt', 'prefix ');  // Prepend to file

// Read operations
$content = $storage->get('path/file.txt');      // Read file
$exists = $storage->exists('path/file.txt');    // Check existence

// Delete operations
$storage->delete('path/file.txt');              // Delete file
```

### File Metadata

```php
$size = $storage->size('file.txt');             // Size in bytes
$mtime = $storage->lastModified('file.txt');    // Unix timestamp
$mime = $storage->mimeType('file.txt');         // MIME type
$url = $storage->getPublicUrl('file.txt');      // Public URL
```

### File Operations

```php
// Copy and move
$storage->copy('source.txt', 'destination.txt');
$storage->move('old-path.txt', 'new-path.txt');

// Upload with unique filename
$url = $storage->upload('original.pdf', '/tmp/uploaded_file');

// Get absolute path (local only)
$path = $storage->download('file.txt');
```

### Directory Operations

```php
// Create directory
$storage->makeDirectory('path/to/directory');

// List files
$files = $storage->files('directory');           // Non-recursive
$files = $storage->files('directory', true);     // Recursive

// List directories
$dirs = $storage->directories('parent');         // Non-recursive
$dirs = $storage->directories('parent', true);   // Recursive

// Delete directory
$storage->deleteDirectory('path/to/directory');           // Empty only
$storage->deleteDirectory('path/to/directory', true);     // Recursive
```

### Multi-Disk Operations

```php
use Lalaz\Storage\Storage;

// Use default disk
Storage::put('file.txt', 'content');

// Use specific disk
Storage::disk('uploads')->put('image.jpg', $imageData);

// Switch default driver at runtime
$manager = Storage::getManager();
$manager->setDefaultDriver('uploads');

// Get disk instance
$uploadDisk = Storage::disk('uploads');
```

## Custom Drivers

Register custom storage drivers:

```php
$manager->extend('s3', function (array $config) {
    return new S3StorageAdapter($config);
});

$manager->addDisk('cloud', [
    'driver' => 's3',
    'bucket' => 'my-bucket',
    'region' => 'us-east-1',
]);
```

## Security

### Path Traversal Protection

The LocalStorageAdapter includes built-in protection against path traversal attacks:

```php
// These will throw StorageException
$storage->get('../../../etc/passwd');      // Path traversal blocked
$storage->put('../../outside.txt', 'x');   // Path traversal blocked
```

### Unique Filename Generation

The `upload()` method generates unique filenames to prevent overwrites:

```php
$url = $storage->upload('document.pdf', '/tmp/file');
// Returns: https://example.com/storage/abc123def456.pdf
```

## Testing

Use the MemoryStorageAdapter for fast, isolated tests:

```php
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        Storage::setManager(new StorageManager([
            'default' => 'memory',
            'disks' => [
                'memory' => ['driver' => 'memory'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        Storage::reset();
    }

    public function testFileUpload(): void
    {
        Storage::put('test.txt', 'content');
        $this->assertTrue(Storage::exists('test.txt'));
    }
}
```

## Service Provider

Register with the Lalaz container:

```php
use Lalaz\Storage\Providers\StorageServiceProvider;

$container->register(new StorageServiceProvider());
```

## Exception Handling

```php
use Lalaz\Storage\Exceptions\StorageException;

try {
    $content = $storage->get('nonexistent.txt');
} catch (StorageException $e) {
    // Handle storage errors
    echo $e->getMessage();
}
```

### Exception Factory Methods

```php
StorageException::missingConfiguration('storage.path');
StorageException::fileNotFound('document.txt');
StorageException::directoryNotFound('/path/to/dir');
StorageException::pathTraversal('../etc/passwd');
StorageException::uploadFailed('/tmp/file', '/storage/file');
StorageException::writeFailed('/path/to/file');
StorageException::readFailed('/path/to/file', 'reason');
StorageException::deleteFailed('/path/to/file');
StorageException::copyFailed('/source', '/dest', 'reason');
StorageException::moveFailed('/source', '/dest');
StorageException::unknownDriver('s3');
```

## Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite Integration
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
