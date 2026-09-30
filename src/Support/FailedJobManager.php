<?php

declare(strict_types=1);

namespace Jengo\Queues\Support;

use Jengo\Queues\Contracts\FailedJobProviderInterface;
use Jengo\Queues\Entities\FailedJob;
use Throwable;

class FailedJobManager
{
    public function __construct(
        protected FailedJobProviderInterface $provider,
        protected ?QueueManager $queueManager = null
    ) {
    }

    public function getProvider(): FailedJobProviderInterface
    {
        return $this->provider;
    }

    public function setQueueManager(QueueManager $manager): self
    {
        $this->queueManager = $manager;
        return $this;
    }

    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int
    {
        return $this->provider->log($connection, $queue, $payload, $exception);
    }

    /**
     * @return FailedJob[]
     */
    public function all(): array
    {
        return $this->provider->all();
    }

    public function find(string|int $id): ?FailedJob
    {
        return $this->provider->find($id);
    }

    public function forget(string|int $id): bool
    {
        return $this->provider->forget($id);
    }

    public function flush(?int $hours = null): int
    {
        return $this->provider->flush($hours);
    }

    public function count(): int
    {
        return $this->provider->count();
    }

    public function retry(string|int $id): bool
    {
        $failedJob = $this->find($id);
        if (!$failedJob) {
            return false;
        }

        $payload = $failedJob->getJobPayload();
        $payload->attempts = 0; // Reset attempts

        $manager = $this->queueManager ?? \Config\Services::queues();
        $connection = $manager->connection($failedJob->connection);

        $connection->pushRaw($payload, $failedJob->queue);
        $this->forget($id);

        return true;
    }

    public function retryAll(?string $queue = null): int
    {
        $all = $this->all();
        $retried = 0;

        foreach ($all as $job) {
            if ($queue !== null && $job->queue !== $queue) {
                continue;
            }
            if ($this->retry($job->id)) {
                $retried++;
            }
        }

        return $retried;
    }
}
