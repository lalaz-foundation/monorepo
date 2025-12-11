<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a new ORM model class.
 *
 * Creates a new model extending the base Model class with
 * optional custom table name and fillable properties.
 *
 * Usage: php lalaz craft:model User
 *        php lalaz craft:model User --table=users
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftModelCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'craft:model';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Generate a new model class for Lalaz ORM.';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Model class name (e.g., User or App\\Models\\User).',
                'required' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [
            [
                'name' => 'table',
                'description' => 'Custom table name (optional).',
                'required' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!is_string($name) || trim($name) === '') {
            $output->error(
                'Usage: php lalaz craft:model User [--table=users]',
            );
            return 1;
        }

        [$class, $path] = Generator::normalizeClass($name, 'App\\Models');

        $file = getcwd() . '/app/' . $path . '.php';
        if (file_exists($file)) {
            $output->error("Model already exists: {$file}");
            return 1;
        }

        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);

        $table = $input->option('table');
        $stub = $this->stub($namespace, $short, $table);

        Generator::writeFile($file, $stub);
        $output->writeln("Created model: {$file}");
        return 0;
    }

    /**
     * Generates the model stub code.
     *
     * @param string      $namespace The model namespace
     * @param string      $class     The model class name
     * @param string|null $table     Custom table name (optional)
     *
     * @return string The generated PHP code
     */
    private function stub(
        string $namespace,
        string $class,
        ?string $table,
    ): string {
        $tableProperty = $table !== null
            ? "    protected ?string \$table = '{$table}';\n\n"
            : '';

        return <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Orm\Model;

class {$class} extends Model
{
{$tableProperty}    protected array \$fillable = [];
}
PHP;
    }
}
