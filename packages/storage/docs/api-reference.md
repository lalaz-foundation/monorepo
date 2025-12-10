# API Reference

Complete reference documentation for the Lalaz Storage package.

## Storage Facade

Static facade for convenient storage access.

### Static Methods

#### setManager

Set the storage manager instance.

```php
public static function setManager(StorageManager $manager): void
```

**Parameters:**
- `$manager` - The StorageManager instance to use

**Example:**
```php
Storage::setManager(new StorageManager($config));
```

#### getManager

Get the current storage manager.

```php
public static function getManager(): StorageManager
```

**Returns:** StorageManager instance

**Example:**
```php
$manager = Storage::getManager();
$disks = $manager->getDisks();
```

#### disk

Get a storage disk instance.

```php
public static function disk(?string $name = null): StorageInterface
```

**Parameters:**
- `$name` - Disk name (optional, uses default if not specified)

**Returns:** StorageInterface instance

**Example:**
```php
$default = Storage::disk();
$uploads = Storage::disk('uploads');
```

#### driver

Alias for `disk()`.

```php
public static function driver(?string $name = null): StorageInterface
```

#### reset

Reset the storage manager to its initial state.

```php
public static function reset(): void
```

**Example:**
```php
Storage::reset(); // Clear manager instance
```

### Magic Methods

The facade proxies method calls to the default driver:

```php
Storage::put('file.txt', 'content');    // Calls driver()->put()
Storage::get('file.txt');               // Calls driver()->get()
Storage::exists('file.txt');            // Calls driver()->exists()
Storage::delete('file.txt');            // Calls driver()->delete()
```

---

## StorageManager

Manages multiple storage disks and driver creation.

### Constructor

```php
public function __construct(array $config = [])
```

**Parameters:**
- `$config` - Configuration array with 'default' and 'disks' keys

**Example:**
```php
$manager = new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/storage',
            'public_url' => 'https://example.com/storage',
        ],
    ],
]);
```

### Methods

#### disk

Get a driver instance for a disk.

```php
public function disk(?string $name = null): StorageInterface
```

**Parameters:**
- `$name` - Disk name (optional, uses default if null)

**Returns:** StorageInterface

**Throws:** StorageException if disk not configured

**Example:**
```php
$local = $manager->disk('local');
$default = $manager->disk(); // Uses default disk
```

#### getDriver

Get the default driver instance.

```php
public function getDriver(): StorageInterface
```

**Returns:** StorageInterface for default disk

#### getDefaultDriver

Get the default driver name.

```php
public function getDefaultDriver(): string
```

**Returns:** String name of default driver

#### setDefaultDriver

Set the default driver name.

```php
public function setDefaultDriver(string $name): void
```

**Parameters:**
- `$name` - Name of the disk to use as default

#### getDisks

Get all disk configurations.

```php
public function getDisks(): array
```

**Returns:** Array of disk configurations

#### addDisk

Add or update a disk configuration.

```php
public function addDisk(string $name, array $config): void
```

**Parameters:**
- `$name` - Disk name
- `$config` - Disk configuration array

**Example:**
```php
$manager->addDisk('uploads', [
    'driver' => 'local',
    'path' => '/var/www/uploads',
]);
```

#### extend

Register a custom driver creator.

```php
public function extend(string $driver, callable $creator): void
```

**Parameters:**
- `$driver` - Driver name
- `$creator` - Callable that receives config and returns StorageInterface

**Example:**
```php
$manager->extend('s3', function (array $config) {
    return new S3StorageAdapter($config);
});
```

#### purge

Clear all cached driver instances.

```php
public function purge(): void
```

---

## StorageInterface

Contract for all storage adapters.

### Basic Operations

#### get

Read file contents.

```php
public function get(string $path): string
```

**Parameters:**
- `$path` - File path relative to storage root

**Returns:** File contents as string

**Throws:** StorageException if file not found

**Example:**
```php
$content = $storage->get('documents/readme.txt');
```

#### put

Write content to a file.

```php
public function put(string $path, string $contents): bool
```

