# Lalaz Database Documentation

Welcome to the Lalaz Database documentation. This package provides a comprehensive database abstraction layer with connection pooling, query building, schema management, and migrations.

## Table of Contents

1. [Quick Start](quick-start.md) - Get up and running in minutes
2. [Installation](installation.md) - Detailed installation instructions
3. [Concepts](concepts.md) - Core concepts and architecture
4. [API Reference](api-reference.md) - Complete API documentation
5. [Testing](testing.md) - Testing guide and best practices
6. [Glossary](glossary.md) - Terms and definitions

## Overview

The Database package is designed with the following principles:

- **Performance First**: Connection pooling, read replicas, and query optimization
- **Developer Experience**: Fluent interfaces and intuitive APIs
- **Database Agnostic**: Support for SQLite, MySQL, and PostgreSQL
- **Type Safety**: Full PHP 8.2+ type declarations
- **Testability**: Easy mocking and in-memory database support

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Application Layer                       │
├─────────────────────────────────────────────────────────────┤
│  QueryBuilder  │  SchemaBuilder  │  Migrator  │  Seeder     │
├─────────────────────────────────────────────────────────────┤
│                        Connection                            │
│         (Transaction Management, Query Profiling)            │
├─────────────────────────────────────────────────────────────┤
│                    ConnectionManager                         │
│      (Pooling, Read Replicas, Driver Resolution)            │
├─────────────────────────────────────────────────────────────┤
│  SqliteConnector  │  MySqlConnector  │  PostgresConnector   │
├─────────────────────────────────────────────────────────────┤
│                          PDO                                 │
└─────────────────────────────────────────────────────────────┘
```

## Quick Example

```php
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Connection;

// Configure
$manager = new ConnectionManager([
    'driver' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => 'localhost',
            'database' => 'myapp',
            'username' => 'root',
            'password' => '',
        ],
    ],
]);

// Connect
$connection = new Connection($manager);

// Query
$users = $connection->table('users')
    ->where('active', true)
    ->orderBy('name')
    ->get();

// Transaction
$connection->transaction(function ($conn) {
    $conn->table('orders')->insert(['user_id' => 1, 'total' => 99.99]);
});
```

## Features by Category

### Connection Management
- Connection pooling with configurable limits
- Read replica support with sticky sessions
- Automatic driver detection and configuration
- Query logging and profiling

### Query Builder
- Fluent interface for all SQL operations
- Support for subqueries, unions, and joins
- Aggregate functions (count, sum, avg, min, max)
- Raw expressions for advanced queries

### Schema Management
- Database-agnostic table definitions
- Blueprint-based column definitions
- Index and foreign key management
- Migration support

### Migrations
- Version-controlled schema changes
- Batch tracking for rollbacks
- CLI commands for migration management
- Up/down migration support

### Seeding
- Database seeding for development
- Seeder runner for automation
- Integration with migration workflow

## Getting Help

- Check the [API Reference](api-reference.md) for detailed method signatures
- Review [Concepts](concepts.md) for architectural understanding
- See [Testing](testing.md) for testing strategies
