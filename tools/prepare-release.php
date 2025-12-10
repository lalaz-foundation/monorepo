#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lalaz Release Script
 * 
 * Prepares starters for release by removing local dependencies
 * and updating version constraints.
 * 
 * Usage:
 *   php tools/prepare-release.php <version>
 *   php tools/prepare-release.php 1.0.0-rc.1
 */

if ($argc < 2) {
    echo "Usage: php tools/prepare-release.php <version>\n";
    echo "Example: php tools/prepare-release.php 1.0.0-rc.1\n";
    exit(1);
}

$version = $argv[1];

// Validate version format
if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', $version)) {
    echo "❌ Invalid version format. Use semantic versioning (e.g., 1.0.0 or 1.0.0-rc.1)\n";
    exit(1);
}

$rootDir = dirname(__DIR__);
$startersDir = $rootDir . '/starters';
$packagesDir = $rootDir . '/packages';
$distDir = $rootDir . '/dist';

echo "🚀 Preparing Lalaz v{$version} for release\n\n";

// Create dist directory
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

// Update package versions
echo "📦 Updating package versions...\n";
$packages = glob($packagesDir . '/*/composer.json');

foreach ($packages as $composerFile) {
    $packageName = basename(dirname($composerFile));
    $composer = json_decode(file_get_contents($composerFile), true);
    
    // Update version
    $composer['version'] = $version;
    
    // Update lalaz/* dependencies to use version constraint
    if (isset($composer['require'])) {
        foreach ($composer['require'] as $dep => $constraint) {
            if (str_starts_with($dep, 'lalaz/')) {
                $composer['require'][$dep] = "^{$version}";
            }
        }
    }
    
    file_put_contents(
        $composerFile, 
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    
    echo "  ✓ {$packageName}\n";
}

// Prepare starters
echo "\n📁 Preparing starters...\n";
$starters = glob($startersDir . '/*', GLOB_ONLYDIR);

foreach ($starters as $starterDir) {
    $starterName = basename($starterDir);
    $composerFile = $starterDir . '/composer.json';
    
    if (!file_exists($composerFile)) {
        echo "  ⚠ {$starterName} - no composer.json\n";
        continue;
    }
    
    $composer = json_decode(file_get_contents($composerFile), true);
    
    // Remove local repositories
    unset($composer['repositories']);
    
    // Update lalaz/framework version
    if (isset($composer['require']['lalaz/framework'])) {
        // For RC versions, use exact version; for stable, use caret
        if (str_contains($version, '-')) {
            $composer['require']['lalaz/framework'] = $version;
        } else {
            $composer['require']['lalaz/framework'] = "^{$version}";
        }
    }
    
    // Create dist version
    $distStarterDir = $distDir . '/' . $starterName;
    
    // Remove old dist
    if (is_dir($distStarterDir)) {
        exec("rm -rf " . escapeshellarg($distStarterDir));
    }
    
    // Copy starter to dist
    exec("cp -r " . escapeshellarg($starterDir) . " " . escapeshellarg($distStarterDir));
    
    // Write updated composer.json to dist
    file_put_contents(
        $distStarterDir . '/composer.json',
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    
    // Clean up dist
    $cleanupPaths = [
        $distStarterDir . '/vendor',
        $distStarterDir . '/.phpunit.cache',
        $distStarterDir . '/storage/cache/*',
        $distStarterDir . '/storage/logs/*',
    ];
    
    foreach ($cleanupPaths as $path) {
        exec("rm -rf " . escapeshellarg($path) . " 2>/dev/null");
    }
    
    echo "  ✓ {$starterName} → dist/{$starterName}\n";
}

// Summary
echo "\n✅ Release preparation complete!\n\n";

echo "📋 Next steps:\n";
echo "  1. Review changes in dist/ directory\n";
echo "  2. Commit version updates: git add -A && git commit -m 'chore: bump version to {$version}'\n";
echo "  3. Create tag: git tag v{$version}\n";
echo "  4. Push: git push origin main --tags\n";
echo "\n";

echo "📁 Distribution files:\n";
foreach (glob($distDir . '/*', GLOB_ONLYDIR) as $dir) {
    echo "  - dist/" . basename($dir) . "/\n";
}

echo "\n🎉 Ready to release Lalaz v{$version}!\n";
