# Testing Guide

This guide covers testing strategies and best practices for the Lalaz Database package.

## Test Structure

```
tests/
├── bootstrap.php              # Test autoloader
├── Common/
│   ├── DatabaseUnitTestCase.php        # Base for unit tests
│   └── DatabaseIntegrationTestCase.php # Base for integration tests
├── Unit/
│   ├── BlueprintTest.php
│   ├── ColumnDefinitionTest.php
│   ├── ExpressionTest.php
│   ├── GrammarTest.php
│   └── ...
└── Integration/
    ├── ConnectionTest.php
    ├── TransactionTest.php
    ├── QueryBuilderIntegrationTest.php
    └── ...
```

## Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run with testdox output
./vendor/bin/phpunit --testdox

# Run specific test file
./vendor/bin/phpunit tests/Unit/BlueprintTest.php

# Run specific test method
./vendor/bin/phpunit --filter test_creates_primary_key

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

## Unit Testing

### Using DatabaseUnitTestCase

```php
<?php

namespace Lalaz\Database\Tests\Unit;

use Lalaz\Database\Tests\Common\DatabaseUnitTestCase;
use Lalaz\Database\Schema\Blueprint;

class MyTest extends DatabaseUnitTestCase
{
    public function test_something(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->id();
        $blueprint->string('name');
        
        $this->assertCount(2, $blueprint->columns());
    }
}
```

### Testing QueryBuilder

```php
public function test_query_builder_select(): void
{
    $connection = $this->createSqliteConnection();
    $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    
    $query = $connection->table('users')
        ->select('id', 'name')
        ->where('id', '>', 0);
    
    $sql = $query->toSql();
    
    $this->assertStringContainsString('SELECT', $sql);
    $this->assertStringContainsString('WHERE', $sql);
}
```

### Testing Grammar

```php
public function test_grammar_compiles_select(): void
{
    $grammar = new SqliteGrammar();
    
    $connection = $this->createSqliteConnection();
    $query = new QueryBuilder($connection, $grammar);
    $query->from('users')->select('id', 'name');
    
    $sql = $grammar->compileSelect($query);
    
    $this->assertStringContainsString('"id"', $sql);
    $this->assertStringContainsString('"name"', $sql);
}
```

## Integration Testing

### Using DatabaseIntegrationTestCase

```php
<?php

namespace Lalaz\Database\Tests\Integration;

use Lalaz\Database\Tests\Common\DatabaseIntegrationTestCase;

class TransactionTest extends DatabaseIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tables
        $this->schema->create('accounts', function ($table) {
            $table->id();
            $table->decimal('balance', 10, 2);
        });
    }
    
    public function test_transaction_commits(): void
    {
        $this->connection->transaction(function ($conn) {
            $conn->table('accounts')->insert(['balance' => 100.00]);
        });
        
        $count = $this->connection->table('accounts')->count();
        $this->assertEquals(1, $count);
    }
    
    public function test_transaction_rollback_on_exception(): void
    {
        try {
            $this->connection->transaction(function ($conn) {
                $conn->table('accounts')->insert(['balance' => 100.00]);
                throw new \Exception('Rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }
        
        $count = $this->connection->table('accounts')->count();
        $this->assertEquals(0, $count);
    }
}
```

### Testing Migrations

```php
public function test_migration_runs_up_and_down(): void
{
    $migration = new class extends Migration {
        public function up(SchemaBuilder $schema): void
        {
            $schema->create('test_table', function ($table) {
                $table->id();
                $table->string('name');
            });
        }
        
        public function down(SchemaBuilder $schema): void
        {
            $schema->dropIfExists('test_table');
        }
    };
    
    // Run up
    $migration->up($this->schema);
    $this->assertTrue($this->schema->hasTable('test_table'));
    
    // Run down
    $migration->down($this->schema);
    $this->assertFalse($this->schema->hasTable('test_table'));
}
```

## Mocking

### Mocking Connection

