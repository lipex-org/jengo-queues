<?php

declare(strict_types=1);

namespace Jengo\Queues\Traits;

use Jengo\Queues\Contracts\JobInterface;
use Throwable;

trait InteractsWithQueue
{
    public ?JobInterface $job = null;

    public function setJob(JobInterface $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function delete(): void
    {
        $this->job?->delete();
    }

    public function release(int $delay = 0): void
    {
        $this->job?->release($delay);
    }

    public function attempts(): int
    {
        return $this->job ? $this->job->attempts() : 1;
    }

    public function fail(?Throwable $e = null): void
    {
        $this->job?->fail($e);
    }
}
