#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Lalaz Package Discovery Script
 *
 * This script is executed by Composer on post-install-cmd and post-update-cmd
 * to discover and configure Lalaz packages automatically.
 *
 * Usage:
 *   php vendor/lalaz/framework/bin/discover.php [options]
 *
 * Options:
 *   --publish-configs  Publish configuration files from discovered packages
 *   --publish-assets   Publish assets from discovered packages
 *   --quiet            Suppress output
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

// Find autoloader
$autoloaderPaths = [
    __DIR__ . '/../../../autoload.php',      // When in vendor/lalaz/framework/bin
    __DIR__ . '/../../vendor/autoload.php',  // When in packages/framework/bin (dev)
    __DIR__ . '/../vendor/autoload.php',     // Alternative location
];

$autoloader = null;
foreach ($autoloaderPaths as $path) {
    if (file_exists($path)) {
        $autoloader = $path;
        break;
    }
}

if ($autoloader === null) {
    fwrite(STDERR, "Could not find Composer autoloader.\n");
    exit(1);
}

require $autoloader;

use Lalaz\Packages\PackageDiscovery;

// Determine base path
$basePath = null;

// Check if we're in vendor/lalaz/framework/bin
if (str_contains(__DIR__, 'vendor/lalaz/framework/bin')) {
    $basePath = dirname(__DIR__, 4);
}
// Check if we're in packages/framework/bin (monorepo dev)
elseif (str_contains(__DIR__, 'packages/framework/bin')) {
    // Look for composer.json with "type": "project"
    $searchPath = dirname(__DIR__, 3);
    $dirs = ['sandbox/fullstack', 'sandbox/minimal', 'starters/fullstack', 'starters/minimal'];

    foreach ($dirs as $dir) {
        $composerFile = $searchPath . '/' . $dir . '/composer.json';
        if (file_exists($composerFile)) {
            $content = file_get_contents($composerFile);
            if ($content && str_contains($content, '"type": "project"')) {
                $basePath = $searchPath . '/' . $dir;
                break;
            }
        }
    }
}

// Fallback: use current working directory
if ($basePath === null) {
    $basePath = getcwd();
}

// Parse options
$options = getopt('', ['publish-configs', 'publish-assets', 'quiet']);
$quiet = isset($options['quiet']);
$publishConfigs = isset($options['publish-configs']);
$publishAssets = isset($options['publish-assets']);

// Helper function for output
$output = function (string $message) use ($quiet): void {
    if (!$quiet) {
        echo $message . PHP_EOL;
    }
};

$output("\033[1;34m⚡ Lalaz Package Discovery\033[0m");
$output("");

// Run discovery
try {
    $stats = PackageDiscovery::discover($basePath);

    $output("\033[32m✓\033[0m Discovered \033[1m{$stats['packages']}\033[0m packages");
    $output("\033[32m✓\033[0m Found \033[1m{$stats['providers']}\033[0m service providers");
    $output("\033[32m✓\033[0m Found \033[1m{$stats['configs']}\033[0m configuration files");

    $discovery = new PackageDiscovery($basePath);

    // Publish configs if requested or if they don't exist
    if ($publishConfigs) {
        $published = $discovery->publishConfigs();
        if (!empty($published)) {
            $output("");
            $output("\033[1mPublished configurations:\033[0m");
            foreach ($published as $file) {
                $output("  \033[32m✓\033[0m {$file}");
            }
        }
    }

    // Publish assets if requested
    if ($publishAssets) {
        $published = $discovery->publishAssets();
        if (!empty($published)) {
            $output("");
            $output("\033[1mPublished assets:\033[0m");
            foreach ($published as $dir) {
                $output("  \033[32m✓\033[0m {$dir}");
            }
        }
    }

    // Show discovered packages
    $packages = $discovery->packages();
    if (!empty($packages) && !$quiet) {
        $output("");
        $output("\033[1mDiscovered packages:\033[0m");
        foreach ($packages as $name => $info) {
            $output("  • {$name} (v{$info['version']})");
        }
    }

    // Show required env variables
    $envVars = $discovery->envVariables();
    $allEnv = [];
    foreach ($envVars as $package => $vars) {
        $allEnv = array_merge($allEnv, $vars);
    }
    $allEnv = array_unique($allEnv);

    if (!empty($allEnv) && !$quiet) {
        $output("");
        $output("\033[1;33m⚠ Required environment variables:\033[0m");
        foreach ($allEnv as $var) {
            $output("  • {$var}");
        }
    }

    $output("");
    $output("\033[32m✓ Package discovery complete!\033[0m");

} catch (\Throwable $e) {
    fwrite(STDERR, "\033[31mError during package discovery: " . $e->getMessage() . "\033[0m\n");
    exit(1);
}
