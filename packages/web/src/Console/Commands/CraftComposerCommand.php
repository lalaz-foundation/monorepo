<?php

declare(strict_types=1);

namespace Lalaz\Web\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Generators\Generator;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that generates a ViewComposer class.
 *
 * Creates a new view composer extending Composer
 * with the compose method signature. Automatically appends
 * "Composer" suffix if not provided.
 *
 * Usage: php lalaz craft:composer Navigation
 *        php lalaz craft:composer NavigationComposer
 *        php lalaz craft:composer Admin/SidebarComposer
 *
 * @package lalaz/web
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CraftComposerCommand implements CommandInterface
{
    /**
     * Get the command name.
     *
     * @return string The command name.
     */
    public function name(): string
    {
        return 'craft:composer';
    }

    /**
     * Get the command description.
     *
     * @return string The command description.
     */
    public function description(): string
    {
        return 'Generate a ViewComposer class';
    }

    /**
     * Get the command arguments definition.
     *
     * @return array<int, array<string, mixed>> The arguments configuration.
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'Composer class name (e.g., Navigation, Admin/Sidebar)',
                'optional' => false,
            ],
        ];
    }

    /**
     * Get the command options definition.
     *
     * @return array<int, array<string, mixed>> The options configuration.
     */
    public function options(): array
    {
        return [
            [
                'name' => 'pattern',
                'shortcut' => 'p',
                'description' => 'View pattern to match (e.g., *, pages/*, layouts/main)',
                'requiresValue' => true,
            ],
        ];
    }

    /**
     * Execute the command.
     *
     * @param Input $input The command input.
     * @param Output $output The command output.
     * @return int Exit code (0 for success, non-zero for failure).
     */
    public function handle(Input $input, Output $output): int
    {
        $name = $input->argument(0);
        if (!$name) {
            $output->error('Usage: php lalaz craft:composer Navigation');
            $output->writeln('');
            $output->writeln('Examples:');
            $output->writeln('  php lalaz craft:composer Navigation');
            $output->writeln('  php lalaz craft:composer Admin/Sidebar');
            $output->writeln('  php lalaz craft:composer Header --pattern=layouts/*');
            return 1;
        }

        // Ensure name ends with "Composer"
        $name = Generator::ensureSuffix($name, 'Composer');

        [$class, $path] = Generator::normalizeClass(
            $name,
            'App\\Views\\Composers',
        );
        $file = getcwd() . '/app/Views/Composers/' . $this->getRelativePath($path) . '.php';

        $namespace = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);

        $pattern = $input->option('pattern') ?? '*';

        $stub = $this->generateStub($namespace, $short);

        Generator::writeFile($file, $stub);

        $output->writeln("✓ ViewComposer created: {$file}");
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln('  1. Implement the compose() method with your data');
        $output->writeln('  2. Register in config/views.php:');
        $output->writeln('');
        $output->writeln("     'composers' => [");
        $output->writeln("         '{$pattern}' => {$class}::class,");
        $output->writeln('     ],');

        return 0;
    }

    /**
     * Get the relative path from the full normalized path.
     *
     * @param string $path The full path.
     * @return string The relative path after Composers/.
     */
    private function getRelativePath(string $path): string
    {
        // Remove the Views/Composers prefix if present
        $path = str_replace(['Views\\Composers\\', 'Views/Composers/'], '', $path);
        return $path;
    }

    /**
     * Generate the composer class stub.
     *
     * @param string $namespace The class namespace.
     * @param string $className The class name.
     * @return string The generated PHP code.
     */
    private function generateStub(string $namespace, string $className): string
    {
        return <<<PHP
<?php declare(strict_types=1);

namespace {$namespace};

use Lalaz\Web\View\Composers\Composer;

/**
 * {$className}
 *
 * View Composer that automatically injects data into matching views.
 * Register this composer in config/views.php under the 'composers' key.
 *
 * @package App\\Views\\Composers
 */
final class {$className} extends Composer
{
    /**
     * Compose the view data.
     *
     * This method receives the existing view data and should return
     * the modified data array with any additional variables.
     *
     * @param array<string, mixed> \$data The existing view data.
     * @return array<string, mixed> The modified view data.
     */
    public function compose(array \$data): array
    {
        // ────────────────────────────────────────────────────────────
        // Add your data here
        // ────────────────────────────────────────────────────────────

        // Example: Add current user to all views
        // \$user = auth()->user();

        // Example: Add navigation items
        // \$navigation = [
        //     ['label' => 'Home', 'url' => '/'],
        //     ['label' => 'About', 'url' => '/about'],
        // ];

        // ────────────────────────────────────────────────────────────
        // Merge your data into the view data
        // Use mergeData() to avoid overwriting existing keys
        // ────────────────────────────────────────────────────────────
        return \$this->mergeData(\$data, [
            // 'user' => \$user,
            // 'navigation' => \$navigation,
        ]);
    }
}
PHP;
    }
}
