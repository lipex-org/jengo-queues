<?php

declare(strict_types=1);

namespace Jengo\Queues\Testing;

use Closure;
use Jengo\Queues\Facades\Queue;

trait QueueTestAssertionsTrait
{
    protected ?QueueFake $queueFake = null;

    protected function fakeQueue(): QueueFake
    {
        return $this->queueFake = Queue::fake();
    }

    protected function assertPushed(string $jobClass, ?Closure $callback = null): void
    {
        $this->queueFake?->assertPushed($jobClass, $callback);
    }

    protected function assertPushedTimes(string $jobClass, int $times = 1, ?Closure $callback = null): void
    {
        $this->queueFake?->assertPushedTimes($jobClass, $times, $callback);
    }

    protected function assertPushedOn(string $queue, string $jobClass, ?Closure $callback = null): void
    {
        $this->queueFake?->assertPushedOn($queue, $jobClass, $callback);
    }

    protected function assertNotPushed(string $jobClass, ?Closure $callback = null): void
    {
        $this->queueFake?->assertNotPushed($jobClass, $callback);
    }

    protected function assertNothingPushed(): void
    {
        $this->queueFake?->assertNothingPushed();
    }

    protected function tearDownQueueFake(): void
    {
        Queue::unfake();
        $this->queueFake = null;
    }
}
