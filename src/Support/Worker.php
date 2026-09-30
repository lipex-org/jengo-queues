<?php

declare(strict_types=1);

namespace Jengo\Queues\Support;

use Closure;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Entities\JobPayload;
use Throwable;

class Worker
{
    protected bool $shouldQuit = false;
    protected ?Closure $onJobProcessing = null;
    protected ?Closure $onJobProcessed = null;
    protected ?Closure $onJobFailed = null;

    public function __construct(
        protected QueueManager $manager,
        protected ?FailedJobManager $failedJobs = null
    ) {
    }

    public function setFailedJobManager(FailedJobManager $failedJobs): self
    {
        $this->failedJobs = $failedJobs;
        return $this;
    }

    public function onProcessing(Closure $callback): self
    {
        $this->onJobProcessing = $callback;
        return $this;
    }

    public function onProcessed(Closure $callback): self
    {
        $this->onJobProcessed = $callback;
        return $this;
    }

    public function onFailed(Closure $callback): self
    {
        $this->onJobFailed = $callback;
        return $this;
    }

    public function daemon(
        string $connectionName,
        string $queue,
        int $delay = 0,
        int $sleep = 3,
        int $maxTries = 0,
        int $memory = 128,
        int $timeout = 60,
        int $maxJobs = 0
    ): void {
        $jobsProcessed = 0;

        while (true) {
            if ($this->shouldQuit) {
                break;
            }

            if ($this->memoryExceeded($memory)) {
                $this->stop(12);
                break;
            }

            $job = $this->getNextJob($this->manager->connection($connectionName), $queue);

            if ($job instanceof JobInterface) {
                $jobsProcessed++;
                $this->process($connectionName, $job, $maxTries, $delay);

                if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                    break;
                }
            } else {
                $this->sleep($sleep);
            }
        }
    }

    public function runNextJob(string $connectionName, string $queue, int $delay = 0, int $maxTries = 0): ?JobInterface
    {
        $connection = $this->manager->connection($connectionName);
        $job = $this->getNextJob($connection, $queue);

        if ($job instanceof JobInterface) {
            $this->process($connectionName, $job, $maxTries, $delay);
            return $job;
        }

        return null;
    }

    protected function getNextJob(QueueDriverInterface $connection, string $queue): ?JobInterface
    {
        try {
            foreach (explode(',', $queue) as $q) {
                $q = trim($q);
                if ($job = $connection->pop($q)) {
                    return $job;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    public function process(string $connectionName, JobInterface $job, int $maxTries = 0, int $delay = 0): void
    {
        $payload = $job->getPayload();

        if ($this->onJobProcessing !== null) {
            ($this->onJobProcessing)($job, $connectionName);
        }

        $effectiveMaxTries = $payload->maxTries > 0 ? $payload->maxTries : $maxTries;

        // Check if attempts exceed max tries
        if ($effectiveMaxTries > 0 && $job->attempts() > $effectiveMaxTries) {
            $this->failJob($connectionName, $job, new \RuntimeException("Job has exceeded max tries ({$effectiveMaxTries})."));
            return;
        }

        try {
            $job->fire();
            $job->delete();

            if ($this->onJobProcessed !== null) {
                ($this->onJobProcessed)($job, $connectionName);
            }
        } catch (Throwable $e) {
            $this->handleJobException($connectionName, $job, $e, $effectiveMaxTries, $delay);
        }
    }

    protected function handleJobException(
        string $connectionName,
        JobInterface $job,
        Throwable $e,
        int $maxTries,
        int $delay
    ): void {
        $payload = $job->getPayload();
        $attempts = $job->attempts();

        if ($maxTries > 0 && $attempts >= $maxTries) {
            $this->failJob($connectionName, $job, $e);
            return;
        }

        // Calculate backoff
        $backoff = $payload->backoff > 0 ? $payload->backoff : $delay;
        $job->release($backoff);

        if ($this->onJobFailed !== null) {
            ($this->onJobFailed)($job, $e, false);
        }
    }

    protected function failJob(string $connectionName, JobInterface $job, Throwable $e): void
    {
        $job->fail($e);

        if ($this->failedJobs !== null) {
            $this->failedJobs->log(
                $connectionName,
                $job->getPayload()->queue,
                json_encode($job->getPayload()->jsonSerialize()),
                $e
            );
        }

        if ($this->onJobFailed !== null) {
            ($this->onJobFailed)($job, $e, true);
        }
    }

    public function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    public function stop(int $status = 0): void
    {
        $this->shouldQuit = true;
    }
}
