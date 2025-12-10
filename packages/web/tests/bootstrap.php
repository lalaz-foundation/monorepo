<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for Web Package Tests.
 *
 * Initializes the test environment by loading Composer's autoloader
 * and setting up any necessary test fixtures.
 *
 * @package lalaz/web
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    throw new RuntimeException(
        'Composer autoloader not found. Run "composer install" in the web package directory.'
    );
}

require $autoload;

// Initialize session for tests (required for SessionManager, ViewDataBag, etc.)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
