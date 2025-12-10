<?php

declare(strict_types=1);

namespace Lalaz\Database\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

final class CraftSeederCommand implements CommandInterface
{
    public function name(): string
    {
        return 'craft:seeder';
    }

    public function description(): string
    {
        return 'Generate a new database seeder class.';
    }

    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Seeder class name (e.g., DatabaseSeeder).',
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
                    'Directory where the seeder will be created (default: database/seeders).',
                'required' => false,
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!is_string($name) || trim($name) === '') {
            $output->error(
                'Usage: php lalaz craft:seeder <Name> [--path=database/seeders]',
            );
            return 1;
        }

        $path =
            $input->option('path') ??
            $input->option('p') ??
            'database/seeders';

        $directory = rtrim($path, '/');
        if (
            !is_dir($directory) &&
            !mkdir($directory, 0777, true) &&
            !is_dir($directory)
        ) {
            $output->error("Unable to create directory: {$directory}");
            return 1;
        }

        $fileName = $directory . '/' . $name . '.php';
        if (file_exists($fileName)) {
            $output->error("Seeder already exists: {$fileName}");
            return 1;
        }

        $stub = $this->stub($name);
        if (file_put_contents($fileName, $stub) === false) {
            $output->error("Failed to write seeder file: {$fileName}");
            return 1;
        }

        $output->writeln("Created seeder: {$fileName}");
        return 0;
    }

    private function stub(string $name): string
    {
        return <<<PHP
<?php

use Lalaz\Database\Seeding\Seeder;

class {$name} extends Seeder
{
    public function run(): void
    {
        // Seed your data here, e.g.:
        // \$this->call([OtherSeeder::class]);
    }
}
PHP;
    }
}
