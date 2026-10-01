<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Testing\QueueFake;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Jobs\DummyFailedJob;
use Tests\Support\Providers\MockFailedJobProvider;

class FailedJobManagerTest extends CIUnitTestCase
{
    #[Test]
    public function it_logs_retrieves_and_forgets_failed_jobs(): void
    {
        $provider = new MockFailedJobProvider();
        $manager = new FailedJobManager($provider);

        $payload = json_encode(JobPayload::create(new DummyFailedJob('arg1', 10))->jsonSerialize());
        $id = $manager->log('default', 'emails', $payload, new RuntimeException('SMTP timeout'));

        $this->assertSame(1, $manager->count());

        $failedJob = $manager->find($id);
        $this->assertNotNull($failedJob);
        $this->assertSame('emails', $failedJob->queue);
        $this->assertSame('default', $failedJob->connection);
        $this->assertStringContainsString('SMTP timeout', $failedJob->exception);

        $this->assertTrue($manager->forget($id));
        $this->assertSame(0, $manager->count());
        $this->assertNull($manager->find($id));
    }

    #[Test]
    public function it_flushes_all_failed_jobs(): void
    {
        $provider = new MockFailedJobProvider();
        $manager = new FailedJobManager($provider);

        $payload = json_encode(JobPayload::create(new DummyFailedJob('arg', 1))->jsonSerialize());
        $manager->log('default', 'emails', $payload, new RuntimeException('Error 1'));
        $manager->log('default', 'emails', $payload, new RuntimeException('Error 2'));

        $this->assertSame(2, $manager->count());
        $flushed = $manager->flush();
        $this->assertSame(2, $flushed);
        $this->assertSame(0, $manager->count());
    }

    #[Test]
    public function it_retries_failed_jobs(): void
    {
        $provider = new MockFailedJobProvider();
        $fakeQueue = new QueueFake();

        $queueManager = $this->createMock(QueueManager::class);
        $queueManager->method('connection')->willReturn($fakeQueue);

        $manager = new FailedJobManager($provider, $queueManager);

        $job = new DummyFailedJob('to-retry', 99);
        $payload = json_encode(JobPayload::create($job, 'emails')->jsonSerialize());
        $id = $manager->log('default', 'emails', $payload, new RuntimeException('Fail'));

        $this->assertSame(1, $manager->count());
        $this->assertSame(0, $fakeQueue->size('emails'));

        $result = $manager->retry($id);

        $this->assertTrue($result);
        $this->assertSame(0, $manager->count());
        $this->assertSame(1, $fakeQueue->size('emails'));
    }
}
