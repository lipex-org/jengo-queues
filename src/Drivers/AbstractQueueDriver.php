<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Entities\JobPayload;

abstract class AbstractQueueDriver implements QueueDriverInterface
{
    /**
     * @param array<string, mixed> $config
     * @param string $prefix
     */
    public function __construct(
        protected array $config = [],
        protected string $prefix = ''
    ) {
    }

    public function qualifyQueue(?string $queue): string
    {
        $queueName = $queue ?? ($this->config['queue'] ?? 'default');
        if ($this->prefix !== '' && !str_starts_with($queueName, $this->prefix)) {
            return $this->prefix . $queueName;
        }

        return $queueName;
    }

    protected function createPayload(object|string $job, ?string $queue = null, int $delay = 0): JobPayload
    {
        return JobPayload::create($job, $this->qualifyQueue($queue), $delay);
    }
}
