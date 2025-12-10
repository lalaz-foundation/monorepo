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
 * ScheduleListCommand - Display all scheduled tasks.
 *
 * @package lalaz/scheduler
 */
final class ScheduleListCommand implements CommandInterface
{
    public function __construct(
        private ContainerInterface $container
    ) {
    }

    public function name(): string
    {
        return 'schedule:list';
    }

    public function description(): string
    {
        return 'List all scheduled tasks.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            [
                'name' => 'next',
                'description' => 'Show the next run time for each task',
                'short' => 'n',
                'required' => false,
            ],
        ];
    }

    public function handle(Input $input, Output $output): int
    {
        $showNext = $input->option('next') !== null;

        // Resolve the schedule from the container
        if (!$this->container->has(Schedule::class)) {
            $output->writeln('<error>No Schedule registered in the container.</error>');
            return 1;
        }

        /** @var Schedule $schedule */
        $schedule = $this->container->resolve(Schedule::class);

        $events = $schedule->events();

        if (empty($events)) {
            $output->writeln('<info>No scheduled tasks defined.</info>');
            return 0;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Scheduled Tasks (%d total)</info>', count($events)));
        $output->writeln(str_repeat('-', 80));

        foreach ($events as $event) {
            $name = $event->getName();
            $expression = $event->getExpression();
            $description = $event->getDescription() ?? '';

            // Build the output line
            $line = sprintf('  %s', $expression);
            $line = str_pad($line, 22);
            $line .= sprintf('<comment>%s</comment>', $name);

            if ($description) {
                $line .= sprintf(' - %s', $description);
            }

            $output->writeln($line);

            // Show next run time if requested
            if ($showNext) {
                // Use the static API so static-analysis tools can validate the call
                $nextRun = CronExpression::nextRunDate($expression);

                if ($nextRun) {
                    $output->writeln(sprintf('                      Next: %s', $nextRun->format('Y-m-d H:i:s')));
                }
            }

            // Show constraints
            $constraints = [];

            if ($event->preventsOverlapping()) {
                $constraints[] = 'no overlap';
            }

            if ($event->runsOnOneServer()) {
                $constraints[] = 'one server';
            }

            if ($event->runsInBackground()) {
                $constraints[] = 'background';
            }

            if (!empty($constraints)) {
                $output->writeln(sprintf('                      [%s]', implode(', ', $constraints)));
            }
        }

        $output->writeln('');
        $output->writeln(str_repeat('-', 80));

        // Show mode information
        $mode = $_ENV['SCHEDULE_MODE'] ?? getenv('SCHEDULE_MODE') ?: 'single';
        $output->writeln(sprintf('Mode: <comment>%s</comment>', $mode));

        if ($mode === 'distributed') {
            $output->writeln('  Mutex coordination is <info>enabled</info> for overlap prevention.');
        } else {
            $output->writeln('  Running in single-server mode (no mutex coordination).');
        }

        $output->writeln('');

        return 0;
    }
}
