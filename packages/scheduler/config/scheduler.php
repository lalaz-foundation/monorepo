<?php

use Lalaz\Scheduler\Schedule;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used for evaluating scheduled task times. If null, the
    | application's default timezone will be used.
    |
    */
    'timezone' => env('SCHEDULE_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Scheduler Mode
    |--------------------------------------------------------------------------
    |
    | Defines how the scheduler handles concurrent execution:
    |
    | - "single": For single-server deployments. No mutex/locking needed.
    |             Tasks run without overlap protection by default.
    |
    | - "distributed": For multi-server/cluster deployments. Uses mutex
    |                  locking to prevent the same task from running on
    |                  multiple servers simultaneously.
    |
    | When using "distributed" mode, you must have lalaz/cache installed
    | with a shared cache backend (Redis, Memcached, etc).
    |
    */
    'mode' => env('SCHEDULE_MODE', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Mutex Configuration (Distributed Mode Only)
    |--------------------------------------------------------------------------
    |
    | These settings only apply when mode is "distributed".
    |
    */
    'mutex' => [
        // Cache store to use for distributed locks (null = default store)
        'store' => null,

        // Prefix for mutex keys in cache
        'prefix' => 'schedule:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Configure where task output should be logged.
    |
    */
    'output' => [
        // Log file for task output (null = no file logging)
        'log' => storage_path('logs/scheduler.log'),

        // Whether to append output to the log file
        'append' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Tasks
    |--------------------------------------------------------------------------
    |
    | Define your scheduled tasks here using the Schedule fluent API.
    |
    | Available methods:
    |   ->command('name')     Run an artisan command
    |   ->job(new Job)        Dispatch a job to the queue
    |   ->call(fn() => ...)   Execute a closure
    |
    | Frequency methods:
    |   ->everyMinute()       ->everyFiveMinutes()    ->everyFifteenMinutes()
    |   ->everyThirtyMinutes() ->hourly()             ->hourlyAt(17)
    |   ->daily()             ->dailyAt('13:00')      ->twiceDaily(1, 13)
    |   ->weekly()            ->weeklyOn(1, '8:00')   ->monthly()
    |   ->monthlyOn(4, '15:00') ->quarterly()         ->yearly()
    |   ->cron('* * * * *')
    |
    | Constraint methods:
    |   ->weekdays()          ->weekends()            ->sundays() ... ->saturdays()
    |   ->between('8:00', '17:00')                    ->unlessBetween('23:00', '4:00')
    |   ->when(fn() => true)                          ->skip(fn() => false)
    |   ->environments(['production'])
    |
    | Overlap prevention (requires distributed mode):
    |   ->withoutOverlapping($minutes = 1440)
    |   ->onOneServer()
    |
    | Output handling:
    |   ->sendOutputTo($path)
    |   ->appendOutputTo($path)
    |   ->emailOutputTo($email)
    |   ->emailOutputOnFailure($email)
    |
    */
    'tasks' => function (Schedule $schedule): void {
        // Example: Clean expired cache entries every hour
        // $schedule->command('cache:prune')
        //     ->hourly()
        //     ->description('Remove expired cache entries');

        // Example: Daily backup at 3 AM (single server)
        // $schedule->command('backup:run')
        //     ->dailyAt('03:00')
        //     ->description('Create daily backup');

        // Example: Process analytics every 15 minutes (distributed)
        // $schedule->job(new \App\Jobs\ProcessAnalytics)
        //     ->everyFifteenMinutes()
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->description('Process analytics data');

        // Example: Simple cleanup task
        // $schedule->call(function () {
        //     \App\Models\Session::expired()->delete();
        // })->daily()->description('Clean expired sessions');
    },
];
