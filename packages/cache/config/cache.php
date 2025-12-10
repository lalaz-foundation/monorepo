<?php declare(strict_types=1);

return [
    "enabled" => true,
    "driver" => "array",
    "prefix" => "lalaz_",
    "stores" => [
        "array" => [
            "driver" => "array",
        ],
        "file" => [
            "driver" => "file",
            "path" => sys_get_temp_dir() . "/lalaz-flash",
        ],
        "apcu" => [
            "driver" => "apcu",
        ],
        "redis" => [
            "driver" => "redis",
            "host" => "127.0.0.1",
            "port" => 6379,
            "database" => 0,
            "password" => null,
            "timeout" => 0.0,
        ],
    ],
];
