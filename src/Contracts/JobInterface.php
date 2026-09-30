<?php

declare(strict_types=1);

namespace Jengo\Queues\Contracts;

use Throwable;

interface JobInterface
{
    /**
     * Process the job payload.
     */
    public function fire(): void;

    /**
     * Get the job payload instance.
     */
    public function getPayload(): mixed;

    /**
     * Delete the job from the queue.
     */
    public function delete(): void;

    /**
     * Release the job back into the queue.
     */
    public function release(int $delay = 0): void;

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int;

    /**
     * Handle a job failure.
     */
    public function fail(?Throwable $e = null): void;
}
