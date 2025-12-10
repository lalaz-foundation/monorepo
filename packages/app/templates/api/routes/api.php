<?php

use Lalaz\Web\Routing\Router;
use App\Controllers\WelcomeController;

return function (Router $router) {
    $router->get('/', WelcomeController::class . '@index');
    $router->get('/health', WelcomeController::class . '@health');
};
