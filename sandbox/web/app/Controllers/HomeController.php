<?php

declare(strict_types=1);

namespace App\Controllers;

use Lalaz\Web\View\ViewResponse;

use function view;

class HomeController
{
    /**
     * Display the home page.
     */
    public function index(): ViewResponse
    {
        return view('home/index', [
            'title' => 'Welcome',
            'message' => 'Your Lalaz Web application is ready!',
        ]);
    }

    /**
     * Display the about page.
     */
    public function about(): ViewResponse
    {
        return view('home/about', [
            'title' => 'About',
        ]);
    }
}