```php
use PHPUnit\Framework\MockObject\MockObject;

public function test_with_mock_connection(): void
{
    /** @var Connection&MockObject $connection */
    $connection = $this->createMock(Connection::class);
    
    $connection->expects($this->once())
        ->method('select')
        ->with(
            $this->stringContains('SELECT'),
            $this->anything()
        )
        ->willReturn([['id' => 1, 'name' => 'Test']]);
    
    $result = $connection->select('SELECT * FROM users');
    
    $this->assertCount(1, $result);
}
```

### Mocking ConnectionManager

```php
public function test_with_mock_manager(): void
{
    $manager = $this->createMockManager([
        'driver' => 'sqlite',
    ]);
    
    $connection = new Connection($manager);
    
    $this->assertEquals('sqlite', $connection->getDriverName());
}
```

## Testing Best Practices

### 1. Use In-Memory SQLite

```php
protected function createSqliteConnection(): Connection
{
    $manager = new ConnectionManager([
        'driver' => 'sqlite',
        'connections' => [
            'sqlite' => ['database' => ':memory:'],
        ],
    ]);
    
    return new Connection($manager);
}
```

### 2. Isolate Tests

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Each test gets fresh database
    $this->connection = $this->createSqliteConnection();
}

protected function tearDown(): void
{
    // Clean up
    $this->connection = null;
    
    parent::tearDown();
}
```

### 3. Test Edge Cases

```php
public function test_empty_insert_returns_true(): void
{
    $result = $this->connection->table('users')->insert([]);
    $this->assertTrue($result);
}

public function test_where_in_with_empty_array(): void
{
    $results = $this->connection->table('users')
        ->whereIn('id', [])
        ->get();
    
    $this->assertEmpty($results);
}
```

### 4. Test SQL Generation

```php
public function test_complex_query_generates_correct_sql(): void
{
    $query = $this->connection->table('users')
        ->select('users.id', 'users.name')
        ->join('posts', 'users.id', '=', 'posts.user_id')
        ->where('users.active', true)
        ->whereIn('users.role', ['admin', 'moderator'])
        ->orderBy('users.name')
        ->limit(10);
    
    $sql = $query->toSql();
    
    $this->assertStringContainsStringIgnoringCase('JOIN', $sql);
    $this->assertStringContainsStringIgnoringCase('WHERE', $sql);
    $this->assertStringContainsStringIgnoringCase('IN', $sql);
    $this->assertStringContainsStringIgnoringCase('ORDER BY', $sql);
    $this->assertStringContainsStringIgnoringCase('LIMIT', $sql);
}
```

### 5. Test Bindings

```php
public function test_bindings_are_correct(): void
{
    $query = $this->connection->table('users')
        ->where('name', 'John')
        ->where('age', '>', 18)
        ->whereIn('role', ['admin', 'user']);
    
    $bindings = $query->bindings();
    
    $this->assertContains('John', $bindings);
    $this->assertContains(18, $bindings);
    $this->assertContains('admin', $bindings);
    $this->assertContains('user', $bindings);
}
```

## PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    
    <coverage>
        <report>
            <html outputDirectory="coverage"/>
        </report>
    </coverage>
</phpunit>
```

## Debugging Tests

### Dump SQL Queries

```php
public function test_debug_query(): void
{
    $query = $this->connection->table('users')
        ->where('active', true);
    
    // Dump SQL
    var_dump($query->toSql());
    
    // Dump bindings
    var_dump($query->bindings());
    
    $this->assertTrue(true);
}
```

### Query Logging

```php
public function test_with_query_logging(): void
{
    $queries = [];
    
    $this->manager->listenQuery(function ($event) use (&$queries) {
        $queries[] = $event;
    });
    
    $this->connection->table('users')->get();
    $this->connection->table('posts')->get();
    
    $this->assertCount(2, $queries);
}
```

## Code Coverage

```bash
# Generate HTML coverage
./vendor/bin/phpunit --coverage-html coverage/

# Generate text coverage
./vendor/bin/phpunit --coverage-text

# Coverage with filter
./vendor/bin/phpunit --coverage-html coverage/ --filter QueryBuilder
```

Target coverage: 80%+ for critical paths (Connection, QueryBuilder, Schema).
