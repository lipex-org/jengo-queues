<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Contracts\FailedJobProviderInterface;
use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Entities\FailedJob;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Testing\QueueFake;
use Jengo\Queues\Traits\Queueable;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

class DummyFailedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $param1 = 'hello', public int $param2 = 42)
    {
    }

    public function handle(): void
    {
    }
}

class MockFailedJobProvider implements FailedJobProviderInterface
{
    public array $jobs = [];
    private int $counter = 1;

    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int
    {
        $id = $this->counter++;
        $this->jobs[$id] = new FailedJob(
            id: $id,
            connection: $connection,
            queue: $queue,
            payload: $payload,
            exception: (string) $exception,
            failedAt: date('Y-m-d H:i:s')
        );

        return $id;
    }

    public function all(): array
    {
        return array_values($this->jobs);
    }

    public function find(string|int $id): ?FailedJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function forget(string|int $id): bool
    {
        if (isset($this->jobs[$id])) {
            unset($this->jobs[$id]);
            return true;
        }
        return false;
    }

    public function flush(?int $hours = null): int
    {
        $count = count($this->jobs);
        $this->jobs = [];
        return $count;
    }

    public function count(): int
    {
        return count($this->jobs);
    }
}

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
