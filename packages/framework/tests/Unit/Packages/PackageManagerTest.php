<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Packages;

use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Packages\PackageManager;
use Lalaz\Packages\PackageManifest;
use Lalaz\Packages\PackageOperationResult;

class PackageManagerTest extends FrameworkUnitTestCase
{
    private static string $tempDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tempDir = sys_get_temp_dir() . '/lalaz-pkg-test-' . uniqid();
        self::cleanupDirectory(self::$tempDir);
        mkdir(self::$tempDir, 0755, true);
        mkdir(self::$tempDir . '/config', 0755, true);
        mkdir(self::$tempDir . '/vendor/lalaz/database/config', 0755, true);

        file_put_contents(self::$tempDir . '/config/providers.php', "<?php return ['providers' => []];");

        // Create valid lalaz.json manifest
        file_put_contents(self::$tempDir . '/vendor/lalaz/database/lalaz.json', json_encode([
            'name' => 'lalaz/database',
            'description' => 'Database package',
            'version' => '1.0.0',
            'provider' => 'Lalaz\\Database\\DatabaseServiceProvider',
            'install' => [
                'config' => 'config/database.php',
                'migrations' => 'migrations',
                'env' => ['DB_HOST', 'DB_NAME'],
            ],
            'post_install' => [
                'scripts' => ['php artisan db:setup'],
                'message' => 'Database package installed successfully!',
            ],
        ]));

        file_put_contents(
            self::$tempDir . '/vendor/lalaz/database/config/database.php',
            "<?php return ['driver' => 'mysql'];"
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanupDirectory(self::$tempDir);
        parent::tearDownAfterClass();
    }

    private static function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    // ============================================
    // Package Manifest Tests
    // ============================================

    public function testgetmanifestReturnsPackageManifest(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $this->assertInstanceOf(PackageManifest::class, $manifest);
    }

    public function testmanifestNameIsCorrect(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $this->assertSame('lalaz/database', $manifest->name());
    }

    public function testmanifestProviderIsCorrect(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $this->assertSame('Lalaz\\Database\\DatabaseServiceProvider', $manifest->provider());
    }

    public function testmanifestContainsEnvVariables(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $envVars = $manifest->envVariables();
        $this->assertContains('DB_HOST', $envVars);
        $this->assertContains('DB_NAME', $envVars);
    }

    public function testmanifestContainsConfigPublication(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $config = $manifest->configPublication();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('stub', $config);
        $this->assertSame('config/database.php', $config['stub']);
    }

    public function testmanifestContainsMigrationsPath(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $this->assertSame('migrations', $manifest->migrationsPath());
    }

    public function testmanifestContainsPostInstallScripts(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $scripts = $manifest->postInstallScripts();
        $this->assertContains('php artisan db:setup', $scripts);
    }

    public function testmanifestContainsPostInstallMessage(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/database');

        $this->assertSame('Database package installed successfully!', $manifest->postInstallMessage());
    }

    // ============================================
    // Package Listing Tests
    // ============================================

    public function testpackagesReturnsArrayOfManifests(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $packages = $manager->packages();

        $this->assertIsArray($packages);
        $this->assertNotEmpty($packages);
        $this->assertContainsOnlyInstancesOf(PackageManifest::class, $packages);
    }

    public function testpackagesIncludesLalazDatabase(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $packages = $manager->packages();

        $names = array_map(fn(PackageManifest $m) => $m->name(), $packages);
        $this->assertContains('lalaz/database', $names);
    }

    // ============================================
    // Package Installation Tests (with mock composer)
    // ============================================

    public function testinstallReturnsOperationResult(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => true,
            'output' => 'Package installed',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->install('lalaz/database');

        $this->assertInstanceOf(PackageOperationResult::class, $result);
    }

    public function testinstallSuccessReturnsTrue(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => true,
            'output' => 'Package installed',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->install('lalaz/database');

        $this->assertTrue($result->success);
    }