**Parameters:**
- `$path` - File path
- `$contents` - Content to write

**Returns:** true on success

**Example:**
```php
$storage->put('logs/app.log', 'Log entry');
```

#### exists

Check if a file exists.

```php
public function exists(string $path): bool
```

**Parameters:**
- `$path` - File path

**Returns:** true if file exists

**Example:**
```php
if ($storage->exists('config.json')) {
    $config = $storage->get('config.json');
}
```

#### delete

Delete a file.

```php
public function delete(string $path): bool
```

**Parameters:**
- `$path` - File path

**Returns:** true on success, false if file not found

**Example:**
```php
$storage->delete('temp/cache.txt');
```

### Content Modification

#### append

Append content to a file.

```php
public function append(string $path, string $contents): int
```

**Parameters:**
- `$path` - File path
- `$contents` - Content to append

**Returns:** Number of bytes written

**Example:**
```php
$storage->append('logs/app.log', "\n[INFO] New entry");
```

#### prepend

Prepend content to a file.

```php
public function prepend(string $path, string $contents): int
```

**Parameters:**
- `$path` - File path
- `$contents` - Content to prepend

**Returns:** Number of bytes written

**Example:**
```php
$storage->prepend('document.txt', "Header\n");
```

### File Metadata

#### size

Get file size in bytes.

```php
public function size(string $path): int
```

**Parameters:**
- `$path` - File path

**Returns:** File size in bytes

**Example:**
```php
$bytes = $storage->size('upload.zip');
$mb = round($bytes / 1024 / 1024, 2);
```

#### lastModified

Get last modification timestamp.

```php
public function lastModified(string $path): int
```

**Parameters:**
- `$path` - File path

**Returns:** Unix timestamp

**Example:**
```php
$timestamp = $storage->lastModified('data.json');
$date = date('Y-m-d H:i:s', $timestamp);
```

#### mimeType

Get file MIME type.

```php
public function mimeType(string $path): ?string
```

**Parameters:**
- `$path` - File path

**Returns:** MIME type string or null

**Example:**
```php
$mime = $storage->mimeType('image.jpg'); // "image/jpeg"
```

### File Operations

#### copy

Copy a file.

```php
public function copy(string $from, string $to): bool
```

**Parameters:**
- `$from` - Source path
- `$to` - Destination path

**Returns:** true on success

**Example:**
```php
$storage->copy('original.txt', 'backup/original.txt');
```

#### move

Move or rename a file.

```php
public function move(string $from, string $to): bool
```

**Parameters:**
- `$from` - Source path
- `$to` - Destination path

**Returns:** true on success

**Example:**
```php
$storage->move('temp/file.txt', 'permanent/file.txt');
```

### Upload/Download

#### upload

Upload a file with unique filename.

```php
public function upload(string $filename, string $path): string
```

**Parameters:**
- `$filename` - Original filename (used for extension)
- `$path` - Path to source file

**Returns:** Public URL of uploaded file

**Example:**
```php
$url = $storage->upload('document.pdf', '/tmp/upload_12345');
// Returns: https://example.com/storage/abc123def456.pdf
```

#### download

Get absolute path for downloading.

```php
public function download(string $path): string
```

**Parameters:**
- `$path` - File path

**Returns:** Absolute filesystem path

**Example:**
```php
$absolutePath = $storage->download('files/report.pdf');
// Returns: /var/www/storage/files/report.pdf
```

#### getPublicUrl

Get public URL for a file.

```php
public function getPublicUrl(string $path): string
```

**Parameters:**
- `$path` - File path

**Returns:** Full public URL

**Example:**
```php
$url = $storage->getPublicUrl('images/logo.png');
// Returns: https://example.com/storage/images/logo.png
```

### Directory Operations

#### makeDirectory

Create a directory.

```php
public function makeDirectory(string $path): bool
```

**Parameters:**
- `$path` - Directory path

**Returns:** true on success

**Example:**
```php
$storage->makeDirectory('uploads/2024/01');
```

#### deleteDirectory

