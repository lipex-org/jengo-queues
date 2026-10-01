<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Facades\Queue;
use Jengo\Queues\Jobs\GenericJob;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Support\Worker;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Jobs\SampleTestJob;
use Tests\Support\Jobs\WorkerFailJob;
use Tests\Support\Jobs\WorkerSuccessJob;
use Tests\Support\Providers\MockFailedJobProvider;
use Throwable;

class WorkerExtendedTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Queue::unfake();
        \Config\Services::reset(true);
        parent::tearDown();
    }
    #[Test]
    public function worker_event_callbacks_fire(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $fake->push(new WorkerSuccessJob(), '', 'default');

        $worker = new Worker($manager);

        $processingCalled = false;
        $processedCalled = false;

        $worker->onProcessing(function (JobInterface $job, string $conn) use (&$processingCalled) {
            $processingCalled = true;
        });

        $worker->onProcessed(function (JobInterface $job, string $conn) use (&$processedCalled) {
            $processedCalled = true;
        });

        $worker->runNextJob('sync', 'default');

        $this->assertTrue($processingCalled);
        $this->assertTrue($processedCalled);
    }

    #[Test]
    public function worker_event_failure_callbacks_fire(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $job = (new WorkerFailJob())->tries(1);
        $fake->push($job, '', 'default');

        $failedProvider = new MockFailedJobProvider();
        $failedManager = new FailedJobManager($failedProvider, $manager);

        $worker = new Worker($manager, $failedManager);

        $failedCalled = false;
        $worker->onFailed(function (JobInterface $job, Throwable $e, bool $final) use (&$failedCalled) {
            $failedCalled = true;
        });

        $worker->runNextJob('sync', 'default', maxTries: 1);

        $this->assertTrue($failedCalled);
    }

    #[Test]
    public function worker_daemon_stops_on_memory_limit(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $worker = new Worker($manager);

        // Memory limit 0 MB will immediately trigger stop
        $worker->daemon('sync', 'default', sleep: 0, memory: 0);

        $this->assertTrue(true);
    }

    #[Test]
    public function worker_handles_comma_separated_queues(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $fake->push(new WorkerSuccessJob(), '', 'high');
        $fake->push(new WorkerSuccessJob(), '', 'low');

        $worker = new Worker($manager);

        $first = $worker->runNextJob('sync', 'high,low');
        $this->assertNotNull($first);
        $this->assertSame(1, $fake->size('low'));
        $this->assertSame(0, $fake->size('high'));
    }

    #[Test]
    public function generic_job_methods_cover_is_deleted_and_is_released(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $fake = $manager->fake();

        $payload = JobPayload::create(new SampleTestJob());
        $job = new GenericJob($fake, $payload, 'default');

        $this->assertFalse($job->isDeleted());
        $this->assertFalse($job->isReleased());

        $job->delete();
        $this->assertTrue($job->isDeleted());

        $job->release(10);
        $this->assertTrue($job->isReleased());
    }

    #[Test]
    public function traits_interacts_with_queue_and_queueable_setters(): void
    {
        $job = new SampleTestJob();
        $job->onConnection('redis')
            ->onQueue('priority')
            ->delay(10)
            ->tries(5)
            ->timeout(100)
            ->backoff(20);

        $this->assertSame('redis', $job->connection);
        $this->assertSame('priority', $job->queue);
        $this->assertSame(10, $job->delay);
        $this->assertSame(5, $job->tries);
        $this->assertSame(100, $job->timeout);
        $this->assertSame(20, $job->backoff);

        $fake = Queue::fake();
        $job->dispatch();
        $fake->assertPushed(SampleTestJob::class);
    }

    #[Test]
    public function interacts_with_queue_trait_methods(): void
    {
        $jobInstance = new SampleTestJob();
        $this->assertSame(1, $jobInstance->attempts());

        $payload = JobPayload::create($jobInstance);
        $genericJob = new GenericJob(Queue::fake(), $payload, 'default');
        $jobInstance->setJob($genericJob);

        $this->assertSame(0, $jobInstance->attempts());

        $jobInstance->release(15);
        $this->assertTrue($genericJob->isReleased());

        $jobInstance->delete();
        $this->assertTrue($genericJob->isDeleted());

        $jobInstance->fail(new RuntimeException('Job failed'));
        $this->assertTrue($genericJob->isDeleted());
    }

    #[Test]
    public function worker_fails_job_if_attempts_exceed_max_tries(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);
        $provider = new MockFailedJobProvider();
        $failedManager = new FailedJobManager($provider, $manager);
        $worker = new Worker($manager, $failedManager);

        $payload = JobPayload::create(new SampleTestJob());
        $payload->attempts = 10; // More than maxTries = 3
        $payload->maxTries = 3;

        $genericJob = new GenericJob(Queue::fake(), $payload, 'default');

        $failedCalled = false;
        $worker->onFailed(function (JobInterface $job, Throwable $e, bool $final) use (&$failedCalled) {
            $failedCalled = true;
            $this->assertTrue($final);
        });

        $worker->process('sync', $genericJob);

        $this->assertTrue($failedCalled);
        $this->assertSame(1, $failedManager->count());
    }
}
