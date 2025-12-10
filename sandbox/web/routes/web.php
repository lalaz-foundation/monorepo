<?php

declare(strict_types=1);

use Lalaz\Web\Routing\Router;
use App\Controllers\HomeController;

return function (Router $router): void {
    // Home page
    $router->get('/', HomeController::class . '@index');

    // Example: About page
    $router->get('/about', HomeController::class . '@about');

    // Health check (returns JSON)
    $router->get('/health', function (): array {
        return [
            'status' => 'ok',
            'timestamp' => date('c'),
        ];
    });
};
