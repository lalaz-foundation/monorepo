<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Packages\PackageDiscovery;
use Lalaz\Runtime\Http\HttpApplication;

/**
 * Command that discovers and caches installed Lalaz packages.
 *
 * This command scans the vendor directory for packages with lalaz.json
 * manifests and generates a cached manifest file. It can also publish
 * configuration files and assets from discovered packages.
 *
 * Usage:
 *   php lalaz package:discover
 *   php lalaz package:discover --publish-configs
 *   php lalaz package:discover --publish-assets
 *   php lalaz package:discover --clear
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class PackageDiscoverCommand implements CommandInterface
{
    /**
     * Creates a new PackageDiscoverCommand instance.
     *
     * @param HttpApplication $app The application instance
     */
    public function __construct(
        private HttpApplication $app,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'package:discover';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Discover and cache installed Lalaz packages';
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
        return [
            [
                'name' => 'publish-configs',
                'description' => 'Publish configuration files from discovered packages',
            ],
            [
                'name' => 'publish-assets',
                'description' => 'Publish assets from discovered packages',
            ],
            [
                'name' => 'clear',
                'description' => 'Clear the discovery cache',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $basePath = $this->app->basePath() ?? getcwd();
        $discovery = new PackageDiscovery($basePath);

        // Handle clear option
        if ($input->hasFlag('clear')) {
            if ($discovery->clearCache()) {
                $output->success('Discovery cache cleared.');
            } else {
                $output->info('No cache to clear.');
            }
            return 0;
        }

        $output->writeln('');
        $output->writeln("\033[1;34m⚡ Lalaz Package Discovery\033[0m");
        $output->writeln('');

        // Run discovery
        try {
            $stats = PackageDiscovery::discover($basePath);

            $output->writeln("\033[32m✓\033[0m Discovered \033[1m{$stats['packages']}\033[0m packages");
            $output->writeln("\033[32m✓\033[0m Found \033[1m{$stats['providers']}\033[0m service providers");
            $output->writeln("\033[32m✓\033[0m Found \033[1m{$stats['configs']}\033[0m configuration files");

            // Reload discovery after cache update
            $discovery = new PackageDiscovery($basePath);

            // Publish configs if requested
            if ($input->hasFlag('publish-configs')) {
                $published = $discovery->publishConfigs();
                if (!empty($published)) {
                    $output->writeln('');
                    $output->writeln("\033[1mPublished configurations:\033[0m");
                    foreach ($published as $file) {
                        $output->writeln("  \033[32m✓\033[0m {$file}");
                    }
                } else {
                    $output->writeln('');
                    $output->info('All configurations already published.');
                }
            }

            // Publish assets if requested
            if ($input->hasFlag('publish-assets')) {
                $published = $discovery->publishAssets();
                if (!empty($published)) {
                    $output->writeln('');
                    $output->writeln("\033[1mPublished assets:\033[0m");
                    foreach ($published as $dir) {
                        $output->writeln("  \033[32m✓\033[0m {$dir}");
                    }
                } else {
                    $output->writeln('');
                    $output->info('All assets already published.');
                }
            }

            // Show discovered packages
            $packages = $discovery->packages();
            if (!empty($packages)) {
                $output->writeln('');
                $output->writeln("\033[1mDiscovered packages:\033[0m");
                foreach ($packages as $name => $info) {
                    $output->writeln("  • {$name} (v{$info['version']})");
                }
            }

            // Show required env variables
            $envVars = $discovery->envVariables();
            $allEnv = [];
            foreach ($envVars as $package => $vars) {
                $allEnv = array_merge($allEnv, $vars);
            }
            $allEnv = array_unique($allEnv);

            if (!empty($allEnv)) {
                $output->writeln('');
                $output->writeln("\033[1;33m⚠ Required environment variables:\033[0m");
                foreach ($allEnv as $var) {
                    $output->writeln("  • {$var}");
                }
            }

            $output->writeln('');
            $output->success('Package discovery complete!');

            return 0;

        } catch (\Throwable $e) {
            $output->error('Discovery failed: ' . $e->getMessage());
            return 1;
        }
    }
}
