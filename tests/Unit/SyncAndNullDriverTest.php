<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Drivers\NullQueueDriver;
use Jengo\Queues\Drivers\SyncQueueDriver;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ExecutableJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public static bool $executed = false;

    public function handle(): void
    {
        self::$executed = true;
    }
}

class FailingJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public function handle(): void
    {
        throw new RuntimeException('Intentional job failure');
    }
}

class SyncAndNullDriverTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ExecutableJob::$executed = false;
    }

    #[Test]
    public function sync_driver_executes_job_immediately(): void
    {
        $driver = new SyncQueueDriver();

        $job = new ExecutableJob();
        $id = $driver->push($job);

        $this->assertIsString($id);
        $this->assertTrue(ExecutableJob::$executed);
        $this->assertSame(0, $driver->size());
    }

    #[Test]
    public function sync_driver_rethrows_exception_on_failure(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Intentional job failure');

        $driver = new SyncQueueDriver();
        $driver->push(new FailingJob());
    }

    #[Test]
    public function sync_driver_pop_returns_null(): void
    {
        $driver = new SyncQueueDriver();
        $this->assertNull($driver->pop());
    }

    #[Test]
    public function null_driver_discards_jobs(): void
    {
        $driver = new NullQueueDriver();

        $job = new ExecutableJob();
        $id = $driver->push($job);

        $this->assertIsString($id);
        $this->assertFalse(ExecutableJob::$executed);
        $this->assertSame(0, $driver->size());
        $this->assertNull($driver->pop());
        $this->assertSame(0, $driver->clear());
    }

    #[Test]
    public function drivers_report_status(): void
    {
        $sync = new SyncQueueDriver();
        $this->assertSame('sync', $sync->status()['driver']);
        $this->assertSame('ok', $sync->status()['status']);

        $null = new NullQueueDriver();
        $this->assertSame('null', $null->status()['driver']);
        $this->assertSame('ok', $null->status()['status']);
    }
}
