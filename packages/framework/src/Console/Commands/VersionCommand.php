<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Composer\InstalledVersions;
use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that displays framework version information.
 *
 * Shows the currently installed version of the Lalaz Framework
 * using Composer's InstalledVersions API.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class VersionCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'version';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Show framework version information';
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
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $version =
            InstalledVersions::getPrettyVersion('lalaz/framework') ?? 'dev';
        $output->writeln('Lalaz Framework ' . $version);
        return 0;
    }
}
