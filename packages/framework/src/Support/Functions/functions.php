<?php

declare(strict_types=1);

/**
 * Framework helper functions loader.
 *
 * This file includes all helper function files to make them
 * globally available throughout the application.
 *
 * @package lalaz/framework
 * @author Lalaz Framework <hi@lalaz.dev>
 * @link https://lalaz.dev
 */

require_once __DIR__ . '/collections.php';
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/container.php';
require_once __DIR__ . '/formatting.php';
require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/responses.php';
require_once __DIR__ . '/retry.php';
require_once __DIR__ . '/routing.php';
require_once __DIR__ . '/tryCatch.php';
