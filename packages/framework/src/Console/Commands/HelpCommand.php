<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;

/**
 * Command that displays usage information for the CLI.
 *
 * This command provides general help and usage instructions
 * for the Lalaz CLI framework.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class HelpCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'help';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Display usage information';
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
        $output->writeln('Usage: php lalaz <command> [options]');
        $output->writeln('Run `php lalaz list` to view registered commands.');
        return 0;
    }
}
