<?php

declare(strict_types=1);

namespace Jengo\Queues\Jobs;

use Jengo\Base\Container\Container;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Traits\InteractsWithQueue;
use Throwable;

class GenericJob implements JobInterface
{
    use InteractsWithQueue;

    protected bool $deleted = false;
    protected bool $released = false;

    public function __construct(
        protected QueueDriverInterface $driver,
        protected JobPayload $payload,
        protected string $queue
    ) {
    }

    public function fire(): void
    {
        $instance = $this->payload->resolveInstance();

        // Inject job context if job uses InteractsWithQueue
        if (method_exists($instance, 'setJob')) {
            $instance->setJob($this);
        }

        if (method_exists($instance, 'handle')) {
            Container::getInstance()->call([$instance, 'handle']);
        }
    }

    public function getPayload(): JobPayload
    {
        return $this->payload;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function release(int $delay = 0): void
    {
        $this->released = true;
        $this->payload->availableAt = time() + max(0, $delay);
        $this->driver->pushRaw($this->payload, $this->queue);
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function attempts(): int
    {
        return $this->payload->attempts;
    }

    public function fail(?Throwable $e = null): void
    {
        $instance = $this->payload->resolveInstance();
        if ($e !== null && method_exists($instance, 'failed')) {
            Container::getInstance()->call([$instance, 'failed'], [
                'exception' => $e,
                'e' => $e,
                'throwable' => $e,
            ]);
        }
        $this->delete();
    }
}
