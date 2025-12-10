<?php declare(strict_types=1);

// Dynamic-only router benchmark harness for Lalaz framework
// Usage: php benchmarks/router_perf_dynamic.php --dynamic M --lookups L

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Web\Routing\Router;

function micro_ms(): float
{
    return microtime(true) * 1000;
}

$options = getopt('', ['dynamic::', 'lookups::']);

$dynamicCount = (int) ($options['dynamic'] ?? 1000);
$lookups = (int) ($options['lookups'] ?? 10000);

printf("Router DYNAMIC-only benchmark (dynamic=%d, lookups=%d)\n", $dynamicCount, $lookups);

$router = new Router();

// Register dynamic routes
for ($i = 0; $i < $dynamicCount; $i++) {
    $path = '/dynamic/route/' . $i . '/{id}';
    $router->get($path, function ($id) use ($i) {
        return ['i' => $i, 'id' => $id];
    });
}

// Build a list of sample dynamic paths to lookup (all dynamic)
$samplePaths = [];
for ($i = 0; $i < $lookups; $i++) {
    $idx = rand(0, max(0, $dynamicCount - 1));
    $samplePaths[] = ['GET', '/dynamic/route/' . $idx . '/123'];
}

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

printf("(Dynamic-only benchmark)\n");
