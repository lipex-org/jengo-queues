<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Jobs\GenericJob;

class SyncQueueDriver extends AbstractQueueDriver
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->pushRaw($this->createPayload($job, $queue, 0), $queue);
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        // Sync executes immediately regardless of delay
        return $this->push($job, $data, $queue);
    }

    public function pushRaw(JobPayload|string $payload, ?string $queue = null): string|int
    {
        $payloadObj = is_string($payload) ? JobPayload::fromArray(json_decode($payload, true) ?? []) : $payload;
        $queueName = $this->qualifyQueue($queue);

        $genericJob = new GenericJob($this, $payloadObj, $queueName);
        $genericJob->fire();

        return $payloadObj->id;
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