Delete a directory.

```php
public function deleteDirectory(string $path, bool $recursive = false): bool
```

**Parameters:**
- `$path` - Directory path
- `$recursive` - Whether to delete contents (default: false)

**Returns:** true on success

**Example:**
```php
// Delete empty directory
$storage->deleteDirectory('empty-folder');

// Delete with all contents
$storage->deleteDirectory('temp', true);
```

#### files

List files in a directory.

```php
public function files(string $directory = '', bool $recursive = false): array
```

**Parameters:**
- `$directory` - Directory path (default: root)
- `$recursive` - Include subdirectories (default: false)

**Returns:** Array of file paths

**Example:**
```php
// List files in root
$files = $storage->files();

// List files in subdirectory
$files = $storage->files('uploads');

// List all files recursively
$allFiles = $storage->files('uploads', true);
```

#### directories

List directories.

```php
public function directories(string $directory = '', bool $recursive = false): array
```

**Parameters:**
- `$directory` - Parent directory (default: root)
- `$recursive` - Include nested directories (default: false)

**Returns:** Array of directory paths

**Example:**
```php
$dirs = $storage->directories('uploads');
$allDirs = $storage->directories('uploads', true);
```

---

## LocalStorageAdapter

Filesystem storage adapter.

### Constructor

```php
public function __construct(array $config)
```

**Required Configuration:**
- `path` - Absolute path to storage root

**Optional Configuration:**
- `public_url` - Base URL for public access

**Example:**
```php
$adapter = new LocalStorageAdapter([
    'path' => '/var/www/storage',
    'public_url' => 'https://example.com/storage',
]);
```

### Security Features

- Path traversal protection
- Automatic directory creation
- Unique filename generation

---

## MemoryStorageAdapter

In-memory storage adapter.

### Constructor

```php
public function __construct(array $config = [])
```

**Optional Configuration:**
- `public_url` - Base URL for generating URLs

**Example:**
```php
$adapter = new MemoryStorageAdapter([
    'public_url' => 'https://test.example.com',
]);
```

### Additional Methods

#### clear

Clear all stored files and directories.

```php
public function clear(): void
```

#### getFiles

Get internal files array.

```php
public function getFiles(): array
```

#### getDirectories

Get internal directories array.

```php
public function getDirectories(): array
```

---

## StorageException

Exception class for storage errors.

### Factory Methods

```php
// Configuration error
StorageException::missingConfiguration(string $key): self

// Path errors
StorageException::invalidPath(string $path, ?string $reason = null): self
StorageException::fileNotFound(string $path): self
StorageException::directoryNotFound(string $path): self
StorageException::pathTraversal(string $path): self

// Operation errors
StorageException::uploadFailed(string $source, string $dest, ?string $reason = null): self
StorageException::writeFailed(string $path): self
StorageException::readFailed(string $path, ?string $reason = null): self
StorageException::deleteFailed(string $path): self
StorageException::copyFailed(string $source, string $dest, ?string $reason = null): self
StorageException::moveFailed(string $source, string $dest): self

// Driver error
StorageException::unknownDriver(string $driver): self
```

**Example:**
```php
throw StorageException::fileNotFound('document.txt');
throw StorageException::pathTraversal('../etc/passwd');
```

---

## StorageServiceProvider

Service provider for framework integration.

### Methods

#### register

Register storage services with the container.

```php
public function register(ContainerInterface $container): void
```

**Example:**
```php
$container->register(new StorageServiceProvider());
```

---

## Configuration Reference

### Complete Configuration Example

```php
[
    'default' => 'local',

    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/var/www/storage/app',
            'public_url' => 'https://example.com/storage',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'directory' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'path' => '/var/www/storage/public',
            'public_url' => 'https://example.com/files',
        ],

        'temp' => [
            'driver' => 'memory',
            'public_url' => 'https://temp.example.com',
        ],
    ],
]
```

### Driver Options

| Driver | Options |
|--------|---------|
| `local` | `path` (required), `public_url`, `permissions` |
| `memory` | `public_url` |
