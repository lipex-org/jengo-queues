<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Jobs\GenericJob;

class SyncQueueDriver extends AbstractQueueDriver
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        $payload = $this->createPayload($job, $queue, 0);
        $queueName = $this->qualifyQueue($queue);

        $genericJob = new GenericJob($this, $payload, $queueName);
        $genericJob->fire();

        return $payload->uuid;
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        // Sync executes immediately regardless of delay
        return $this->push($job, $data, $queue);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        return null;
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    public function clear(?string $queue = null): int
    {
        return 0;
    }

    public function status(): array
    {
        return [
            'driver' => 'sync',
            'status' => 'ok',
        ];
    }
}
