<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\Http\Response;

class WelcomeController
{
    public function index(): Response
    {
        return json([
            'message' => 'Welcome to Lalaz API',
            'version' => '1.0.0',
            'timestamp' => time(),
        ]);
    }

    public function health(): Response
    {
        return json([
            'status' => 'healthy',
            'timestamp' => time(),
        ]);
    }
}
