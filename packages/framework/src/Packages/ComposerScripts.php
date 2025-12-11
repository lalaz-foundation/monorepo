<?php

declare(strict_types=1);

namespace Lalaz\Packages;

use Composer\Script\Event;

/**
 * Composer script hooks for Lalaz package discovery.
 *
 * This class provides static methods that are called by Composer
 * during install/update to automatically discover and configure
 * Lalaz packages.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ComposerScripts
{
    /**
     * Handle the post-autoload-dump Composer event.
     *
     * This method is called after Composer generates the autoloader,
     * ensuring all packages are available for discovery.
     *
     * @param Event $event The Composer event
     * @return void
     */
    public static function postAutoloadDump(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);

        // Check if this is a Lalaz project (has lalaz CLI)
        if (!self::isLalazProject($basePath)) {
            return;
        }

        $io = $event->getIO();

        // Run discovery
        require_once $vendorDir . '/autoload.php';

        $io->write('<info>⚡ Lalaz Package Discovery</info>');

        try {
            $stats = PackageDiscovery::discover($basePath);

            $io->write(sprintf(
                '  <comment>→</comment> Discovered <info>%d</info> packages with <info>%d</info> providers',
                $stats['packages'],
                $stats['providers']
            ));

            // Auto-publish missing configs (non-destructive)
            $discovery = new PackageDiscovery($basePath);
            $published = $discovery->publishConfigs();

            if (!empty($published)) {
                $io->write('  <comment>→</comment> Published configurations:');
                foreach ($published as $file) {
                    $io->write("    <info>✓</info> {$file}");
                }
            }

            // Check for missing env variables
            $envVars = $discovery->envVariables();
            $missingEnv = self::checkMissingEnvVariables($basePath, $envVars);

            if (!empty($missingEnv)) {
                $io->write('');
                $io->write('  <warning>⚠ Missing environment variables:</warning>');
                foreach ($missingEnv as $var) {
                    $io->write("    • {$var}");
                }
                $io->write('  <comment>Add these to your .env file</comment>');
            }

        } catch (\Throwable $e) {
            $io->writeError('<error>Package discovery failed: ' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Check if this is a Lalaz project.
     *
     * @param string $basePath
     * @return bool
     */
    private static function isLalazProject(string $basePath): bool
    {
        // Check for lalaz CLI file
        if (file_exists($basePath . '/lalaz')) {
            return true;
        }

        // Check for lalaz in composer.json require
        $composerFile = $basePath . '/composer.json';
        if (file_exists($composerFile)) {
            $content = file_get_contents($composerFile);
            if ($content && str_contains($content, '"lalaz/framework"')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for missing environment variables.
     *
     * @param string $basePath
     * @param array<string, array<int, string>> $envVars
     * @return array<int, string>
     */
    private static function checkMissingEnvVariables(
        string $basePath,
        array $envVars
    ): array {
        $envFile = $basePath . '/.env';
        $existingVars = [];

        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if ($content) {
                preg_match_all('/^([A-Z_]+)=/m', $content, $matches);
                $existingVars = $matches[1] ?? [];
            }
        }

        $allRequired = [];
        foreach ($envVars as $package => $vars) {
            $allRequired = array_merge($allRequired, $vars);
        }
        $allRequired = array_unique($allRequired);

        return array_diff($allRequired, $existingVars);
    }
}
