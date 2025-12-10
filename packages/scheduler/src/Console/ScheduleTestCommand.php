<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Console;

use Lalaz\Console\Contracts\CommandInterface;
use Lalaz\Console\Input;
use Lalaz\Console\Output;
use Lalaz\Container\Contracts\ContainerInterface;
use Lalaz\Scheduler\CronExpression;
use Lalaz\Scheduler\Schedule;

/**
 * ScheduleTestCommand - Test a specific scheduled task.
 *
 * @package lalaz/scheduler
 */
final class ScheduleTestCommand implements CommandInterface
{
    public function __construct(
        private ContainerInterface $container
    ) {
    }

    public function name(): string
    {
        return 'schedule:test';
    }

    public function description(): string
    {
        return 'Test run a specific scheduled task regardless of schedule.';
    }

    public function arguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'The name of the task to test (use schedule:list to see names)',
                'optional' => false,
            ],
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function handle(Input $input, Output $output): int
    {
        // The input API exposes positional arguments (index-based). The 'name'
        // argument is the first positional argument, so request index 0 here.
        $taskName = $input->argument(0);

        if (!$taskName) {
            $output->writeln('<error>Please provide a task name.</error>');
            $output->writeln('Run <comment>php lalaz schedule:list</comment> to see available tasks.');
            return 1;
        }

        // Resolve the schedule from the container
        if (!$this->container->has(Schedule::class)) {
            $output->writeln('<error>No Schedule registered in the container.</error>');
            return 1;
        }

        /** @var Schedule $schedule */
        $schedule = $this->container->resolve(Schedule::class);

        // Find the task
        $targetEvent = null;
        foreach ($schedule->events() as $event) {
            if ($event->getName() === $taskName) {
                $targetEvent = $event;
                break;
            }
        }

        if (!$targetEvent) {
            $output->writeln(sprintf('<error>Task "%s" not found.</error>', $taskName));
            $output->writeln('');
            $output->writeln('Available tasks:');

            foreach ($schedule->events() as $event) {
                $output->writeln(sprintf('  - %s', $event->getName()));
            }

            return 1;
        }

        // Show task info
        $output->writeln('');
        $output->writeln(sprintf('<info>Testing Task: %s</info>', $taskName));
        $output->writeln(str_repeat('-', 60));

        $expression = $targetEvent->getExpression();
        $output->writeln(sprintf('Expression: %s', $expression));
        $output->writeln(sprintf('Human:      %s', CronExpression::describe($expression)));

        if (CronExpression::isDue($expression)) {
            $output->writeln('Due:        <info>YES (would run now)</info>');
        } else {
            $nextRun = CronExpression::nextRunDate($expression);
            $output->writeln(sprintf('Due:        NO (next: %s)', $nextRun?->format('Y-m-d H:i:s') ?? 'N/A'));
        }

        $output->writeln('');
        $output->writeln('Running task...');
        $output->writeln('');

        try {
            $startTime = microtime(true);
            $targetEvent->run();
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            $output->writeln(sprintf('<info>✓ Task completed successfully in %sms</info>', $elapsed));
            return 0;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>✗ Task failed: %s</error>', $e->getMessage()));
            $output->writeln('');
            $output->writeln('Stack trace:');
            $output->writeln($e->getTraceAsString());
            return 1;
        }
    }
}
