<?php declare(strict_types=1);

use Lalaz\Database\Connection;
use Lalaz\Database\ConnectionManager;
use Lalaz\Database\Migrations\MigrationRepository;
use Lalaz\Database\Migrations\Migrator;
use Lalaz\Database\Schema\SchemaBuilder;

/**
 * @return array{manager: ConnectionManager, connection: Connection, schema: SchemaBuilder}
 */
function sqlite_components(): array
{
    $manager = new ConnectionManager([
        "driver" => "sqlite",
        "connections" => [
            "sqlite" => [
                "path" => ":memory:",
                "options" => [],
            ],
        ],
    ]);

    $connection = new Connection($manager);
    $schema = new SchemaBuilder($connection, $manager);

    return [
        "manager" => $manager,
        "connection" => $connection,
        "schema" => $schema,
    ];
}

function sqlite_migrator(): Migrator
{
    return sqlite_migrator_from(sqlite_components());
}

/**
 * @param array{manager: ConnectionManager, connection: Connection, schema: SchemaBuilder} $components
 */
function sqlite_migrator_from(array $components): Migrator
{
    $repository = new MigrationRepository(
        $components["connection"],
        $components["schema"],
    );

    return new Migrator(
        $components["schema"],
        $repository,
        $components["connection"],
    );
}
