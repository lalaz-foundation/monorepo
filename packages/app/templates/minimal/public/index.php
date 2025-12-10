<?php

require __DIR__.'/../vendor/autoload.php';

use Lalaz\Runtime\Http\HttpApplication;

$app = HttpApplication::create(__DIR__.'/..');

$response = $app->handleRequest();
$app->sendResponse($response);
