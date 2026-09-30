<?php

declare(strict_types=1);

namespace Jengo\Queues\Traits;

use Jengo\Queues\Facades\Queue;

trait Queueable
{
    public ?string $queue = null;
    public ?string $connection = null;
    public int $delay = 0;
    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 0;

    public function onQueue(?string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function onConnection(?string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function delay(int $delayInSeconds): self
    {
        $this->delay = max(0, $delayInSeconds);
        return $this;
    }

    public function tries(int $tries): self
    {
        $this->tries = max(1, $tries);
        return $this;
    }

    public function timeout(int $timeout): self
    {
        $this->timeout = max(1, $timeout);
        return $this;
    }

    public function backoff(int $backoff): self
    {
        $this->backoff = max(0, $backoff);
        return $this;
    }

    public function dispatch(): mixed
    {
        if ($this->delay > 0) {
            return Queue::connection($this->connection)->later($this->delay, $this, '', $this->queue);
        }

        return Queue::connection($this->connection)->push($this, '', $this->queue);
    }
}
