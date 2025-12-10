# Installation

## Requirements

- PHP 8.2 or higher
- PDO extension enabled
- One of the following database drivers:
  - SQLite (pdo_sqlite)
  - MySQL 5.7+ (pdo_mysql)
  - PostgreSQL 10+ (pdo_pgsql)

## Composer Installation

```bash
composer require lalaz/database
```

## Manual Installation

If you're not using Composer, you can manually include the package:

1. Download the package from the repository
2. Include the autoloader or manually require the files

```php
require_once 'path/to/database/src/Connection.php';
require_once 'path/to/database/src/ConnectionManager.php';
// ... other required files
```

## Verifying Installation

```php
<?php

require 'vendor/autoload.php';

use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Connection;

$config = [
    'driver' => 'sqlite',
    'connections' => [
        'sqlite' => ['database' => ':memory:'],
    ],
];

$manager = new ConnectionManager($config);
$connection = new Connection($manager);

// Test connection
$result = $connection->select('SELECT 1 as test');
var_dump($result); // [['test' => 1]]

echo "Installation successful!\n";
```

## Database Setup

### SQLite

SQLite requires no server setup. For file-based database:

```php
$config = [
    'driver' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'database' => __DIR__ . '/database/app.sqlite',
        ],
    ],
];

// Ensure directory exists
@mkdir(__DIR__ . '/database', 0755, true);
```

### MySQL

1. Install MySQL server:
   ```bash
   # Ubuntu/Debian
   sudo apt install mysql-server
   
   # macOS with Homebrew
   brew install mysql
   ```

2. Create database:
   ```sql
   CREATE DATABASE myapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'myapp'@'localhost' IDENTIFIED BY 'secret';
   GRANT ALL PRIVILEGES ON myapp.* TO 'myapp'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Configure connection:
   ```php
   $config = [
       'driver' => 'mysql',
       'connections' => [
           'mysql' => [
               'host' => 'localhost',
               'port' => 3306,
               'database' => 'myapp',
               'username' => 'myapp',
               'password' => 'secret',
               'charset' => 'utf8mb4',
               'collation' => 'utf8mb4_unicode_ci',
           ],
       ],
   ];
   ```

### PostgreSQL

1. Install PostgreSQL server:
   ```bash
   # Ubuntu/Debian
   sudo apt install postgresql postgresql-contrib
   
   # macOS with Homebrew
   brew install postgresql
   ```

2. Create database:
   ```sql
   CREATE DATABASE myapp;
   CREATE USER myapp WITH ENCRYPTED PASSWORD 'secret';
   GRANT ALL PRIVILEGES ON DATABASE myapp TO myapp;
   ```

3. Configure connection:
   ```php
   $config = [
       'driver' => 'postgres',
       'connections' => [
           'postgres' => [
               'host' => 'localhost',
               'port' => 5432,
               'database' => 'myapp',
               'username' => 'myapp',
               'password' => 'secret',
               'schema' => 'public',
           ],
       ],
   ];
   ```

## Docker Setup

### MySQL with Docker

```yaml
# docker-compose.yml
version: '3.8'
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: myapp
      MYSQL_USER: myapp
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

### PostgreSQL with Docker

```yaml
# docker-compose.yml
version: '3.8'
services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: myapp
      POSTGRES_USER: myapp
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

## Environment Configuration

Use environment variables for sensitive configuration:

```php
$config = [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'connections' => [
        'mysql' => [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'myapp',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
```

## Service Provider Integration

For Lalaz framework integration:

```php
use Lalaz\Database\DatabaseServiceProvider;

// In your bootstrap
$provider = new DatabaseServiceProvider($container);
$provider->register();

// Access via container
$connection = $container->get(\Lalaz\Database\Connection::class);
```

## Troubleshooting

### PDO Extension Not Found

```bash
# Ubuntu/Debian
sudo apt install php-pdo php-mysql php-pgsql php-sqlite3

# CentOS/RHEL
sudo yum install php-pdo php-mysql php-pgsql

# macOS with Homebrew PHP
# PDO extensions are usually included
```

### Connection Refused

1. Verify the database server is running
2. Check host and port configuration
3. Verify firewall allows connections
4. For Docker, ensure port mapping is correct

### Access Denied

1. Verify username and password
2. Check user privileges in database
3. For MySQL, verify authentication method:
   ```sql
   ALTER USER 'myapp'@'localhost' IDENTIFIED WITH mysql_native_password BY 'secret';
   ```

### Character Encoding Issues

Always use UTF-8:
```php
'charset' => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',
```

For PostgreSQL:
```sql
CREATE DATABASE myapp ENCODING 'UTF8';
```
