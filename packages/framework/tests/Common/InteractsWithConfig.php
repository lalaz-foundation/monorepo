<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

use Lalaz\Config\Config;

/**
 * Trait for tests that need to manage configuration state.
 *
 * @package lalaz/framework
 */
trait InteractsWithConfig
{
    protected ?string $tempDir = null;
    protected ?string $envPath = null;
    protected ?string $configDir = null;

    protected function setUpConfig(): void
    {
        Config::clearCache();
    }

    protected function tearDownConfig(): void
    {
        Config::clearCache();

        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
            $this->tempDir = null;
        }
    }

    protected function createTempConfigDirectory(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/lalaz_config_test_' . uniqid();

        if (!mkdir($this->tempDir, 0777, true)) {
            throw new \RuntimeException("Failed to create temp directory: {$this->tempDir}");
        }

        if (!mkdir($this->tempDir . '/config', 0777, true)) {
            throw new \RuntimeException('Failed to create temp config directory.');
        }

        $this->envPath = $this->tempDir . '/.env';
        $this->configDir = $this->tempDir . '/config';

        return $this->tempDir;
    }

    /**
     * @param array<string, string> $variables
     */
    protected function createEnvFile(array $variables): string
    {
        if ($this->envPath === null) {
            $this->createTempConfigDirectory();
        }

        $content = '';
        foreach ($variables as $key => $value) {
            $content .= "{$key}={$value}\n";
        }

        file_put_contents($this->envPath, $content);

        return $this->envPath;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createConfigFile(string $name, array $config): string
    {
        if ($this->configDir === null) {
            $this->createTempConfigDirectory();
        }

        $path = $this->configDir . '/' . $name . '.php';
        $content = '<?php return ' . var_export($config, true) . ';';
        file_put_contents($path, $content);

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $todo($fileinfo->getRealPath());
        }

        rmdir($path);
    }
}
