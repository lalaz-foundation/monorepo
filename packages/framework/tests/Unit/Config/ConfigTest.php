<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Config;

use Lalaz\Config\Config;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Config::class)]
final class ConfigTest extends FrameworkUnitTestCase
{
    private static string $tempDir;
    private static array $originalConfigs = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tempDir = sys_get_temp_dir() . '/lalaz-config-test-' . uniqid();
        mkdir(self::$tempDir, 0777, true);

        // Create sample config files
        file_put_contents(
            self::$tempDir . '/app.php',
            '<?php return ["name" => "TestApp", "debug" => true, "nested" => ["key1" => "value1", "key2" => "value2"]];'
        );
        file_put_contents(
            self::$tempDir . '/database.php',
            '<?php return ["driver" => "mysql", "host" => "localhost"];'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Clean up temp files
        if (is_dir(self::$tempDir)) {
            array_map('unlink', glob(self::$tempDir . '/*.php'));
            rmdir(self::$tempDir);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Config singleton before each test
        Config::setInstance(null);

        // Store original state for reset
        self::$originalConfigs = [];
    }

    public function testLoadConfigurationFromDirectory(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        $this->assertSame('TestApp', Config::get('app.name'));
        $this->assertTrue(Config::get('app.debug'));
        $this->assertSame('mysql', Config::get('database.driver'));
    }

    public function testGetNestedConfigurationValues(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        $this->assertSame('value1', Config::get('app.nested.key1'));
        $this->assertSame('value2', Config::get('app.nested.key2'));
    }

    public function testReturnDefaultValueForMissingKeys(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        $this->assertNull(Config::get('nonexistent.key'));
        $this->assertSame('default', Config::get('nonexistent.key', 'default'));
    }

    public function testSetConfigurationValueAtRuntime(): void
    {
        Config::loadConfigFiles(self::$tempDir);
        Config::set('app.runtime_key', 'runtime_value');

        $this->assertSame('runtime_value', Config::get('app.runtime_key'));
    }

    public function testCheckIfKeyExistsViaGet(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        // Existing key returns value (not null)
        $this->assertNotNull(Config::get('app.name'));

        // Missing key returns null by default
        $this->assertNull(Config::get('nonexistent.key'));

        // Can use a different default to distinguish missing from null values
        $this->assertSame('__missing__', Config::get('nonexistent.key', '__missing__'));
    }

    public function testAllReturnsEntireConfigurationArray(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        $allConfig = Config::all();

        $this->assertIsArray($allConfig);
        $this->assertArrayHasKey('app', $allConfig);
        $this->assertArrayHasKey('database', $allConfig);
    }

    public function testGetEntireFileConfiguration(): void
    {
        Config::loadConfigFiles(self::$tempDir);

        $appConfig = Config::get('app');

        $this->assertIsArray($appConfig);
        $this->assertArrayHasKey('name', $appConfig);
        $this->assertArrayHasKey('debug', $appConfig);
    }
}
