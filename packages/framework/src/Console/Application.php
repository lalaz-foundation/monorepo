<?php

declare(strict_types=1);

namespace Lalaz\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Container\ContainerScope;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Logging\Log;

/**
 * Console application for handling CLI commands.
 *
 * This class serves as the main entry point for the CLI application,
 * managing command registration, resolution, execution, and help rendering.
 * It integrates with the container for dependency injection and logs
 * command execution for debugging and monitoring purposes.
 *
 * Example usage:
 * ```php
 * $app = new Application();
 * $app->register(new MigrateCommand());
 * $app->register(new SeedCommand());
 * exit($app->run($argv));
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Application
{
    /**
     * The command registry.
     *
     * @var Registry
     */
    private Registry $registry;

    /**
     * The output handler for writing to console.
     *
     * @var Output
     */
    private Output $output;

    /**
     * Creates a new console application instance.
     *
     * @param Registry|null $registry The command registry to use
     * @param Output|null   $output   The output handler for console output
     */
    public function __construct(
        ?Registry $registry = null,
        ?Output $output = null,
    ) {
        $this->registry = $registry ?? new Registry();
        $this->output = $output ?? new Output();
    }

    /**
     * Creates an application instance from a container.
     *
     * This factory method creates a new application with a registry
     * that's connected to the provided container for resolving commands.
     *
     * @param ContainerInterface $container The dependency injection container
     *
     * @return self A new application instance
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        $registry = new Registry($container);
        return new self($registry);
    }

    /**
     * Registers a command with the application.
     *
     * @param CommandInterface $command The command to register
     *
     * @return void
     */
    public function register(CommandInterface $command): void
    {
        $this->registry->add($command);
    }

    /**
     * Runs the console application.
     *
     * Parses the command line arguments, resolves the appropriate command,
     * and executes it within a container scope. Handles help flags and
     * logs execution details.
     *
     * @param array<int, string> $argv Command line arguments (typically from global $argv)
     *
     * @return int Exit code (0 for success, non-zero for failure)
     *
     * @throws \Throwable Re-throws any exception from command execution after logging
     */
    public function run(array $argv): int
    {
        $startTime = microtime(true);
        $input = new Input($argv);
        $command = $input->command();

        if (in_array($command, ['-h', '--help'])) {
            return $this->renderGlobalHelp();
        }

        $handler = $this->registry->resolve($command);
        if ($handler === null) {
            $this->output->error("Command '{$command}' not found.");
            return $this->renderList(1);
        }

        if ($input->hasFlag('help') || $input->hasFlag('h')) {
            return $this->renderCommandHelp($handler);
        }

        $container = $this->registry->container();

        Log::debug('Executing command', [
            'command' => $command,
            'args' => $argv,
        ]);

        try {
            $exitCode = ContainerScope::run(
                $container,
                fn () => $handler->handle($input, $this->output),
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Command completed', [
                'command' => $command,
                'exit_code' => $exitCode,
                'duration_ms' => $duration,
            ]);

            return $exitCode;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Command failed', [
                'command' => $command,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }

    /**
     * Renders the global help message.
     *
     * Displays basic usage information for the CLI application.
     *
     * @return int Exit code (always 0)
     */
    private function renderGlobalHelp(): int
    {
        $this->output->writeln('Usage: php lalaz <command> [options]');
        $this->output->writeln(
            'Use `php lalaz list` to view available commands.',
        );
        return 0;
    }

    /**
     * Renders the list of available commands.
     *
     * Displays all registered commands with their names and descriptions.
     *
     * @param int $code The exit code to return
     *
     * @return int The provided exit code
     */
    private function renderList(int $code = 0): int
    {
        $this->output->writeln('Available commands:');
        foreach ($this->registry->all() as $command) {
            $this->output->writeln(
                sprintf(
                    '  %-18s %s',
                    $command->name(),
                    $command->description(),
                ),
            );
        }
        return $code;
    }

    /**
     * Renders help for a specific command.
     *
     * Displays the command's name, description, arguments, and options
     * in a formatted manner.
     *
     * @param CommandInterface $command The command to display help for
     *
     * @return int Exit code (always 0)
     */
    private function renderCommandHelp(CommandInterface $command): int
    {
        $this->output->writeln(sprintf('Command: %s', $command->name()));
        $this->output->writeln($command->description());
        $this->output->writeln('');

        $args = $command->arguments();
        if ($args !== []) {
            $this->output->writeln('Arguments:');
            foreach ($args as $arg) {
                $name = $arg['name'];
                if (!empty($arg['optional'])) {
                    $name = "[{$name}]";
                }
                $this->output->writeln(
                    sprintf('  %-16s %s', $name, $arg['description']),
                );
            }
            $this->output->writeln('');
        }

        $options = $command->options();
        if ($options !== []) {
            $this->output->writeln('Options:');
            foreach ($options as $option) {
                $name = '--' . $option['name'];
                if (!empty($option['shortcut'])) {
                    $name .= ', -' . $option['shortcut'];
                }
                $suffix = !empty($option['requiresValue']) ? ' <value>' : '';
                $this->output->writeln(
                    sprintf(
                        '  %-20s %s',
                        $name . $suffix,
                        $option['description'],
                    ),
                );
            }
        }

        return 0;
    }
}
