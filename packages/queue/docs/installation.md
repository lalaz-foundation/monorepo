# Installation

This guide covers installing and configuring the Queue package.

---

## Requirements

- PHP 8.2+
- Lalaz Framework
- PDO extension (for database drivers)

---

## Installation

Install via Composer:

```bash
composer require lalaz/queue
```

---

## Configuration

### 1. Create Configuration File

Create `config/queue.php`:

```php
<?php

return [
    'enabled' => true,
    'driver' => 'memory', // memory | mysql | pgsql | sqlite
    'connection' => null, // reuse default database connection
    'job_timeout' => 300, // seconds before a processing job is considered stuck
    'tables' => [
        'jobs' => 'jobs',
        'failed' => 'failed_jobs',
        'logs' => 'job_logs',
    ],
];
```

### 2. Configure Environment Variables

```env
QUEUE_ENABLED=true
QUEUE_DRIVER=mysql
QUEUE_JOB_TIMEOUT=300
```

---

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable/disable queue processing |
| `driver` | string | `memory` | Queue driver (memory, mysql, pgsql, sqlite) |
| `connection` | string\|null | `null` | Database connection name |
| `job_timeout` | int | `300` | Seconds before a job is considered stuck |
| `tables.jobs` | string | `jobs` | Main jobs table name |
| `tables.failed` | string | `failed_jobs` | Failed jobs table name |
| `tables.logs` | string | `job_logs` | Job logs table name |

---

## Database Setup

If using a database driver, run the migration:

```bash
php lalaz migrate
```

Or create the tables manually:

### Jobs Table

```sql
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL DEFAULT 'default',
    task VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 5,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    timeout INT NOT NULL DEFAULT 300,
    backoff_strategy VARCHAR(50) DEFAULT 'exponential',
    retry_delay INT DEFAULT 60,
    tags JSON,
    last_error TEXT,
    available_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status_available (status, available_at),
    INDEX idx_queue_status (queue, status)
);
```

### Failed Jobs Table

```sql
CREATE TABLE failed_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    task VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT,
    stack_trace TEXT,
    failed_at DATETIME NOT NULL,
    total_attempts INT NOT NULL DEFAULT 0,
    retry_history JSON,
    original_job_id INT,
    priority INT DEFAULT 5,
    tags JSON
);
```

### Job Logs Table

```sql
CREATE TABLE job_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT,
    queue VARCHAR(255),
    task VARCHAR(255),
    level VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    execution_time FLOAT,
    memory_usage INT,
    created_at DATETIME NOT NULL,
    INDEX idx_job_id (job_id),
    INDEX idx_level (level)
);
```

---

## Register Service Provider

Add the service provider to your application:

```php
// config/app.php
return [
    'providers' => [
        Lalaz\Queue\QueueServiceProvider::class,
    ],
];
```

---

## Verify Installation

Test the installation:

```php
use Lalaz\Queue\QueueManager;

$manager = resolve(QueueManager::class);
$stats = $manager->getStats();

print_r($stats);
```

---

## Next Steps

- [Quick Start](quick-start.md) - Create your first job
- [Drivers](drivers/index.md) - Configure queue drivers
- [Core Concepts](concepts.md) - Learn how queues work
