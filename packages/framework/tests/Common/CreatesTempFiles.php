<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Common;

/**
 * Trait for tests that create temporary files.
 *
 * @package lalaz/framework
 */
trait CreatesTempFiles
{
    /** @var array<int, string> */
    protected array $tempFiles = [];

    /** @var array<int, string> */
    protected array $tempDirs = [];

    protected function tearDownTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        foreach (array_reverse($this->tempDirs) as $dir) {
            $this->removeTempDirectory($dir);
        }

        $this->tempFiles = [];
        $this->tempDirs = [];
    }

    protected function createTempFile(string $content, ?string $extension = null): string
    {
        $suffix = $extension !== null ? '.' . ltrim($extension, '.') : '';
        $path = tempnam(sys_get_temp_dir(), 'lalaz_test_') . $suffix;

        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    protected function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/lalaz_test_' . uniqid('', true);
        mkdir($path, 0777, true);
        $this->tempDirs[] = $path;

        return $path;
    }

    protected function createTempPhpFile(string $phpContent): string
    {
        return $this->createTempFile("<?php\n" . $phpContent, 'php');
    }

    private function removeTempDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($path);
    }
}
