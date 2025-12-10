<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Scheduler\Schedule;

/**
 * ScheduleRunCommand - Execute all due scheduled tasks.
 *
 * This command should be run via cron every minute:
 *   * * * * * cd /path-to-project && php lalaz schedule:run >> /dev/null 2>&1
 *
 * @package lalaz/scheduler
 */
final class ScheduleRunCommand implements CommandInterface
{
    public function __construct(
        private ContainerInterface $container
    ) {
    }

    public function name(): string
    {
        return 'schedule:run';
    }

    public function description(): string
    {
        return 'Run the scheduled tasks that are due.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'debug',
                'description' => 'Show detailed output for each task',
                'short' => 'd',
                'required' => false,
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $debug = $input->option('debug') !== null;

        // Resolve the schedule from the container
        if (!$this->container->has(Schedule::class)) {
            $output->writeln('<error>No Schedule registered in the container.</error>');
            return 1;
        }

        /** @var Schedule $schedule */
        $schedule = $this->container->resolve(Schedule::class);

        // Get due events
        $events = $schedule->dueEvents();

        if (empty($events)) {
            if ($debug) {
                $output->writeln('<info>No scheduled tasks are due.</info>');
            }
            return 0;
        }

        $output->writeln(sprintf('<info>Running %d scheduled task(s)...</info>', count($events)));

        $failed = 0;

        foreach ($events as $event) {
            $name = $event->getName();

            if ($debug) {
                $output->writeln(sprintf('  → Running: %s', $name));
            }

            try {
                $event->run();

                if ($debug) {
                    $output->writeln(sprintf('    <info>✓</info> Completed: %s', $name));
                }
            } catch (\Throwable $e) {
                $failed++;
                $output->writeln(sprintf('    <error>✗</error> Failed: %s - %s', $name, $e->getMessage()));

                if ($debug) {
                    $output->writeln(sprintf('      %s', $e->getTraceAsString()));
                }
            }
        }

        if ($failed > 0) {
            $output->writeln(sprintf('<error>%d task(s) failed.</error>', $failed));
            return 1;
        }

        $output->writeln('<info>All tasks completed successfully.</info>');
        return 0;
    }
}
