<?php

declare(strict_types=1);

namespace Lalaz\Console\Commands;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Console\Registry;

/**
 * Command that lists all available CLI commands.
 *
 * This command displays a formatted list of all registered
 * commands with their names and descriptions.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class ListCommands implements CommandInterface
{
    /**
     * Creates a new ListCommands instance.
     *
     * @param Registry $registry The command registry
     */
    public function __construct(private Registry $registry)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'list';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'List available commands';
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
        return [
            [
                'name' => 'help',
                'description' => 'Display help for this command',
                'shortcut' => 'h',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Input $input, Output $output): int
    {
        $output->writeln('Available commands:');
        foreach ($this->registry->all() as $command) {
            $output->writeln(
                sprintf(
                    '  %-18s %s',
                    $command->name(),
                    $command->description(),
                ),
            );
        }
        return 0;
    }
}
