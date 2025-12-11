<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\PackageManager;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that installs a Lalaz package via Composer.
 *
 * Runs composer require, processes the lalaz.json manifest,
 * publishes config/routes/migrations, and registers the provider.
 *
 * Usage: php lalaz package:add lalaz/auth
 *        php lalaz package:add lalaz/debug --dev
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageAddCommand implements CommandInterface
{
    /**
     * Creates a new PackageAddCommand instance.
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
        return 'package:add';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Install a Lalaz-ready package via Composer and manifest';
    }

    /**
     * {@inheritdoc}
     */
    public function arguments(): array
    {
        return [
            [
                'name' => 'package',
                'description' => 'Package name (vendor/package)',
                'optional' => false,
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
                'name' => 'dev',
                'description' => 'Install as dev dependency',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $package = $input->argument(0);
        if ($package === null) {
            $output->error('Package name is required (vendor/package).');
            return 1;
        }

        $dev = $input->hasFlag('dev') || $input->hasFlag('d');
        $result = $this->manager()->install($package, $dev);

        foreach ($result->messages as $message) {
            $output->writeln($message);
        }

        return $result->success ? 0 : 1;
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
