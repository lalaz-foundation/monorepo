<?php declare(strict_types=1);

/**
 * JWT Performance Benchmark
 *
 * Tests JWT encoding/decoding performance with different signers.
 *
 * Usage:
 *   php benchmarks/jwt_perf.php [--iterations N] [--signer SIGNER]
 *
 * Signers: hs256, hs384, hs512, rs256
 *
 * @package Lalaz\Auth\Benchmarks
 */

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha384Signer;
use Lalaz\Auth\Jwt\Signers\HmacSha512Signer;
use Lalaz\Auth\Jwt\Signers\RsaSha256Signer;

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
    if ($opsPerSec >= 1000000) {
        return sprintf('%.2fM ops/s', $opsPerSec / 1000000);
    }
    if ($opsPerSec >= 1000) {
        return sprintf('%.2fK ops/s', $opsPerSec / 1000);
    }
    return sprintf('%.2f ops/s', $opsPerSec);
}

function print_header(): void
{
    echo str_repeat('=', 70) . "\n";
    echo "Lalaz Auth - JWT Performance Benchmark\n";
    echo str_repeat('=', 70) . "\n";
    echo "PHP " . PHP_VERSION . " | " . PHP_OS . "\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('-', 70) . "\n\n";
}

function print_result(string $operation, int $iterations, float $totalMs): void
{
    $avgMs = $totalMs / $iterations;
    printf(
        "%-30s %8d iterations | Total: %12s | Avg: %12s | %s\n",
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

function benchmark_create_token(JwtEncoder $encoder, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $encoder->createAccessToken('user-' . $i, [
            'email' => 'user' . $i . '@example.com',
            'roles' => ['user', 'admin'],
        ]);
    }
    
    return micro_ms() - $start;
}

function benchmark_validate_token(JwtEncoder $encoder, string $token, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $encoder->validate($token);
    }
    
    return micro_ms() - $start;
}

function benchmark_decode_token(JwtEncoder $encoder, string $token, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $encoder->decode($token);
    }
    
    return micro_ms() - $start;
}

function benchmark_create_and_validate(JwtEncoder $encoder, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $token = $encoder->createAccessToken('user-' . $i);
        $encoder->validate($token);
    }
    
    return micro_ms() - $start;
}

function benchmark_refresh_flow(JwtEncoder $encoder, int $iterations): float
{
    $start = micro_ms();
    
    for ($i = 0; $i < $iterations; $i++) {
        $refresh = $encoder->createRefreshToken('user-' . $i);
        $payload = $encoder->decode($refresh);
        $encoder->createAccessToken($payload['sub']);
    }
    
    return micro_ms() - $start;
}

// ============================================================================
// Main
// ============================================================================

$options = getopt('', ['iterations::', 'signer::']);
$iterations = (int) ($options['iterations'] ?? 1000);
$signerName = $options['signer'] ?? 'all';

print_header();

// Secret for HMAC signers
$secret = 'benchmark-secret-key-at-least-32-bytes-long!!';

// RSA keys
$rsaKeys = null;
if ($signerName === 'all' || $signerName === 'rs256') {
    echo "Generating RSA keys (2048-bit)...\n";
    $rsaKeys = RsaSha256Signer::generateKeyPair(2048);
    echo "RSA keys generated.\n\n";
}

// Signers to benchmark
$signers = [];

if ($signerName === 'all' || $signerName === 'hs256') {
    $signers['HS256'] = new HmacSha256Signer($secret);
}
if ($signerName === 'all' || $signerName === 'hs384') {
    $signers['HS384'] = new HmacSha384Signer($secret);
}
if ($signerName === 'all' || $signerName === 'hs512') {
    $signers['HS512'] = new HmacSha512Signer($secret);
}
if ($signerName === 'all' || $signerName === 'rs256') {
    $signers['RS256'] = new RsaSha256Signer($rsaKeys['privateKey'], $rsaKeys['publicKey']);
}

foreach ($signers as $name => $signer) {
    echo "=== $name Signer ===\n\n";
    
    $encoder = new JwtEncoder($signer, issuer: 'benchmark');
    
    // Create a sample token for validation/decode tests
    $sampleToken = $encoder->createAccessToken('sample-user', [
        'email' => 'sample@example.com',
        'roles' => ['user'],
    ]);
    
    // Warmup
    for ($i = 0; $i < 100; $i++) {
        $encoder->createAccessToken('warmup');
        $encoder->validate($sampleToken);
    }
    
    // Benchmarks
    $createTime = benchmark_create_token($encoder, $iterations);
    print_result('Create Token', $iterations, $createTime);
    
    $validateTime = benchmark_validate_token($encoder, $sampleToken, $iterations);
    print_result('Validate Token', $iterations, $validateTime);
    
    $decodeTime = benchmark_decode_token($encoder, $sampleToken, $iterations);
    print_result('Decode Token', $iterations, $decodeTime);
    
    $flowIterations = max(100, $iterations / 10);
    $createValidateTime = benchmark_create_and_validate($encoder, (int) $flowIterations);
    print_result('Create + Validate', (int) $flowIterations, $createValidateTime);
    
    $refreshTime = benchmark_refresh_flow($encoder, (int) $flowIterations);
    print_result('Refresh Flow', (int) $flowIterations, $refreshTime);
    
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
echo "Benchmark complete.\n";
