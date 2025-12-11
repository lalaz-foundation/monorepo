<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\ManifestValidationException;
use Lalaz\Packages\PackageManager;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that displays package manifest information.
 *
 * Shows detailed information from a package's lalaz.json
 * manifest including name, version, description, provider,
 * and environment variables.
 *
 * Usage: php lalaz package:info lalaz/auth
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageInfoCommand implements CommandInterface
{
    /**
     * Creates a new PackageInfoCommand instance.
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
        return 'package:info';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Show manifest information for a package';
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
        return [];
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

        try {
            $manifest = $this->manager()->getManifest($package);
        } catch (ManifestValidationException $e) {
            $output->error('Manifest invalid: ' . $e->getMessage());
            return 1;
        }

        if ($manifest === null) {
            $output->error("Package manifest not found for {$package}.");
            return 1;
        }

        $output->writeln('Name:        ' . $manifest->name());
        $output->writeln('Version:     ' . ($manifest->version() ?? 'unknown'));
        $output->writeln('Description: ' . ($manifest->description() ?? 'n/a'));

        if ($manifest->provider()) {
            $output->writeln('Provider:    ' . $manifest->provider());
        }

        $env = $manifest->envVariables();
        if ($env !== []) {
            $output->writeln('');
            $output->writeln('Environment variables:');
            foreach ($env as $var) {
                $output->writeln("  - {$var}");
            }
        }

        $message = $manifest->postInstallMessage();
        if ($message) {
            $output->writeln('');
            $output->writeln('Post-install message:');
            $output->writeln($message);
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
