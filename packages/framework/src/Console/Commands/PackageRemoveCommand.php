<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\PackageManager;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that removes a Lalaz package.
 *
 * Unregisters the service provider, optionally removes published
 * config files, and runs composer remove.
 *
 * Usage: php lalaz package:remove lalaz/auth
 *        php lalaz package:remove lalaz/auth --purge
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageRemoveCommand implements CommandInterface
{
    /**
     * Creates a new PackageRemoveCommand instance.
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
        return 'package:remove';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Remove a Lalaz package and unregister its provider';
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
                'name' => 'purge',
                'description' => 'Remove published config files',
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

        $purge = $input->hasFlag('purge') || $input->hasFlag('p');
        $result = $this->manager()->remove($package, !$purge);

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
