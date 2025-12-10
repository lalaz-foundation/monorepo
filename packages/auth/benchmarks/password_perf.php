<?php declare(strict_types=1);

/**
 * Password Hashing Performance Benchmark
 *
 * Tests password hashing and verification performance with different costs.
 *
 * Usage:
 *   php benchmarks/password_perf.php [--iterations N] [--cost C]
 *
 * @package Lalaz\Auth\Benchmarks
 */

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Auth\NativePasswordHasher;

// ============================================================================
// Helpers
// ============================================================================

function micro_ms(): float
{
    return microtime(true) * 1000;
}

function format_time(float $ms): string
{
    if ($ms < 1) {
        return sprintf('%.3f µs', $ms * 1000);
    }
    if ($ms < 1000) {
        return sprintf('%.3f ms', $ms);
    }
    return sprintf('%.3f s', $ms / 1000);
}

function format_ops(int $count, float $ms): string
{
    $opsPerSec = ($count / $ms) * 1000;
    if ($opsPerSec >= 1000) {
        return sprintf('%.2fK ops/s', $opsPerSec / 1000);
    }
    return sprintf('%.2f ops/s', $opsPerSec);
}

function print_header(): void
{
    echo str_repeat('=', 70) . "\n";
    echo "Lalaz Auth - Password Hashing Performance Benchmark\n";
    echo str_repeat('=', 70) . "\n";
    echo "PHP " . PHP_VERSION . " | " . PHP_OS . "\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('-', 70) . "\n\n";
}

function print_result(string $operation, int $iterations, float $totalMs): void
{
    $avgMs = $totalMs / $iterations;
    printf(
        "%-30s %6d iterations | Total: %12s | Avg: %12s | %s\n",
        $operation,
        $iterations,
        format_time($totalMs),
        format_time($avgMs),
        format_ops($iterations, $totalMs)
    );
}

// ============================================================================
// Benchmark Functions
// ============================================================================

function benchmark_hash(NativePasswordHasher $hasher, string $password, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $hasher->hash($password);
    }
    
    return micro_ms() - $start;
}

function benchmark_verify_correct(NativePasswordHasher $hasher, string $password, string $hash, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $hasher->verify($password, $hash);
    }
    
    return micro_ms() - $start;
}

function benchmark_verify_wrong(NativePasswordHasher $hasher, string $hash, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $hasher->verify('wrong-password', $hash);
    }
    
    return micro_ms() - $start;
}

function benchmark_needs_rehash(NativePasswordHasher $hasher, string $hash, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $hasher->needsRehash($hash);
    }
    
    return micro_ms() - $start;
}

function benchmark_hash_and_verify(NativePasswordHasher $hasher, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $password = 'password-' . $i;
        $hash = $hasher->hash($password);
        $hasher->verify($password, $hash);
    }
    
    return micro_ms() - $start;
}

// ============================================================================
// Main
// ============================================================================

$options = getopt('', ['iterations::', 'cost::']);
$iterations = (int) ($options['iterations'] ?? 100);
$cost = isset($options['cost']) ? (int) $options['cost'] : null;

print_header();

$testPassword = 'my-secure-password-123!';

// Test different cost factors
$costs = $cost !== null ? [$cost] : [10, 11, 12, 13];

foreach ($costs as $costFactor) {
    echo "=== Bcrypt Cost Factor: $costFactor ===\n\n";
    
    $hasher = new NativePasswordHasher(options: ['cost' => $costFactor]);
    
    // Create a sample hash for verification tests
    $sampleHash = $hasher->hash($testPassword);
    
    // Warmup
    for ($i = 0; $i < 3; $i++) {
        $hasher->hash('warmup');
        $hasher->verify('warmup', $sampleHash);
    }
    
    // Adjust iterations based on cost (higher cost = fewer iterations)
    $adjustedIterations = max(10, (int) ($iterations / pow(2, $costFactor - 10)));
    
    // Benchmarks
    $hashTime = benchmark_hash($hasher, $testPassword, $adjustedIterations);
    print_result('Hash Password', $adjustedIterations, $hashTime);
    
    $verifyCorrectTime = benchmark_verify_correct($hasher, $testPassword, $sampleHash, $adjustedIterations);
    print_result('Verify (Correct)', $adjustedIterations, $verifyCorrectTime);
    
    $verifyWrongTime = benchmark_verify_wrong($hasher, $sampleHash, $adjustedIterations);
    print_result('Verify (Wrong)', $adjustedIterations, $verifyWrongTime);
    
    // needsRehash is fast, can do more iterations
    $rehashIterations = $iterations * 100;
    $rehashTime = benchmark_needs_rehash($hasher, $sampleHash, $rehashIterations);
    print_result('Needs Rehash', $rehashIterations, $rehashTime);
    
    $flowIterations = max(5, $adjustedIterations / 2);
    $flowTime = benchmark_hash_and_verify($hasher, (int) $flowIterations);
    print_result('Hash + Verify', (int) $flowIterations, $flowTime);
    
    echo "\n";
}

// Security recommendations
echo str_repeat('=', 70) . "\n";
echo "Security Recommendations:\n";
echo str_repeat('-', 70) . "\n";
echo "- Cost 10: ~100ms per hash (minimum recommended)\n";
echo "- Cost 11: ~200ms per hash (good balance)\n";
echo "- Cost 12: ~400ms per hash (production recommended)\n";
echo "- Cost 13: ~800ms per hash (high security)\n";
echo "\nTarget: 100-500ms per hash for good UX with strong security.\n";
echo str_repeat('=', 70) . "\n";
echo "Benchmark complete.\n";
