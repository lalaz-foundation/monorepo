<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController
{
    public function index()
    {
        return view('home/index', [
            'title' => 'Welcome to Lalaz',
        ]);
    }

    public function about()
    {
        return view('home/about', [
            'title' => 'About Lalaz',
        ]);
    }
}
