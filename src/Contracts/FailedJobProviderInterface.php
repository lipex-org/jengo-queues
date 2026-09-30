<?php

declare(strict_types=1);

namespace Jengo\Queues\Contracts;

use Jengo\Queues\Entities\FailedJob;
use Throwable;

interface FailedJobProviderInterface
{
    /**
     * Log a failed job into storage.
     *
     * @param string $connection Connection name
     * @param string $queue Queue name
     * @param string $payload Serialized job payload
     * @param Throwable $exception The exception causing failure
     * @return int|string ID of the logged failure
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception): int|string;

    /**
     * Get a list of all failed jobs.
     *
     * @return FailedJob[]
     */
    public function all(): array;

    /**
     * Find a failed job by its ID.
     *
     * @param int|string $id
     * @return FailedJob|null
     */
    public function find(int|string $id): ?FailedJob;

    /**
     * Delete a single failed job by its ID.
     *
     * @param int|string $id
     * @return bool
     */
    public function forget(int|string $id): bool;

    /**
     * Flush all failed jobs from storage.
     *
     * @param int|null $hours
     * @return int Number of deleted records
     */
    public function flush(?int $hours = null): int;

    /**
     * Count total failed jobs.
     *
     * @return int
     */
    public function count(): int;
}
