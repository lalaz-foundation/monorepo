<?php declare(strict_types=1);

return [
    "enabled" => false,
    "driver" => "memory", // memory | mysql | pgsql | sqlite | database
    "connection" => null, // reuse default database connection
    "job_timeout" => 300, // seconds before a processing job is considered stuck
    "tables" => [
        "jobs" => "jobs",
        "failed" => "failed_jobs",
        "logs" => "job_logs",
    ],
];
