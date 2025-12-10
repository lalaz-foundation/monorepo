<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

final class CraftMigrationCommand implements CommandInterface
{
    public function name(): string
    {
        return 'craft:migration';
    }

    public function description(): string
    {
        return 'Generate a new migration file.';
    }

    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' =>
                    'The migration name (e.g., create_users_table).',
                'required' => true,
            ],
        ];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'path',
                'short' => 'p',
                'description' =>
                    'Directory where the migration will be created (default: database/migrations).',
                'required' => false,
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!is_string($name) || trim($name) === '') {
            $output->error(
                'Usage: php lalaz craft:migration <name> [--path=database/migrations]',
            );
            return 1;
        }

        $path =
            $input->option('path') ??
            ($input->option('p') ?? 'database/migrations');
        $directory = rtrim($path, '/');
        if (
            !is_dir($directory) &&
            !mkdir($directory, 0777, true) &&
            !is_dir($directory)
        ) {
            $output->error("Unable to create directory: {$directory}");
            return 1;
        }

        $timestamp = date('YmdHis');
        $fileName =
            $directory .
            '/' .
            $timestamp .
            '_' .
            $this->slugify($name) .
            '.php';

        $stub = $this->stub($name);
        if (file_put_contents($fileName, $stub) === false) {
            $output->error("Failed to write migration file: {$fileName}");
            return 1;
        }

        $output->writeln("Created migration: {$fileName}");
        return 0;
    }

    private function stub(string $name): string
    {
        [$table, $action] = $this->inferTableAndAction($name);
        $down =
            $action === 'create'
                ? "\$schema->drop('{$table}');"
                : '// reverse your changes';

        $body =
            $action === 'create'
                ? "        \$schema->create('{$table}', function (\$table): void {\n            \$table->increments('id');\n            \$table->timestamps();\n        });"
                : "        \$schema->table('{$table}', function (\$table): void {\n            // add/modify columns here\n        });";

        return <<<PHP
        <?php

        use Lalaz\Database\Migrations\Migration;
        use Lalaz\Database\Schema\SchemaBuilder;

        return new class extends Migration {
            public function up(SchemaBuilder \$schema): void
            {
        {$body}
            }

            public function down(SchemaBuilder \$schema): void
            {
                {$down}
            }
        };
        PHP;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? $value;
        return trim($value, '_');
    }

    /**
     * @return array{0:string,1:string} table, action
     */
    private function inferTableAndAction(string $name): array
    {
        $slug = $this->slugify($name);

        if (preg_match('/^create_(.+)_table$/', $slug, $m)) {
            return [$m[1], 'create'];
        }

        if (preg_match('/^add_.+_to_(.+)_table$/', $slug, $m)) {
            return [$m[1], 'table'];
        }

        if (preg_match('/^update_(.+)_table$/', $slug, $m)) {
            return [$m[1], 'table'];
        }

        return ['example_table', 'create'];
    }
}
