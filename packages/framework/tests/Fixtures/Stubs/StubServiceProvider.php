<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Fixtures\Stubs;

use Lalaz\Container\ServiceProvider;

/**
 * Stub service provider for testing provider registration and boot order.
 */
class StubServiceProvider extends ServiceProvider
{
    /** @var array<int, string> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function register(): void
    {
        self::$events[] = 'register';
        $this->instance('stub.provider.flag', 'registered');
    }

    public function boot(): void
    {
        self::$events[] = 'boot';
    }
}
