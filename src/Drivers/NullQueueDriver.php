<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;

class NullQueueDriver extends AbstractQueueDriver
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return 'null-job-id';
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return 'null-job-id';
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
