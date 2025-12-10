<?php declare(strict_types=1);

use Lalaz\Web\Routing\Router;
use App\Controllers\WelcomeController;

return function (Router $router): void {
    $router->get('/health', function (): array {
        return [
            'status' => 'ok',
            'timestamp' => date('c'),
        ];
    });

    $router->get('/', WelcomeController::class . '@index');
};
