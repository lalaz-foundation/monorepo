# Event Drivers

Drivers are the transport mechanism for event publishing. They determine how and where events are delivered.

## Driver Interface

All drivers implement `EventDriverInterface`:

```php
interface EventDriverInterface
{
    /**
     * Publish an event.
     */
    public function publish(string $event, mixed $data, array $options = []): void;

    /**
     * Check if driver is available.
     */
    public function isAvailable(): bool;

    /**
     * Get driver name.
     */
    public function getName(): string;
}
```

## Built-in Drivers

### SyncDriver

Executes listeners inline in the current process.

```php
use Lalaz\Events\Drivers\SyncDriver;
use Lalaz\Events\ListenerRegistry;
use Lalaz\Events\ListenerResolver;

// Default construction
$driver = new SyncDriver();

// With custom dependencies
$driver = new SyncDriver(
    registry: new ListenerRegistry(),
    resolver: new ListenerResolver(fn($class) => $container->get($class))
);
```

#### Features

- Always available (`isAvailable()` returns true)
- Supports priority-based execution
- Provides listener management methods
- Error handling with continue/stop options

#### Methods

```php
// Listener management
$driver->addListener('event', $listener, priority: 0);
$driver->removeListener('event', $listener);
$driver->hasListeners('event');
$driver->getListeners('event');

// Publishing
$driver->publish('event', $data);
$driver->publish('event', $data, ['stop_on_error' => true]);

// Access internals
$registry = $driver->getRegistry();
$resolver = $driver->getResolver();
```

#### Use Cases

- Development environments
- Simple applications
- When guaranteed execution order matters
- Testing with real execution

---

### QueueDriver

Dispatches events to a queue for async processing.

```php
use Lalaz\Events\Drivers\QueueDriver;

$driver = new QueueDriver(
    queue: 'events',      // Queue name
    priority: 9,          // Default job priority
    delay: null           // Default delay in seconds
);
```

#### Features

- Events become background jobs
- Automatic serialization
- Configurable queue, priority, delay
- Requires Queue package

#### Per-Event Options

```php
$driver->publish('email.send', $data, [
    'queue' => 'high-priority',  // Override queue
    'priority' => 1,             // Override priority
    'delay' => 60,               // Delay 60 seconds
]);
```

#### Availability Check

```php
if ($driver->isAvailable()) {
    // Queue package is loaded and configured
    $driver->publish('event', $data);
}
```

#### Payload Format

Events are serialized as:

```php
[
    'event_name' => 'user.created',
    'event_data' => '{"id":1,"email":"user@example.com"}',
    'published_at' => '2024-01-01 12:00:00'
]
```

#### Use Cases

- Heavy event processing
- Email sending
- External API calls
- Any long-running operations

---

### NullDriver

No-op driver for testing or disabling events.

```php
use Lalaz\Events\Drivers\NullDriver;

// Silent mode (events disappear)
$driver = new NullDriver(recordEvents: false);

// Recording mode (for assertions)
$driver = new NullDriver(recordEvents: true);
```

#### Recording Events

```php
$driver = new NullDriver(recordEvents: true);

$driver->publish('user.created', ['id' => 1]);
$driver->publish('order.placed', ['id' => 123]);

// Get recorded events
$events = $driver->getRecordedEvents();
// [
//     ['event' => 'user.created', 'data' => ['id' => 1], 'options' => []],
//     ['event' => 'order.placed', 'data' => ['id' => 123], 'options' => []],
// ]

// Clear recordings
$driver->clearRecordedEvents();
```

#### Use Cases

- Unit testing
- Disabling events in specific environments
- Performance testing without side effects

---

## Creating Custom Drivers

### Redis Pub/Sub Driver

