<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Web\Routing\Router;

$router = new Router();
$count = (int) ($_SERVER['argv'][1] ?? 5000);

for ($i = 0; $i < $count; $i++) {
    $router->get('/dynamic/route/' . $i . '/{id}', fn() => null);
}

$max = 0;
$sizes = [];
$examples = [];

foreach ($router->all() as $route) {
    $ref = new ReflectionClass($route);
    if (!$ref->hasProperty('regex')) {
        continue;
    }

    $prop = $ref->getProperty('regex');
    $prop->setAccessible(true);
    $regex = $prop->getValue($route);

    $len = strlen($regex);
    $sizes[] = $len;
    if ($len > $max) {
        $max = $len;
        $examples = [$route->path(), $regex];
    }
}

rsort($sizes);

echo "Registered routes: " . count($sizes) . PHP_EOL;
echo "Max regex length: $max" . PHP_EOL;
echo "Top 5 regex lengths: " . implode(', ', array_slice($sizes, 0, 5)) . PHP_EOL;
echo "Example route path: " . ($examples[0] ?? '<none>') . PHP_EOL;
echo "Example regex (truncated): " . (substr($examples[1] ?? '', 0, 400)) . PHP_EOL;

// show memory usage briefly
echo "Memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
