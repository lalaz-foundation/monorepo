# Quick Start

Get up and running with Lalaz Storage in minutes.

## Basic Setup

```php
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;

// Configure storage
Storage::setManager(new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/var/www/storage',
            'public_url' => 'https://example.com/storage',
        ],
    ],
]));
```

## Writing Files

### Simple Write

```php
// Write text content
Storage::put('documents/readme.txt', 'Welcome to Lalaz Storage!');

// Write binary content
Storage::put('images/logo.png', file_get_contents('/tmp/logo.png'));

// Write JSON data
Storage::put('data/config.json', json_encode(['setting' => 'value']));
```

### Append and Prepend

```php
// Add content to end of file
Storage::put('logs/app.log', "Initial log\n");
Storage::disk()->append('logs/app.log', "New entry\n");

// Add content to beginning of file
Storage::disk()->prepend('logs/app.log', "Header: App Log\n");
```

### Upload Files

```php
// Upload with unique filename generation
$tempFile = '/tmp/user_upload.pdf';
$publicUrl = Storage::disk()->upload('document.pdf', $tempFile);
// Returns: https://example.com/storage/abc123def456.pdf
```

## Reading Files

### Get Content

```php
// Read file content
$content = Storage::get('documents/readme.txt');

// Check before reading
if (Storage::exists('documents/readme.txt')) {
    $content = Storage::get('documents/readme.txt');
}
```

### Get Metadata

```php
// Get file size in bytes
$size = Storage::disk()->size('documents/readme.txt');

// Get last modified timestamp
$timestamp = Storage::disk()->lastModified('documents/readme.txt');
$date = date('Y-m-d H:i:s', $timestamp);

// Get MIME type
$mimeType = Storage::disk()->mimeType('documents/readme.txt');
```

### Get URL

```php
// Get public URL for a file
$url = Storage::disk()->getPublicUrl('documents/readme.txt');
// Returns: https://example.com/storage/documents/readme.txt
```

## Deleting Files

```php
// Delete a single file
Storage::delete('documents/readme.txt');

// Check and delete
if (Storage::exists('temp/cache.txt')) {
    Storage::delete('temp/cache.txt');
}
```

## Working with Directories

### Create Directories

```php
// Create a directory
Storage::disk()->makeDirectory('uploads');

// Create nested directories
Storage::disk()->makeDirectory('uploads/images/thumbnails');
```

### List Contents

```php
// List files in a directory
$files = Storage::disk()->files('uploads');
// ['file1.txt', 'file2.pdf']

// List files recursively
$allFiles = Storage::disk()->files('uploads', true);
// ['file1.txt', 'images/photo.jpg', 'images/thumbnails/thumb.jpg']

// List directories
$dirs = Storage::disk()->directories('uploads');
// ['images', 'documents']

// List directories recursively
$allDirs = Storage::disk()->directories('uploads', true);
// ['images', 'images/thumbnails', 'documents']
```

### Delete Directories

```php
// Delete empty directory
Storage::disk()->deleteDirectory('empty-folder');

// Delete directory with all contents (recursive)
Storage::disk()->deleteDirectory('uploads/temp', true);
```

## Copy and Move

```php
// Copy a file
Storage::disk()->copy('source/original.txt', 'backup/original.txt');

// Move a file (rename)
Storage::disk()->move('old-name.txt', 'new-name.txt');

// Move to different directory
Storage::disk()->move('temp/file.txt', 'permanent/file.txt');
```

## Multi-Disk Usage

### Configure Multiple Disks

```php
Storage::setManager(new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => '/var/www/storage/app',
            'public_url' => 'https://example.com/storage',
        ],
        'public' => [
            'driver' => 'local',
            'path' => '/var/www/storage/public',
            'public_url' => 'https://example.com/files',
        ],
        'temp' => [
            'driver' => 'memory',
        ],
    ],
]));
```

### Switch Between Disks

```php
// Use default disk
Storage::put('private/secret.txt', 'Private data');

// Use specific disk
Storage::disk('public')->put('assets/image.jpg', $imageData);
Storage::disk('temp')->put('cache/key.json', $cacheData);

// Get content from specific disk
$image = Storage::disk('public')->get('assets/image.jpg');
```

### Change Default Disk

```php
$manager = Storage::getManager();
$manager->setDefaultDriver('public');

// Now Storage::put() uses 'public' disk
Storage::put('uploads/file.txt', 'content');
```

## Error Handling

```php
use Lalaz\Storage\Exceptions\StorageException;

try {
    $content = Storage::get('nonexistent.txt');
} catch (StorageException $e) {
    echo "Storage error: " . $e->getMessage();
}

// Handle specific scenarios
try {
    Storage::put('../outside.txt', 'malicious content');
} catch (StorageException $e) {
    // Path traversal attempt blocked
    echo "Security violation: " . $e->getMessage();
}
```

## Using Adapters Directly

For more control, use adapters without the facade:

```php
use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;

// Local filesystem adapter
$local = new LocalStorageAdapter([
    'path' => '/var/www/storage',
    'public_url' => 'https://example.com/storage',
]);

// In-memory adapter (great for testing)
$memory = new MemoryStorageAdapter([
    'public_url' => 'https://test.example.com',
]);

// Use directly
$local->put('file.txt', 'content');
$memory->put('temp.txt', 'temporary data');
```

## Complete Example

Here's a complete example of a file upload handler:

```php
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;
use Lalaz\Storage\Exceptions\StorageException;

// Setup
Storage::setManager(new StorageManager([
    'default' => 'uploads',
    'disks' => [
        'uploads' => [
            'driver' => 'local',
            'path' => '/var/www/storage/uploads',
            'public_url' => 'https://example.com/uploads',
        ],
    ],
]));

// Handle file upload
function handleUpload(string $originalName, string $tempPath): array
{
    try {
        // Generate path based on date
        $date = date('Y/m/d');
        $uploadPath = "files/{$date}";

        // Ensure directory exists
        Storage::disk()->makeDirectory($uploadPath);

        // Upload with unique filename
        $publicUrl = Storage::disk()->upload($originalName, $tempPath);

        // Get file info
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = basename(parse_url($publicUrl, PHP_URL_PATH));

        return [
            'success' => true,
            'url' => $publicUrl,
            'filename' => $filename,
            'original_name' => $originalName,
            'size' => Storage::disk()->size("{$uploadPath}/{$filename}"),
        ];
    } catch (StorageException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

// Usage
$result = handleUpload('document.pdf', $_FILES['file']['tmp_name']);
if ($result['success']) {
    echo "File uploaded: " . $result['url'];
}
```

## Next Steps

- [Concepts](concepts.md) - Understand the architecture
- [API Reference](api-reference.md) - Complete method documentation
- [Testing](testing.md) - Testing strategies with Storage
