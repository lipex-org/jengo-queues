<?php

declare(strict_types=1);

namespace Jengo\Queues\Jobs;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Drivers\DatabaseQueueDriver;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Traits\InteractsWithQueue;
use Throwable;

class DatabaseJob implements JobInterface
{
    use InteractsWithQueue;

    public function __construct(
        protected DatabaseQueueDriver $driver,
        protected object|array $record,
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
        $id = is_array($this->record) ? $this->record['id'] : $this->record->id;
        $this->driver->deleteReservedJob($this->queue, (int) $id);
    }

    public function release(int $delay = 0): void
    {
        $id = is_array($this->record) ? $this->record['id'] : $this->record->id;
        $this->driver->releaseJob($this->queue, (int) $id, $delay);
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
