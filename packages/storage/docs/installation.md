# Installation

This guide covers installing and configuring the Lalaz Storage package.

## Requirements

- PHP 8.2 or higher
- Composer
- (Optional) Lalaz Framework for container integration

## Installing via Composer

```bash
composer require lalaz/storage
```

## Manual Installation

If you prefer to install manually:

1. Clone or download the package
2. Include the autoloader in your application

```php
require_once 'vendor/autoload.php';
```

## Basic Configuration

### Standalone Usage

```php
use Lalaz\Storage\Storage;
use Lalaz\Storage\StorageManager;

// Create a storage manager with configuration
$manager = new StorageManager([
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => __DIR__ . '/storage',
            'public_url' => 'https://example.com/storage',
        ],
    ],
]);

// Set the global manager
Storage::setManager($manager);

// Now you can use the Storage facade
Storage::put('file.txt', 'Hello World');
```

### Framework Integration

With the Lalaz Framework, register the service provider:

```php
use Lalaz\Storage\Providers\StorageServiceProvider;

// In your bootstrap or container setup
$container->register(new StorageServiceProvider());
```

Then configure in your `config/storage.php`:

```php
return [
    'default' => env('STORAGE_DRIVER', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => storage_path('app'),
            'public_url' => env('APP_URL') . '/storage',
        ],

        'public' => [
            'driver' => 'local',
            'path' => storage_path('app/public'),
            'public_url' => env('APP_URL') . '/storage/public',
        ],

        'temp' => [
            'driver' => 'memory',
        ],
    ],
];
```

## Configuration Options

### Local Driver Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `driver` | string | Yes | Must be `'local'` |
| `path` | string | Yes | Absolute path to storage directory |
| `public_url` | string | No | Base URL for public file access |
| `permissions.file.public` | int | No | Permission for public files (default: 0644) |
| `permissions.file.private` | int | No | Permission for private files (default: 0600) |
| `permissions.directory.public` | int | No | Permission for public directories (default: 0755) |
| `permissions.directory.private` | int | No | Permission for private directories (default: 0700) |

### Memory Driver Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `driver` | string | Yes | Must be `'memory'` |
| `public_url` | string | No | Base URL for generating URLs |

## Directory Structure

Create the following directory structure for local storage:

```
your-project/
├── storage/
│   ├── app/           # Application files
│   │   └── public/    # Publicly accessible files
│   ├── logs/          # Log files
│   └── cache/         # Cache files
├── public/
│   └── storage/       # Symlink to storage/app/public
└── ...
```

## Environment Variables

Recommended environment variables for configuration:

```env
# Storage Configuration
STORAGE_DRIVER=local
STORAGE_PATH=/var/www/storage
STORAGE_URL=https://example.com/storage

# Optional cloud storage
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=us-east-1
# AWS_BUCKET=
```

## Creating Storage Directory

Ensure the storage directory exists and is writable:

```bash
mkdir -p storage/app/public
chmod -R 775 storage
```

## Public Symlink

For publicly accessible files, create a symlink:

```bash
ln -s /path/to/storage/app/public /path/to/public/storage
```

## Verification

Verify your installation:

```php
use Lalaz\Storage\Storage;

// Test write
Storage::put('test.txt', 'Installation successful!');

// Test read
$content = Storage::get('test.txt');
echo $content; // "Installation successful!"

// Test exists
var_dump(Storage::exists('test.txt')); // bool(true)

// Clean up
Storage::delete('test.txt');
```

## Troubleshooting

### Permission Denied

If you encounter permission errors:

```bash
# Fix directory permissions
chmod -R 755 storage
chown -R www-data:www-data storage
```

### File Not Found

Ensure the `path` configuration is an absolute path:

```php
// Wrong - relative path
'path' => 'storage'

// Correct - absolute path
'path' => __DIR__ . '/storage'
'path' => '/var/www/app/storage'
```

### Configuration Missing

Make sure to set the manager before using the facade:

```php
// This will use a default empty configuration
$manager = Storage::getManager();

// Always set your configuration explicitly
Storage::setManager(new StorageManager($config));
```

## Next Steps

- [Quick Start](quick-start.md) - Learn basic usage patterns
- [Concepts](concepts.md) - Understand the architecture
- [API Reference](api-reference.md) - Explore all available methods
