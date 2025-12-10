<?php

use Lalaz\Web\Routing\Router;
use App\Controllers\HomeController;

return function (Router $router) {
    $router->get('/', HomeController::class . '@index')->name('home');
    $router->get('/about', HomeController::class . '@about')->name('about');
};
