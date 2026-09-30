<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Support;

use Jengo\Queues\Contracts\FailedJobProviderInterface;
use Jengo\Queues\Entities\FailedJob;
use Throwable;

class MockFailedJobProvider implements FailedJobProviderInterface
{
    public array $jobs = [];
    private int $counter = 1;

    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int
    {
        $id = $this->counter++;
        $this->jobs[$id] = new FailedJob(
            id: $id,
            connection: $connection,
            queue: $queue,
            payload: $payload,
            exception: (string) $exception,
            failedAt: date('Y-m-d H:i:s')
        );

        return $id;
    }

    public function all(): array
    {
        return array_values($this->jobs);
    }

    public function find(string|int $id): ?FailedJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function forget(string|int $id): bool
    {
        if (isset($this->jobs[$id])) {
            unset($this->jobs[$id]);
            return true;
        }
        return false;
    }

    public function flush(?int $hours = null): int
    {
        $count = count($this->jobs);
        $this->jobs = [];
        return $count;
    }

    public function count(): int
    {
        return count($this->jobs);
    }
}
