<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Drivers\NullQueueDriver;
use Jengo\Queues\Drivers\SyncQueueDriver;
use Jengo\Queues\Facades\Queue;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Traits\Queueable;
use PHPUnit\Framework\Attributes\Test;

class ManagerHelperJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
    }
}

class QueueManagerTest extends CIUnitTestCase
{
    #[Test]
    public function it_resolves_default_and_custom_connections(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);

        $defaultConn = $manager->connection();
        $this->assertInstanceOf(SyncQueueDriver::class, $defaultConn);

        $nullConn = $manager->connection('null');
        $this->assertInstanceOf(NullQueueDriver::class, $nullConn);
    }

    #[Test]
    public function it_allows_custom_driver_extensions(): void
    {
        $config = new QueueConfig();
        $config->connections['custom'] = [
            'driver' => 'custom_driver',
        ];

        $manager = new QueueManager($config);
        $manager->extend('custom_driver', function (array $cfg, string $prefix) {
            return new NullQueueDriver($cfg, $prefix);
        });

        $conn = $manager->connection('custom');
        $this->assertInstanceOf(NullQueueDriver::class, $conn);
    }

    #[Test]
    public function helper_functions_work_correctly(): void
    {
        $manager = queue();
        $this->assertInstanceOf(QueueManager::class, $manager);

        $syncDriver = queue('sync');
        $this->assertInstanceOf(SyncQueueDriver::class, $syncDriver);

        $fake = Queue::fake();
        dispatch(new ManagerHelperJob());
        $fake->assertPushed(ManagerHelperJob::class);

        dispatch_later(10, new ManagerHelperJob());
        $fake->assertPushedTimes(ManagerHelperJob::class, 2);

        Queue::unfake();
    }
}
