<?php declare(strict_types=1);

// Simple router benchmark harness for Lalaz framework
// Usage: php benchmarks/router_perf.php [--static N] [--dynamic M] [--lookups L]

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Web\Routing\Router;
use Lalaz\Web\Routing\RouteDefinition;

function micro_ms(): float
{
    return microtime(true) * 1000;
}

$options = getopt('', ['static::', 'dynamic::', 'lookups::']);

$staticCount = (int) ($options['static'] ?? 1000);
$dynamicCount = (int) ($options['dynamic'] ?? 0);
$lookups = (int) ($options['lookups'] ?? 10000);

printf("Router benchmark (static=%d, dynamic=%d, lookups=%d)\n", $staticCount, $dynamicCount, $lookups);

$router = new Router();

// Warm: register static routes
for ($i = 0; $i < $staticCount; $i++) {
    $path = '/static/route/' . $i;
    $router->get($path, function () use ($i) {
        return $i;
    });
}

// Register dynamic routes
for ($i = 0; $i < $dynamicCount; $i++) {
    $path = '/dynamic/route/' . $i . '/{id}';
    // Using a class-style handler string would be fine, but closures are simpler and realistic
    $router->get($path, function ($id) use ($i) {
        return ['i' => $i, 'id' => $id];
    });
}

// Build a list of sample paths to lookup: include existing static ones and some dynamic ones
$samplePaths = [];

// create a mix: 70% static lookups, 30% dynamic
$staticLookups = (int) round($lookups * 0.7);
$dynamicLookups = $lookups - $staticLookups;

for ($i = 0; $i < $staticLookups; $i++) {
    $idx = rand(0, max(0, $staticCount - 1));
    $samplePaths[] = ['GET', '/static/route/' . $idx];
}

for ($i = 0; $i < $dynamicLookups; $i++) {
    $idx = rand(0, max(0, $dynamicCount - 1));
    // if no dynamic routes, use some static paths as fallback
    if ($dynamicCount === 0) {
        $samplePaths[] = ['GET', '/static/route/' . rand(0, max(0, $staticCount - 1))];
    } else {
        $samplePaths[] = ['GET', '/dynamic/route/' . rand(0, $dynamicCount - 1) . '/123'];
    }
}

// Shuffle sample paths
shuffle($samplePaths);

// Measure matching performance
$matchTimes = [];
$start = micro_ms();
for ($i = 0, $n = count($samplePaths); $i < $n; $i++) {
    [$method, $path] = $samplePaths[$i];
    $t0 = micro_ms();
    try {
        $matched = $router->match($method, $path);
    } catch (Throwable $e) {
        // Not found -> continue
    }
    $t1 = micro_ms();

    $matchTimes[] = $t1 - $t0;
}
$end = micro_ms();

$totalMs = $end - $start;
$avg = array_sum($matchTimes) / max(1, count($matchTimes));
$min = min($matchTimes);
$max = max($matchTimes);
rsort($matchTimes);
$p95 = $matchTimes[(int) round(count($matchTimes) * 0.05)] ?? $max; // approximate

printf("Total loop time: %.3f ms\n", $totalMs);
printf("Lookup count: %d\n", count($matchTimes));
printf("Avg per lookup: %.6f ms\n", $avg);
printf("Min: %.6f ms, Max: %.6f ms, p95 approx: %.6f ms\n", $min, $max, $p95);

// Print small summary of memory used
printf("Memory: %.2f MB\n", memory_get_peak_usage(true) / 1024 / 1024);

// helpful note
printf("(This is a lightweight benchmark; use larger iterations for more stable measurements.)\n");
