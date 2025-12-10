<?php

declare(strict_types=1);

namespace App\Controllers;

class WelcomeController
{
    public function index(): array
    {
        return [
            'name' => config('app.name', 'Lalaz API'),
            'message' => 'Welcome to your new API!',
            'version' => '1.0.0',
            'docs' => 'https://lalaz.dev/docs',
        ];
    }
}
