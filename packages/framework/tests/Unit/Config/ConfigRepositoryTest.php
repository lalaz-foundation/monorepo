<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Config;

use Lalaz\Config\ConfigRepository;
use Lalaz\Config\Contracts\ConfigRepositoryInterface;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

#[CoversClass(ConfigRepository::class)]
/**
 * Tests for the ConfigRepository class.
 */
final class ConfigRepositoryTest extends FrameworkUnitTestCase
{
    private static string $tempDir;
    private string $envPath;
    private string $appConfigPath;
    private string $cachePath;
    private ConfigRepository $config;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tempDir = sys_get_temp_dir() . '/lalaz_config_repo_test_' . time();
        if (!mkdir(self::$tempDir, 0777, true)) {
            throw new \Exception("Failed to create temp directory: " . self::$tempDir);
        }
        if (!mkdir(self::$tempDir . '/config', 0777, true)) {
            throw new \Exception("Failed to create temp config directory.");
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (is_dir(self::$tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::$tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir(self::$tempDir);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = self::$tempDir . '/.env';
        $this->appConfigPath = self::$tempDir . '/config/app.php';
        $this->cachePath = self::$tempDir . '/cache/config.php';
        $this->config = new ConfigRepository();
    }

    public function testimplementsConfigRepositoryInterface(): void
    {
        $this->assertInstanceOf(ConfigRepositoryInterface::class, $this->config);
    }

    public function testloadsVariablesFromEnvFile(): void
    {
        file_put_contents($this->envPath, "APP_NAME=LalazRepo\nDB_HOST=localhost");

        $this->config->load($this->envPath);

        $this->assertSame('LalazRepo', $this->config->get('APP_NAME'));
        $this->assertSame('localhost', $this->config->get('DB_HOST'));
    }

    public function testloadsVariablesFromPhpConfigFiles(): void
    {
        $configContent = "<?php return ['name' => 'RepoApp', 'env' => 'testing'];";
        file_put_contents($this->appConfigPath, $configContent);

        $this->config->loadConfigFiles(self::$tempDir . '/config');

        $this->assertSame('RepoApp', $this->config->get('app.name'));
        $this->assertSame('testing', $this->config->get('app.env'));
    }

    public function testgetsTypedValuesCorrectly(): void
    {
        $envContent = "IS_ENABLED=true\nCOUNT=42\nRATIO=3.14";
        file_put_contents($this->envPath, $envContent);
        $this->config->load($this->envPath);

        $this->assertTrue($this->config->getBool('IS_ENABLED'));
        $this->assertSame(42, $this->config->getInt('COUNT'));
        $this->assertSame(3.14, $this->config->getFloat('RATIO'));
    }

    public function testsupportsArrayAccessForReading(): void
    {
        file_put_contents($this->envPath, "APP_KEY=secret123");
        $this->config->load($this->envPath);

        $this->assertTrue(isset($this->config['APP_KEY']));
        $this->assertSame('secret123', $this->config['APP_KEY']);
        $this->assertFalse(isset($this->config['MISSING_KEY']));
    }

    public function testsupportsArrayAccessForWriting(): void
    {
        $this->config['NEW_KEY'] = 'new_value';

        $this->assertSame('new_value', $this->config->get('NEW_KEY'));
        $this->assertSame('new_value', $this->config['NEW_KEY']);
    }

    public function testsupportsArrayAccessForUnsetting(): void
    {
        $key = 'TEMP_KEY_' . uniqid();
        $this->config->set($key, 'temp_value');
        $this->assertSame('temp_value', $this->config->get($key));

        unset($this->config[$key]);
        $this->assertFalse(isset($this->config[$key]));
    }

    public function testallowsMultipleIndependentInstances(): void
    {
        $config1 = new ConfigRepository();
        $config2 = new ConfigRepository();

        $config1->set('INSTANCE', 'one');
        $config2->set('INSTANCE', 'two');

        $this->assertSame('one', $config1->get('INSTANCE'));
        $this->assertSame('two', $config2->get('INSTANCE'));
    }

    public function testclearsCacheIndependentlyPerInstance(): void
    {
        $config = new ConfigRepository();
        $config->setConfig('test', ['key' => 'value']);
        $this->assertSame('value', $config->get('test.key'));

        $config->clearCache();
        $this->assertSame('default', $config->get('test.key', 'default'));
    }

    public function testvalidatesConfigurationValues(): void
    {
        $this->config->set('PORT', '8080');

        $valid = $this->config->validate('PORT', fn($v) => is_numeric($v) && $v > 0);
        $this->assertTrue($valid);

        $invalid = $this->config->validate('PORT', fn($v) => $v > 10000);
        $this->assertFalse($invalid);
    }

    public function testreturnsAllConfigAndEnvValues(): void
    {
        $this->config->set('ENV_VAR', 'env_value');
        $this->config->setConfig('app', ['name' => 'TestApp']);

        $all = $this->config->all();
        $this->assertArrayHasKey('ENV_VAR', $all);

        $allConfig = $this->config->allConfig();
        $this->assertArrayHasKey('app', $allConfig);
        $this->assertSame('TestApp', $allConfig['app']['name']);
    }

    public function testchecksEnvironmentCorrectly(): void
    {
        $this->config->setConfig('app', ['env' => 'production']);

        $this->assertTrue($this->config->isEnv('production'));
        $this->assertFalse($this->config->isEnv('development'));
        $this->assertTrue($this->config->isProduction());
        $this->assertFalse($this->config->isDevelopment());
    }

    public function testdetectsDebugModeFromAppDebug(): void
    {
        $this->config->set('APP_DEBUG', 'true');
        $this->assertTrue($this->config->isDebug());

        $this->config->set('APP_DEBUG', 'false');
        $this->assertFalse($this->config->isDebug());
    }

    public function testcanBeInjectedAsADependency(): void
    {
        $service = new class ($this->config) {
            public function __construct(
                private ConfigRepositoryInterface $config
            ) {}

            public function getValue(string $key): mixed
            {
                return $this->config->get($key, 'injected_default');
            }
        };

        $this->config->set('INJECTED_KEY', 'injected_value');
        $this->assertSame('injected_value', $service->getValue('INJECTED_KEY'));
        $this->assertSame('injected_default', $service->getValue('MISSING'));
    }
}
