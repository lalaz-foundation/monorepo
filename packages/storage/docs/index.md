# Lalaz Storage Documentation

Welcome to the Lalaz Storage documentation. This package provides a unified file storage abstraction layer with support for multiple drivers and seamless disk switching.

## Table of Contents

1. [Installation](installation.md) - Getting started with Lalaz Storage
2. [Quick Start](quick-start.md) - Basic usage examples
3. [Concepts](concepts.md) - Core concepts and architecture
4. [API Reference](api-reference.md) - Complete API documentation
5. [Testing](testing.md) - Testing strategies and utilities
6. [Glossary](glossary.md) - Terminology and definitions

## Overview

Lalaz Storage provides a powerful yet simple abstraction for file storage operations. Whether you're storing files on the local filesystem, in memory for testing, or on cloud providers, the API remains consistent.

### Key Features

- **Unified API**: Same methods work across all storage drivers
- **Multi-Disk Support**: Configure multiple storage locations and switch between them
- **Security First**: Built-in protection against path traversal attacks
- **Extensible**: Easy to add custom storage drivers
- **Testing Friendly**: In-memory adapter for fast, isolated tests

### Quick Example

```php
use Lalaz\Storage\Storage;

// Configure the storage
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

// Store a file
Storage::put('documents/report.pdf', $pdfContent);

// Check if file exists
if (Storage::exists('documents/report.pdf')) {
    // Get the public URL
    $url = Storage::disk()->getPublicUrl('documents/report.pdf');
}

// Clean up
Storage::delete('documents/report.pdf');
```

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Code                          │
├─────────────────────────────────────────────────────────────┤
│                     Storage Facade                           │
│                    (Static API Access)                       │
├─────────────────────────────────────────────────────────────┤
│                    StorageManager                            │
│              (Multi-Disk Driver Factory)                     │
├─────────────────┬─────────────────┬─────────────────────────┤
│ LocalStorage    │ MemoryStorage   │    Custom Drivers       │
│ Adapter         │ Adapter         │                         │
├─────────────────┴─────────────────┴─────────────────────────┤
│                   StorageInterface                           │
│                 (Common Contract)                            │
└─────────────────────────────────────────────────────────────┘
```

## Requirements

- PHP 8.2 or higher
- Composer for package management
- Lalaz Framework (optional, for service provider integration)

## Getting Help

- Check the [API Reference](api-reference.md) for detailed method documentation
- Review the [Concepts](concepts.md) guide for understanding the architecture
- See [Testing](testing.md) for testing strategies

## License

This package is open-sourced software licensed under the MIT license.
