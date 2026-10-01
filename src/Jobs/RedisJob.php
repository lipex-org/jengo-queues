<?php

declare(strict_types=1);

namespace Jengo\Queues\Jobs;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Drivers\RedisQueueDriver;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Traits\InteractsWithQueue;
use Throwable;

class RedisJob implements JobInterface
{
    use InteractsWithQueue;

    public function __construct(
        protected RedisQueueDriver $driver,
        protected string $rawJob,
        protected JobPayload $payload,
        protected string $queue
    ) {
    }

    public function fire(): void
    {
        $instance = $this->payload->resolveInstance();

        if (method_exists($instance, 'setJob')) {
            $instance->setJob($this);
        }

        if (method_exists($instance, 'handle')) {
            $instance->handle();
        }
    }

    public function getPayload(): JobPayload
    {
        return $this->payload;
    }

    public function delete(): void
    {
        $this->driver->deleteReserved($this->queue, $this->rawJob);
    }

    public function release(int $delay = 0): void
    {
        $this->driver->deleteReserved($this->queue, $this->rawJob);

        $instance = $this->payload->resolveInstance();

        if ($delay > 0) {
            $this->driver->later($delay, $instance, '', $this->queue);
        } else {
            $this->driver->push($instance, '', $this->queue);
        }
    }

    public function attempts(): int
    {
        return $this->payload->attempts;
    }

    public function fail(?Throwable $e = null): void
    {
        $instance = $this->payload->resolveInstance();
        if ($e !== null && method_exists($instance, 'failed')) {
            $instance->failed($e);
        }
        $this->delete();
    }
}
