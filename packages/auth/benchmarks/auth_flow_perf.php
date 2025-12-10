<?php declare(strict_types=1);

/**
 * Authentication Flow Performance Benchmark
 *
 * Tests complete authentication workflows including guards and providers.
 *
 * Usage:
 *   php benchmarks/auth_flow_perf.php [--iterations N]
 *
 * @package Lalaz\Auth\Benchmarks
 */

require __DIR__ . '/../vendor/autoload.php';

use Lalaz\Auth\AuthManager;
use Lalaz\Auth\NativePasswordHasher;
use Lalaz\Auth\Jwt\JwtEncoder;
use Lalaz\Auth\Jwt\JwtBlacklist;
use Lalaz\Auth\Jwt\Signers\HmacSha256Signer;
use Lalaz\Auth\Guards\JwtGuard;
use Lalaz\Auth\Guards\SessionGuard;
use Lalaz\Auth\Providers\GenericUserProvider;
use Lalaz\Auth\Contracts\AuthenticatableInterface;

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
    echo "Lalaz Auth - Authentication Flow Performance Benchmark\n";
    echo str_repeat('=', 70) . "\n";
    echo "PHP " . PHP_VERSION . " | " . PHP_OS . "\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('-', 70) . "\n\n";
}

function print_result(string $operation, int $iterations, float $totalMs): void
{
    $avgMs = $totalMs / $iterations;
    printf(
        "%-35s %6d iters | Total: %10s | Avg: %10s | %s\n",
        $operation,
        $iterations,
        format_time($totalMs),
        format_time($avgMs),
        format_ops($iterations, $totalMs)
    );
}

// ============================================================================
// Fake User Class
// ============================================================================

class BenchmarkUser implements AuthenticatableInterface
{
    public function __construct(
        private string $id,
        private string $password,
        public string $email,
    ) {}

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function setRememberToken(?string $value): void
    {
    }
}

// ============================================================================
// Setup
// ============================================================================

$options = getopt('', ['iterations::']);
$iterations = (int) ($options['iterations'] ?? 500);

print_header();

$secret = 'benchmark-secret-key-at-least-32-bytes-long!!';
$hasher = new NativePasswordHasher(options: ['cost' => 4]); // Low cost for benchmark
$hashedPassword = $hasher->hash('correct-password');

// Create test users
$users = [];
for ($i = 0; $i < 1000; $i++) {
    $users['user-' . $i] = new BenchmarkUser(
        'user-' . $i,
        $hashedPassword,
        'user' . $i . '@example.com'
    );
}

// ============================================================================
// JWT Guard Benchmarks
// ============================================================================

echo "=== JWT Guard Performance ===\n\n";

$encoder = new JwtEncoder(new HmacSha256Signer($secret), issuer: 'benchmark');
$blacklist = new JwtBlacklist();

$provider = (new GenericUserProvider())
    ->setByIdCallback(fn($id) => $users[$id] ?? null)
    ->setByCredentialsCallback(function ($creds) use ($users) {
        foreach ($users as $user) {
            if ($user->email === ($creds['email'] ?? '')) {
                return $user;
            }
        }
        return null;
    });

$jwtGuard = new JwtGuard($encoder, $blacklist, $provider);

// Warmup
$warmupToken = $encoder->createAccessToken('user-0');
for ($i = 0; $i < 50; $i++) {
    $jwtGuard->authenticateToken($warmupToken);
}

// JWT Token Creation
$start = micro_ms();
$tokens = [];
for ($i = 0; $i < $iterations; $i++) {
    $tokens[] = $encoder->createAccessToken('user-' . ($i % 1000));
}
$createTime = micro_ms() - $start;
print_result('JWT Create Token', $iterations, $createTime);

// JWT Token Authentication
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $jwtGuard->authenticateToken($tokens[$i]);
}
$authTime = micro_ms() - $start;
print_result('JWT Authenticate Token', $iterations, $authTime);

// JWT Create + Authenticate Flow
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $token = $encoder->createAccessToken('user-' . ($i % 1000));
    $jwtGuard->authenticateToken($token);
}
$flowTime = micro_ms() - $start;
print_result('JWT Create + Authenticate', $iterations, $flowTime);

// JWT Refresh Token Pair
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $encoder->createAccessToken('user-' . ($i % 1000));
    $encoder->createRefreshToken('user-' . ($i % 1000));
}
$pairTime = micro_ms() - $start;
print_result('JWT Token Pair (Access+Refresh)', $iterations, $pairTime);

echo "\n";

// ============================================================================
// AuthManager Benchmarks
// ============================================================================

echo "=== AuthManager Performance ===\n\n";

// Manager with single guard
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $manager = new AuthManager();
    $manager->register('jwt', new JwtGuard($encoder, $blacklist, $provider));
}
$registerTime = micro_ms() - $start;
print_result('Manager Create + Register Guard', $iterations, $registerTime);

// Guard resolution
$manager = new AuthManager();
$manager->register('jwt', $jwtGuard);
$manager->setDefaultGuard('jwt');

$start = micro_ms();
for ($i = 0; $i < $iterations * 10; $i++) {
    $manager->guard('jwt');
}
$resolveTime = micro_ms() - $start;
print_result('Guard Resolution', $iterations * 10, $resolveTime);

// Default guard operations
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $manager->check();
}
$checkTime = micro_ms() - $start;
print_result('Manager check() (unauthenticated)', $iterations, $checkTime);

echo "\n";

// ============================================================================
// Blacklist Benchmarks
// ============================================================================

echo "=== JWT Blacklist Performance ===\n\n";

$freshBlacklist = new JwtBlacklist();

// Add to blacklist
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $freshBlacklist->add('jti-' . $i, time() + 3600);
}
$addTime = micro_ms() - $start;
print_result('Blacklist Add', $iterations, $addTime);

// Check blacklist (hit)
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $freshBlacklist->has('jti-' . ($i % $iterations));
}
$hitTime = micro_ms() - $start;
print_result('Blacklist Check (Hit)', $iterations, $hitTime);

// Check blacklist (miss)
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $freshBlacklist->has('nonexistent-' . $i);
}
$missTime = micro_ms() - $start;
print_result('Blacklist Check (Miss)', $iterations, $missTime);

echo "\n";

// ============================================================================
// Provider Benchmarks
// ============================================================================

echo "=== User Provider Performance ===\n\n";

// Lookup by ID
$start = micro_ms();
for ($i = 0; $i < $iterations; $i++) {
    $provider->retrieveById('user-' . ($i % 1000));
}
$byIdTime = micro_ms() - $start;
print_result('Provider retrieveById', $iterations, $byIdTime);

// Lookup by credentials
$start = micro_ms();
for ($i = 0; $i < min($iterations, 100); $i++) { // Fewer iterations due to loop overhead
    $provider->retrieveByCredentials(['email' => 'user' . ($i % 100) . '@example.com']);
}
$byCredsTime = micro_ms() - $start;
print_result('Provider retrieveByCredentials', min($iterations, 100), $byCredsTime);

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "Benchmark complete.\n";
