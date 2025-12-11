<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\PackageManager;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that lists installed Lalaz packages.
 *
 * Scans the vendor directory for packages with lalaz.json
 * manifests and displays their names, versions, and descriptions.
 *
 * Usage: php lalaz package:list
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageListCommand implements CommandInterface
{
    /**
     * Creates a new PackageListCommand instance.
     *
     * @param HttpApplication      $app     The application instance
     * @param PackageManager|null  $manager Optional package manager (for testing)
     */
    public function __construct(
        private HttpApplication $app,
        private ?PackageManager $manager = null,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'package:list';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'List installed Lalaz packages';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function options(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $packages = $this->manager()->packages();

        if ($packages === []) {
            $output->writeln('No Lalaz packages detected.');
            return 0;
        }

        $output->writeln(str_pad('Package', 30) . str_pad('Version', 12) . 'Description');
        $output->writeln(str_repeat('-', 80));

        foreach ($packages as $manifest) {
            $output->writeln(
                str_pad($manifest->name(), 30) .
                    str_pad($manifest->version() ?? '?', 12) .
                    ($manifest->description() ?? '-'),
            );
        }

        return 0;
    }

    /**
     * Gets or creates the package manager instance.
     *
     * @return PackageManager
     */
    private function manager(): PackageManager
    {
        if ($this->manager instanceof PackageManager) {
            return $this->manager;
        }

        return $this->manager = new PackageManager(
            $this->app->basePath() ?? getcwd(),
        );
    }
}
