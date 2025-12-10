<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Lalaz\Container\Container;
use Lalaz\Container\BindingConfiguration;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;

#[CoversClass(Container::class)]
#[CoversClass(BindingConfiguration::class)]
class TaggedBindingsTest extends FrameworkUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        $this->container->flush();
    }

    #[Test]
    public function it_can_tag_a_single_service_with_one_tag(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->tag(FileLogger::class, 'loggers');

        $this->assertTrue($this->container->hasTag('loggers'));
        $this->assertContains(FileLogger::class, $this->container->getTaggedAbstracts('loggers'));
    }

    #[Test]
    public function it_can_tag_a_service_with_multiple_tags(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->tag(FileLogger::class, ['loggers', 'file-handlers']);

        $this->assertTrue($this->container->hasTag('loggers'));
        $this->assertTrue($this->container->hasTag('file-handlers'));
        $this->assertContains(FileLogger::class, $this->container->getTaggedAbstracts('loggers'));
        $this->assertContains(FileLogger::class, $this->container->getTaggedAbstracts('file-handlers'));
    }

    #[Test]
    public function it_can_tag_multiple_services_with_same_tag(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->bind(DatabaseLogger::class, DatabaseLogger::class);
        $this->container->bind(ConsoleLogger::class, ConsoleLogger::class);

        $this->container->tag(FileLogger::class, 'loggers');
        $this->container->tag(DatabaseLogger::class, 'loggers');
        $this->container->tag(ConsoleLogger::class, 'loggers');

        $abstracts = $this->container->getTaggedAbstracts('loggers');

        $this->assertCount(3, $abstracts);
        $this->assertContains(FileLogger::class, $abstracts);
        $this->assertContains(DatabaseLogger::class, $abstracts);
        $this->assertContains(ConsoleLogger::class, $abstracts);
    }

    #[Test]
    public function it_resolves_all_tagged_services(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->bind(DatabaseLogger::class, DatabaseLogger::class);

        $this->container->tag(FileLogger::class, 'loggers');
        $this->container->tag(DatabaseLogger::class, 'loggers');

        $loggers = $this->container->tagged('loggers');

        $this->assertCount(2, $loggers);
        $this->assertInstanceOf(FileLogger::class, $loggers[0]);
        $this->assertInstanceOf(DatabaseLogger::class, $loggers[1]);
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_tag(): void
    {
        $result = $this->container->tagged('nonexistent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_abstracts_for_nonexistent_tag(): void
    {
        $result = $this->container->getTaggedAbstracts('nonexistent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_does_not_duplicate_same_abstract_in_tag(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->tag(FileLogger::class, 'loggers');
        $this->container->tag(FileLogger::class, 'loggers');
        $this->container->tag(FileLogger::class, 'loggers');

        $abstracts = $this->container->getTaggedAbstracts('loggers');

        $this->assertCount(1, $abstracts);
    }

    #[Test]
    public function it_resolves_singletons_correctly_with_tags(): void
    {
        $this->container->singleton(FileLogger::class, FileLogger::class);
        $this->container->tag(FileLogger::class, 'loggers');

        $taggedLoggers = $this->container->tagged('loggers');
        $directResolve = $this->container->resolve(FileLogger::class);

        $this->assertSame($directResolve, $taggedLoggers[0]);
    }

    #[Test]
    public function has_tag_returns_false_for_nonexistent_tag(): void
    {
        $this->assertFalse($this->container->hasTag('nonexistent'));
    }

    #[Test]
    public function has_tag_returns_false_for_empty_tag(): void
    {
        // Tag exists in storage but has no services
        $this->assertFalse($this->container->hasTag('empty-tag'));
    }

    #[Test]
    public function flush_clears_all_tags(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->tag(FileLogger::class, 'loggers');

        $this->assertTrue($this->container->hasTag('loggers'));

        $this->container->flush();

        $this->assertFalse($this->container->hasTag('loggers'));
    }

    #[Test]
    public function it_can_resolve_auto_wired_services_with_tags(): void
    {
        // Auto-wire without explicit binding
        $this->container->tag(FileLogger::class, 'loggers');

        $loggers = $this->container->tagged('loggers');

        $this->assertCount(1, $loggers);
        $this->assertInstanceOf(FileLogger::class, $loggers[0]);
    }

    #[Test]
    public function it_resolves_services_with_dependencies_via_tags(): void
    {
        $this->container->bind(LoggerInterface::class, FileLogger::class);
        $this->container->bind(NotificationService::class, NotificationService::class);
        $this->container->tag(NotificationService::class, 'services');

        $services = $this->container->tagged('services');

        $this->assertCount(1, $services);
        $this->assertInstanceOf(NotificationService::class, $services[0]);
    }

    #[Test]
    public function it_maintains_tag_order(): void
    {
        $this->container->bind(FileLogger::class, FileLogger::class);
        $this->container->bind(DatabaseLogger::class, DatabaseLogger::class);
        $this->container->bind(ConsoleLogger::class, ConsoleLogger::class);

        $this->container->tag(FileLogger::class, 'loggers');
        $this->container->tag(DatabaseLogger::class, 'loggers');
        $this->container->tag(ConsoleLogger::class, 'loggers');

        $loggers = $this->container->tagged('loggers');

        $this->assertInstanceOf(FileLogger::class, $loggers[0]);
        $this->assertInstanceOf(DatabaseLogger::class, $loggers[1]);
        $this->assertInstanceOf(ConsoleLogger::class, $loggers[2]);
    }

    #[Test]
    public function binding_configuration_allows_fluent_tagging(): void
    {
        $config = new BindingConfiguration($this->container, FileLogger::class);
        $result = $config->tag('loggers');

        $this->assertSame($config, $result);
        $this->assertTrue($this->container->hasTag('loggers'));
        $this->assertContains(FileLogger::class, $this->container->getTaggedAbstracts('loggers'));
    }

    #[Test]
    public function binding_configuration_allows_multiple_tags(): void
    {
        $config = new BindingConfiguration($this->container, FileLogger::class);
        $config->tag(['loggers', 'file-handlers', 'debuggers']);

        $this->assertTrue($this->container->hasTag('loggers'));
        $this->assertTrue($this->container->hasTag('file-handlers'));
        $this->assertTrue($this->container->hasTag('debuggers'));
    }

    #[Test]
    public function binding_configuration_can_chain_tags(): void
    {
        $config = new BindingConfiguration($this->container, FileLogger::class);
        $config
            ->tag('loggers')
            ->tag('file-handlers')
            ->tag('debuggers');

        $this->assertTrue($this->container->hasTag('loggers'));
        $this->assertTrue($this->container->hasTag('file-handlers'));
        $this->assertTrue($this->container->hasTag('debuggers'));
    }
}

// Test doubles

interface LoggerInterface
{
    public function log(string $message): void;
}

class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        // File logging implementation
    }
}

class DatabaseLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        // Database logging implementation
    }
}

class ConsoleLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        // Console logging implementation
    }
}

class NotificationService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function notify(string $message): void
    {
        $this->logger->log("Notification: {$message}");
    }
}
