<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Entities\JobPayload;

class NullQueueDriver extends AbstractQueueDriver
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->createPayload($job, $queue, 0)->id;
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->createPayload($job, $queue, $delay)->id;
    }

    public function pushRaw(JobPayload|string $payload, ?string $queue = null): string|int
    {
        $payloadObj = is_string($payload) ? JobPayload::fromArray(json_decode($payload, true) ?? []) : $payload;
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
            'driver' => 'null',
            'status' => 'ok',
        ];
    }
}
