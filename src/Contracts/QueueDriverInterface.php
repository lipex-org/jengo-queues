<?php

declare(strict_types=1);

namespace Jengo\Queues\Contracts;

interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     *
     * @param object|string $job
     * @param mixed $data
     * @param string|null $queue
     * @return string|int
     */
    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int;

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param int $delay Delay in seconds
     * @param object|string $job
     * @param mixed $data
     * @param string|null $queue
     * @return string|int
     */
    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int;

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return JobInterface|null
     */
    public function pop(?string $queue = null): ?JobInterface;

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size(?string $queue = null): int;

    /**
     * Clear all jobs from the queue.
     *
     * @param string|null $queue
     * @return int Number of cleared jobs
     */
    public function clear(?string $queue = null): int;

    /**
     * Check queue driver health and connection status.
     *
     * @return array<string, mixed>
     */
    public function status(): array;
}
