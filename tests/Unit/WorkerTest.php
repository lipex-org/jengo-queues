<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Support\Worker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Jobs\WorkerFailJob;
use Tests\Support\Jobs\WorkerSuccessJob;
use Tests\Support\Providers\MockFailedJobProvider;

class WorkerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WorkerSuccessJob::$runCount = 0;
        WorkerFailJob::$attemptCount = 0;
    }

    #[Test]
    public function worker_processes_successful_job(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $fake->push(new WorkerSuccessJob(), '', 'default');

        $worker = new Worker($manager);
        $processed = $worker->runNextJob('sync', 'default');

        $this->assertNotNull($processed);
        $this->assertSame(1, WorkerSuccessJob::$runCount);
        $this->assertSame(0, $fake->size('default'));
    }

    #[Test]
    public function worker_retries_failed_job_up_to_max_tries(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $job = (new WorkerFailJob())->tries(3)->backoff(5);
        $fake->push($job, '', 'default');

        $failedProvider = new MockFailedJobProvider();
        $failedManager = new FailedJobManager($failedProvider, $manager);

        $worker = new Worker($manager, $failedManager);

        // First attempt (attempt 1 -> failed -> released)
        $worker->runNextJob('sync', 'default', maxTries: 3);
        $this->assertSame(1, WorkerFailJob::$attemptCount);
        $this->assertSame(1, $fake->size('default'));
        $this->assertSame(0, $failedManager->count());

        // Second attempt (attempt 2 -> failed -> released)
        $worker->runNextJob('sync', 'default', maxTries: 3);
        $this->assertSame(2, WorkerFailJob::$attemptCount);
        $this->assertSame(1, $fake->size('default'));
        $this->assertSame(0, $failedManager->count());

        // Third attempt (attempt 3 -> failed -> logged to failed jobs)
        $worker->runNextJob('sync', 'default', maxTries: 3);
        $this->assertSame(3, WorkerFailJob::$attemptCount);
        $this->assertSame(0, $fake->size('default'));
        $this->assertSame(1, $failedManager->count());

        $failed = $failedManager->all()[0];
        $this->assertSame('default', $failed->queue);
        $this->assertStringContainsString('Job exploded', $failed->exception);
    }
}
