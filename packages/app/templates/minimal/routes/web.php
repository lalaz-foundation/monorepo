<?php

use Lalaz\Web\Routing\Router;

return function (Router $router) {
    $router->get('/', fn() => json(['message' => 'Hello from Lalaz']));
};
