<?php

require __DIR__.'/../vendor/autoload.php';

use Lalaz\Runtime\Http\HttpApplication;

$app = HttpApplication::boot(__DIR__.'/..');
$app->run();