    public function testinstallFailureReturnsFalse(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => false,
            'output' => 'Package not found',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->install('lalaz/nonexistent');

        $this->assertFalse($result->success);
    }

    public function testinstallReturnsMessages(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => true,
            'output' => 'Package installed',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->install('lalaz/database');

        $this->assertNotEmpty($result->messages);
    }

    // ============================================
    // Package Removal Tests (with mock composer)
    // ============================================

    public function testremoveReturnsOperationResult(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => true,
            'output' => 'Package removed',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->remove('lalaz/database');

        $this->assertInstanceOf(PackageOperationResult::class, $result);
    }

    public function testremoveSuccessReturnsTrue(): void
    {
        $composerRunner = fn($action, $package, $dev, $basePath) => [
            'success' => true,
            'output' => 'Package removed',
        ];

        $manager = new PackageManager(self::$tempDir, $composerRunner);
        $result = $manager->remove('lalaz/database');

        $this->assertTrue($result->success);
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function testgetmanifestReturnsNullForNonexistentPackage(): void
    {
        $manager = new PackageManager(self::$tempDir);
        $manifest = $manager->getManifest('lalaz/nonexistent');

        $this->assertNull($manifest);
    }

    public function testpackagesReturnsEmptyArrayForEmptyVendor(): void
    {
        $emptyDir = sys_get_temp_dir() . '/lalaz-empty-' . uniqid();
        mkdir($emptyDir, 0755, true);
        mkdir($emptyDir . '/vendor', 0755, true);

        $manager = new PackageManager($emptyDir);
        $packages = $manager->packages();

        $this->assertIsArray($packages);
        $this->assertEmpty($packages);

        rmdir($emptyDir . '/vendor');
        rmdir($emptyDir);
    }

    // ============================================
    // Local Package Detection Tests
    // ============================================

    public function testinstallDetectsLocalPackageInMonorepo(): void
    {
        // Setup monorepo structure
        $rootDir = sys_get_temp_dir() . '/lalaz-monorepo-test-' . uniqid();
        $projectDir = $rootDir . '/apps/my-app';

        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        // Create local package at ../../packages/auth
        $packagesDir = dirname(dirname($projectDir)) . '/packages';
        mkdir($packagesDir . '/auth', 0755, true);
        file_put_contents($packagesDir . '/auth/composer.json', json_encode([
            'name' => 'lalaz/auth',
        ]));
        file_put_contents($packagesDir . '/auth/lalaz.json', json_encode([
            'name' => 'lalaz/auth',
        ]));

        // Create empty composer.json in project
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'require' => [],
        ]));

        // Create providers.php
        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRan = false;
        $packageRequested = '';

        $composerRunner = function ($action, $package, $dev, $basePath) use (&$composerRan, &$packageRequested) {
            $composerRan = true;
            $packageRequested = $package;
            return ['success' => true, 'output' => ''];
        };

        $manager = new PackageManager($projectDir, $composerRunner);
        $result = $manager->install('lalaz/auth');

        // Verify local package was detected
        $messagesStr = implode(' ', $result->messages);
        $this->assertStringContainsString('Local package detected', $messagesStr);

        // Verify @dev version was requested
        $this->assertStringContainsString('@dev', $packageRequested);

        // Cleanup
        self::cleanupDirectory($rootDir);
    }

    public function testinstallAddsPathRepositoryToComposerJson(): void
    {
        // Setup project directory
        $projectDir = sys_get_temp_dir() . '/lalaz-path-repo-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        // Create local package using custom path
        $localPkgDir = $projectDir . '/local-packages/myauth';
        mkdir($localPkgDir, 0755, true);
        file_put_contents($localPkgDir . '/composer.json', json_encode([
            'name' => 'lalaz/myauth',
        ]));

        // Create empty composer.json in project
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'require' => [],
        ], JSON_PRETTY_PRINT));

        // Create providers.php
        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        // Use custom localPackagesPath
        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './local-packages'
        );

        $manager->install('lalaz/myauth');

        // Verify path repository was added
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        $this->assertArrayHasKey('repositories', $composerJson);

        $hasPathRepo = false;
        foreach ($composerJson['repositories'] as $repo) {
            if ($repo['type'] === 'path' && str_contains($repo['url'], 'myauth')) {
                $hasPathRepo = true;
                break;
            }
        }
        $this->assertTrue($hasPathRepo, 'Path repository should be added to composer.json');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    public function testinstallSkipsLocalDetectionWhenDisabledByEnv(): void
    {
        // Set environment variable to disable local detection
        putenv('LALAZ_DISABLE_LOCAL_PACKAGES=true');

        try {
            // Setup project with local package
            $rootDir = sys_get_temp_dir() . '/lalaz-disabled-test-' . uniqid();
            $projectDir = $rootDir . '/apps/my-app';

            mkdir($projectDir, 0755, true);
            mkdir($projectDir . '/config', 0755, true);
            mkdir($projectDir . '/vendor', 0755, true);

            // Create local package at ../../packages/cache
            $packagesDir = dirname(dirname($projectDir)) . '/packages';
            mkdir($packagesDir . '/cache', 0755, true);
            file_put_contents($packagesDir . '/cache/composer.json', json_encode([
                'name' => 'lalaz/cache',
            ]));

            file_put_contents($projectDir . '/composer.json', json_encode([
                'name' => 'test/project',
            ]));
            file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

            $packageRequested = '';
            $composerRunner = function ($action, $package, $dev, $basePath) use (&$packageRequested) {
                $packageRequested = $package;
                return ['success' => true, 'output' => ''];
            };

            $manager = new PackageManager($projectDir, $composerRunner);
            $result = $manager->install('lalaz/cache');

            // Local detection should be skipped - no @dev suffix
            $this->assertStringNotContainsString('@dev', $packageRequested);

            // Messages should not mention local package
            $messagesStr = implode(' ', $result->messages);
            $this->assertStringNotContainsString('Local package detected', $messagesStr);

            // Cleanup
            self::cleanupDirectory($rootDir);
        } finally {
            // Restore environment
            putenv('LALAZ_DISABLE_LOCAL_PACKAGES');
        }
    }

    public function testinstallSkipsLocalDetectionInProduction(): void
    {
        // Set production environment
        putenv('APP_ENV=production');

        try {
            $rootDir = sys_get_temp_dir() . '/lalaz-prod-test-' . uniqid();
            $projectDir = $rootDir . '/apps/my-app';

            mkdir($projectDir, 0755, true);
            mkdir($projectDir . '/config', 0755, true);
            mkdir($projectDir . '/vendor', 0755, true);

            // Create local package
            $packagesDir = dirname(dirname($projectDir)) . '/packages';
            mkdir($packagesDir . '/events', 0755, true);
            file_put_contents($packagesDir . '/events/composer.json', json_encode([
                'name' => 'lalaz/events',
            ]));

            file_put_contents($projectDir . '/composer.json', json_encode([
                'name' => 'test/project',
            ]));
            file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

            $packageRequested = '';
            $composerRunner = function ($action, $package, $dev, $basePath) use (&$packageRequested) {
                $packageRequested = $package;
                return ['success' => true, 'output' => ''];
            };

            $manager = new PackageManager($projectDir, $composerRunner);
            $result = $manager->install('lalaz/events');

            // In production, should NOT detect local packages
            $this->assertStringNotContainsString('@dev', $packageRequested);
            $this->assertEquals('lalaz/events', $packageRequested);

            // Cleanup
            self::cleanupDirectory($rootDir);
        } finally {
            putenv('APP_ENV');
        }
    }

    public function testinstallDoesNotDuplicatePathRepository(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-dup-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        // Create local package
        $localPkgDir = $projectDir . '/local-packages/orm';
        mkdir($localPkgDir, 0755, true);
        file_put_contents($localPkgDir . '/composer.json', json_encode([
            'name' => 'lalaz/orm',
        ]));

        // Create composer.json with existing path repository
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => './local-packages/orm',
                    'options' => ['symlink' => true],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './local-packages'
        );

        // Install twice
        $manager->install('lalaz/orm');
        $manager->install('lalaz/orm');

        // Verify only one path repository exists
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        $pathRepoCount = 0;
        foreach ($composerJson['repositories'] as $repo) {
            if ($repo['type'] === 'path' && str_contains($repo['url'], 'orm')) {
                $pathRepoCount++;
            }
        }
        $this->assertEquals(1, $pathRepoCount, 'Should not duplicate path repository');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    public function testinstallFallsBackToPackagistWhenLocalNotFound(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-fallback-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
        ]));
        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $packageRequested = '';
        $composerRunner = function ($action, $package, $dev, $basePath) use (&$packageRequested) {
            $packageRequested = $package;
            return ['success' => true, 'output' => ''];
        };

        $manager = new PackageManager($projectDir, $composerRunner);
        $result = $manager->install('lalaz/nonexistent-pkg');

        // Should use package name as-is (no @dev)
        $this->assertEquals('lalaz/nonexistent-pkg', $packageRequested);

        // Should not mention local package
        $messagesStr = implode(' ', $result->messages);
        $this->assertStringNotContainsString('Local package detected', $messagesStr);

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    // ============================================
    // Transitive Dependency Resolution Tests
    // ============================================

    public function testinstallResolvesTransitiveLalazDependencies(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-transitive-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        // Create local packages
        $localPkgDir = $projectDir . '/packages';

        // Create lalaz/orm that depends on lalaz/database
        mkdir($localPkgDir . '/orm', 0755, true);
        file_put_contents($localPkgDir . '/orm/composer.json', json_encode([
            'name' => 'lalaz/orm',
            'require' => [
                'php' => '^8.2',
                'lalaz/database' => '@dev',
            ],
        ]));

        // Create lalaz/database
        mkdir($localPkgDir . '/database', 0755, true);
        file_put_contents($localPkgDir . '/database/composer.json', json_encode([
            'name' => 'lalaz/database',
        ]));

        // Create project composer.json
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'repositories' => [],
        ], JSON_PRETTY_PRINT));

        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './packages'
        );

        $result = $manager->install('lalaz/orm');

        // Verify both packages were detected
        $messagesStr = implode(' ', $result->messages);
        $this->assertStringContainsString('Local package detected', $messagesStr);

        // Verify composer.json has both path repositories
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);
        $this->assertArrayHasKey('repositories', $composerJson);

        $hasOrmRepo = false;
        $hasDatabaseRepo = false;
        foreach ($composerJson['repositories'] as $repo) {
            if ($repo['type'] === 'path' && str_contains($repo['url'], '/orm')) {
                $hasOrmRepo = true;
            }
            if ($repo['type'] === 'path' && str_contains($repo['url'], '/database')) {
                $hasDatabaseRepo = true;
            }
        }

        $this->assertTrue($hasOrmRepo, 'Path repository for orm should be added');
        $this->assertTrue($hasDatabaseRepo, 'Path repository for database (transitive dependency) should be added');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    public function testinstallHandlesNestedTransitiveDependencies(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-nested-deps-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        $localPkgDir = $projectDir . '/packages';

        // Create lalaz/app that depends on lalaz/orm
        mkdir($localPkgDir . '/app', 0755, true);
        file_put_contents($localPkgDir . '/app/composer.json', json_encode([
            'name' => 'lalaz/app',
            'require' => [
                'lalaz/orm' => '@dev',
            ],
        ]));

        // Create lalaz/orm that depends on lalaz/database
        mkdir($localPkgDir . '/orm', 0755, true);
        file_put_contents($localPkgDir . '/orm/composer.json', json_encode([
            'name' => 'lalaz/orm',
            'require' => [
                'lalaz/database' => '@dev',
            ],
        ]));

        // Create lalaz/database
        mkdir($localPkgDir . '/database', 0755, true);
        file_put_contents($localPkgDir . '/database/composer.json', json_encode([
            'name' => 'lalaz/database',
        ]));

        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
        ], JSON_PRETTY_PRINT));

        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './packages'
        );

        $result = $manager->install('lalaz/app');

        // Verify composer.json has all path repositories
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);

        $foundRepos = [];
        foreach ($composerJson['repositories'] ?? [] as $repo) {
            if ($repo['type'] === 'path') {
                $foundRepos[] = $repo['url'];
            }
        }

        $this->assertCount(3, $foundRepos, 'Should have 3 path repositories (app, orm, database)');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    public function testinstallDoesNotResolveNonLalazDependencies(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-no-ext-deps-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        $localPkgDir = $projectDir . '/packages';

        // Create lalaz/cache that depends on external packages
        mkdir($localPkgDir . '/cache', 0755, true);
        file_put_contents($localPkgDir . '/cache/composer.json', json_encode([
            'name' => 'lalaz/cache',
            'require' => [
                'php' => '^8.2',
                'psr/cache' => '^3.0',
                'symfony/cache' => '^6.0',
            ],
        ]));

        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
        ], JSON_PRETTY_PRINT));

        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './packages'
        );

        $result = $manager->install('lalaz/cache');

        // Verify composer.json only has cache path repository
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);

        $pathRepoCount = 0;
        foreach ($composerJson['repositories'] ?? [] as $repo) {
            if ($repo['type'] === 'path') {
                $pathRepoCount++;
                // Should only contain cache
                $this->assertStringContainsString('cache', $repo['url']);
            }
        }

        $this->assertEquals(1, $pathRepoCount, 'Should only have 1 path repository (cache), not external deps');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }

    public function testinstallSkipsAlreadyAddedTransitiveDependencies(): void
    {
        $projectDir = sys_get_temp_dir() . '/lalaz-skip-existing-test-' . uniqid();
        mkdir($projectDir, 0755, true);
        mkdir($projectDir . '/config', 0755, true);
        mkdir($projectDir . '/vendor', 0755, true);

        $localPkgDir = $projectDir . '/packages';

        // Create lalaz/orm
        mkdir($localPkgDir . '/orm', 0755, true);
        file_put_contents($localPkgDir . '/orm/composer.json', json_encode([
            'name' => 'lalaz/orm',
            'require' => [
                'lalaz/database' => '@dev',
            ],
        ]));

        // Create lalaz/database
        mkdir($localPkgDir . '/database', 0755, true);
        file_put_contents($localPkgDir . '/database/composer.json', json_encode([
            'name' => 'lalaz/database',
        ]));

        // Create composer.json with database already added
        file_put_contents($projectDir . '/composer.json', json_encode([
            'name' => 'test/project',
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => './packages/database',
                    'options' => ['symlink' => true],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        file_put_contents($projectDir . '/config/providers.php', "<?php return ['providers' => []];");

        $composerRunner = fn($action, $package, $dev, $basePath) => ['success' => true, 'output' => ''];

        $manager = new PackageManager(
            $projectDir,
            $composerRunner,
            null,
            null,
            './packages'
        );

        $result = $manager->install('lalaz/orm');

        // Verify database repo was not duplicated
        $composerJson = json_decode(file_get_contents($projectDir . '/composer.json'), true);

        $databaseRepoCount = 0;
        foreach ($composerJson['repositories'] as $repo) {
            if ($repo['type'] === 'path' && str_contains($repo['url'], 'database')) {
                $databaseRepoCount++;
            }
        }

        $this->assertEquals(1, $databaseRepoCount, 'Should not duplicate database path repository');

        // Cleanup
        self::cleanupDirectory($projectDir);
    }
}
