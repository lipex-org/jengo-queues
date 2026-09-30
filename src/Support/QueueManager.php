<?php

declare(strict_types=1);

namespace Jengo\Queues\Support;

use Closure;
use InvalidArgumentException;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Drivers\DatabaseQueueDriver;
use Jengo\Queues\Drivers\NullQueueDriver;
use Jengo\Queues\Drivers\RedisQueueDriver;
use Jengo\Queues\Drivers\SyncQueueDriver;
use Jengo\Queues\Testing\QueueFake;

class QueueManager
{
    /**
     * @var array<string, QueueDriverInterface>
     */
    protected array $drivers = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    protected ?QueueFake $fake = null;

    public function __construct(protected QueueConfig $config)
    {
    }

    public function connection(?string $name = null): QueueDriverInterface
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->config->default;
    }

    public function setDefaultDriver(string $name): void
    {
        $this->config->default = $name;
    }

    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    public function fake(): QueueFake
    {
        if ($this->fake === null) {
            $this->fake = new QueueFake($this);
        }

        return $this->fake;
    }

    public function isFaking(): bool
    {
        return $this->fake !== null;
    }

    public function unfake(): void
    {
        $this->fake = null;
    }

    public function getConfig(): QueueConfig
    {
        return $this->config;
    }

    protected function resolve(string $name): QueueDriverInterface
    {
        $config = $this->getConnectionConfig($name);

        $driver = $config['driver'] ?? null;

        if (!$driver) {
            throw new InvalidArgumentException("Queue connection [{$name}] has no driver configured.");
        }

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->config->prefix);
        }

        return match ($driver) {
            'sync'     => new SyncQueueDriver($config, $this->config->prefix),
            'null'     => new NullQueueDriver($config, $this->config->prefix),
            'database' => new DatabaseQueueDriver($config, $this->config->prefix),
            'redis'    => new RedisQueueDriver($config, $this->config->prefix),
            default    => throw new InvalidArgumentException("Unsupported queue driver [{$driver}]."),
        };
    }

    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->config->connections;

        if (!isset($connections[$name])) {
            throw new InvalidArgumentException("Queue connection [{$name}] is not defined.");
        }

        return $connections[$name];
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
