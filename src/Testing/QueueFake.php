<?php

declare(strict_types=1);

namespace Jengo\Queues\Testing;

use Closure;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Drivers\AbstractQueueDriver;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Jobs\GenericJob;
use Jengo\Queues\Support\QueueManager;
use PHPUnit\Framework\Assert as PHPUnit;

class QueueFake extends AbstractQueueDriver implements QueueDriverInterface
{
    /**
     * @var array<string, array<int, array{job: object|string, data: mixed, queue: string, delay: int, payload: JobPayload}>>
     */
    protected array $pushedJobs = [];

    public function __construct(protected ?QueueManager $manager = null)
    {
        parent::__construct();
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->recordPushedJob($job, $data, $queue, 0);
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->recordPushedJob($job, $data, $queue, $delay);
    }

    public function pushRaw(JobPayload|string $payload, ?string $queue = null): string
    {
        $queueName = $this->qualifyQueue($queue);
        $payloadObj = is_string($payload) ? JobPayload::fromArray(json_decode($payload, true) ?? []) : $payload;
        
        $this->pushedJobs[$queueName][] = [
            'job'     => $payloadObj->displayName,
            'data'    => $payloadObj->data,
            'queue'   => $queueName,
            'delay'   => 0,
            'payload' => $payloadObj,
        ];

        return $payloadObj->id;
    }

    protected function recordPushedJob(object|string $job, mixed $data, ?string $queue, int $delay): string
    {
        $queueName = $this->qualifyQueue($queue);
        $payload = $this->createPayload($job, $queueName, $delay);

        $this->pushedJobs[$queueName][] = [
            'job'     => $job,
            'data'    => $data,
            'queue'   => $queueName,
            'delay'   => $delay,
            'payload' => $payload,
        ];

        return $payload->id;
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queueName = $this->qualifyQueue($queue);

        if (empty($this->pushedJobs[$queueName])) {
            return null;
        }

        $jobData = array_shift($this->pushedJobs[$queueName]);
        $jobData['payload']->attempts++;

        return new GenericJob(
            $this,
            $jobData['payload'],
            $queueName
        );
    }

    public function size(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        return count($this->pushedJobs[$queueName] ?? []);
    }

    public function clear(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        $count = $this->size($queueName);
        $this->pushedJobs[$queueName] = [];
        return $count;
    }

    public function status(): array
    {
        return [
            'driver' => 'fake',
            'status' => 'ok',
            'pushed' => $this->allPushedCount(),
        ];
    }

    public function allPushedCount(): int
    {
        $total = 0;
        foreach ($this->pushedJobs as $jobs) {
            $total += count($jobs);
        }
        return $total;
    }

    /**
     * @return array<int, array{job: object|string, data: mixed, queue: string, delay: int, payload: JobPayload}>
     */
    public function pushed(string $jobClass, ?Closure $callback = null): array
    {
        $matching = [];

        foreach ($this->pushedJobs as $queue => $jobs) {
            foreach ($jobs as $jobData) {
                $job = $jobData['job'];
                $isMatch = is_object($job) ? $job instanceof $jobClass : $job === $jobClass;

                if ($isMatch) {
                    if ($callback === null || $callback($job, $jobData['queue'], $jobData['data'])) {
                        $matching[] = $jobData;
                    }
                }
            }
        }

        return $matching;
    }

    public function assertPushed(string $jobClass, ?Closure $callback = null): void
    {
        $count = count($this->pushed($jobClass, $callback));
        PHPUnit::assertTrue(
            $count > 0,
            "The expected [{$jobClass}] job was not pushed."
        );
    }

    public function assertPushedTimes(string $jobClass, int $times = 1, ?Closure $callback = null): void
    {
        $count = count($this->pushed($jobClass, $callback));
        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$jobClass}] job was pushed {$count} times instead of {$times} times."
        );
    }

    public function assertPushedOn(string $queue, string $jobClass, ?Closure $callback = null): void
    {
        $matching = [];
        foreach ($this->pushedJobs[$queue] ?? [] as $jobData) {
            $job = $jobData['job'];
            $isMatch = is_object($job) ? $job instanceof $jobClass : $job === $jobClass;
            if ($isMatch) {
                if ($callback === null || $callback($job, $queue, $jobData['data'])) {
                    $matching[] = $jobData;
                }
            }
        }

        PHPUnit::assertTrue(
            count($matching) > 0,
            "The expected [{$jobClass}] job was not pushed on queue [{$queue}]."
        );
    }

    public function assertNotPushed(string $jobClass, ?Closure $callback = null): void
    {
        $count = count($this->pushed($jobClass, $callback));
        PHPUnit::assertSame(
            0,
            $count,
            "The unexpected [{$jobClass}] job was pushed."
        );
    }

    public function assertNothingPushed(): void
    {
        PHPUnit::assertSame(
            0,
            $this->allPushedCount(),
            "Jobs were unexpectedly pushed to the queue."
        );
    }
}