```php
use Lalaz\Events\Contracts\EventDriverInterface;
use Predis\Client;

class RedisDriver implements EventDriverInterface
{
    private Client $redis;
    private string $prefix;

    public function __construct(Client $redis, string $prefix = 'events:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    public function publish(string $event, mixed $data, array $options = []): void
    {
        $channel = $this->prefix . $event;
        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'published_at' => date('c'),
        ]);

        $this->redis->publish($channel, $payload);
    }

    public function isAvailable(): bool
    {
        try {
            $this->redis->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'redis';
    }
}
```

### Kafka Driver

```php
class KafkaDriver implements EventDriverInterface
{
    private \RdKafka\Producer $producer;
    private string $topic;

    public function __construct(\RdKafka\Producer $producer, string $topic = 'events')
    {
        $this->producer = $producer;
        $this->topic = $topic;
    }

    public function publish(string $event, mixed $data, array $options = []): void
    {
        $topic = $this->producer->newTopic($options['topic'] ?? $this->topic);
        
        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => microtime(true),
        ]);

        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload, $event);
        $this->producer->flush(1000);
    }

    public function isAvailable(): bool
    {
        return $this->producer !== null;
    }

    public function getName(): string
    {
        return 'kafka';
    }
}
```

### Webhook Driver

```php
class WebhookDriver implements EventDriverInterface
{
    private string $endpoint;
    private string $secret;

    public function __construct(string $endpoint, string $secret)
    {
        $this->endpoint = $endpoint;
        $this->secret = $secret;
    }

    public function publish(string $event, mixed $data, array $options = []): void
    {
        $payload = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Signature: ' . $signature,
                'X-Event: ' . $event,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }

    public function isAvailable(): bool
    {
        return !empty($this->endpoint);
    }

    public function getName(): string
    {
        return 'webhook';
    }
}
```

---

## Using Custom Drivers

### With EventHub

```php
use Lalaz\Events\EventHub;

$redisDriver = new RedisDriver($redis);
$hub = new EventHub(asyncDriver: $redisDriver);

// Events now go to Redis
$hub->trigger('user.created', $data);
```

### With EventManager

```php
use Lalaz\Events\EventManager;

$kafkaDriver = new KafkaDriver($producer);
$manager = new EventManager(asyncDriver: $kafkaDriver);

$manager->trigger('order.placed', $orderData);
```

### Dynamic Driver Switching

```php
// Start with queue
$hub = EventHub::withQueueDriver('events');

// Switch to Redis for specific scenario
$hub->setAsyncDriver(new RedisDriver($redis));
$hub->trigger('realtime.event', $data);

// Switch to sync for critical events
$hub->setAsyncEnabled(false);
$hub->trigger('payment.completed', $paymentData);
```

---

## Driver Comparison

| Driver | Latency | Reliability | Use Case |
|--------|---------|-------------|----------|
| SyncDriver | Immediate | High | Development, simple apps |
| QueueDriver | Low | High | Background processing |
| NullDriver | None | N/A | Testing |
| RedisDriver | Very Low | Medium | Real-time events |
| KafkaDriver | Low | Very High | High-throughput systems |
| WebhookDriver | Variable | Medium | External integrations |

---

## Subscribable Drivers

Some drivers can also receive/subscribe to events:

```php
interface SubscribableDriverInterface extends EventDriverInterface
{
    /**
     * Subscribe to events.
     *
     * @param callable $handler Function receiving (string $event, mixed $data)
     */
    public function subscribe(callable $handler): void;

    /**
     * Start listening for events (blocking).
     */
    public function listen(): void;

    /**
     * Stop listening.
     */
    public function stop(): void;
}
```

Example implementation for Redis:

```php
class RedisSubscribableDriver extends RedisDriver implements SubscribableDriverInterface
{
    private $handler;
    private bool $running = false;

    public function subscribe(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function listen(): void
    {
        $this->running = true;
        $pubsub = $this->redis->pubSubLoop();
        $pubsub->psubscribe($this->prefix . '*');

        foreach ($pubsub as $message) {
            if (!$this->running) break;
            
            if ($message->kind === 'pmessage') {
                $payload = json_decode($message->payload, true);
                ($this->handler)($payload['event'], $payload['data']);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }
}
```
