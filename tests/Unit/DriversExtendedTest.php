<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Drivers\DatabaseQueueDriver;
use Jengo\Queues\Drivers\NullQueueDriver;
use Jengo\Queues\Drivers\RedisQueueDriver;
use Jengo\Queues\Drivers\SyncQueueDriver;
use Jengo\Queues\Entities\FailedJob;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Installers\QueueInstaller;
use Jengo\Queues\Jobs\DatabaseJob;
use Jengo\Queues\Jobs\RedisJob;
use Jengo\Queues\Support\DatabaseFailedJobProvider;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Jobs\DbTestJob;
use Tests\Support\Jobs\RedisDummyJob;
use Tests\Support\Jobs\SampleTestJob;
use Tests\Support\Redis\FakeRedisClient;

class DriversExtendedTest extends CIUnitTestCase
{
    #[Test]
    public function null_driver_extended_methods(): void
    {
        $driver = new NullQueueDriver([], 'jengo_');
        $id = $driver->later(10, new SampleTestJob());
        $this->assertIsString($id);
        $rawId = $driver->pushRaw('{"foo":"bar"}');
        $this->assertIsString($rawId);
    }

    #[Test]
    public function sync_driver_later_and_status(): void
    {
        $driver = new SyncQueueDriver();
        $id = $driver->later(5, new SampleTestJob());
        $this->assertIsString($id);
    }

    #[Test]
    public function redis_driver_extended_coverage(): void
    {
        $client = new FakeRedisClient();
        $driver = new RedisQueueDriver([
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => 'secret',
            'database' => 1,
            'timeout' => 2.0,
            'retryAfter' => 60,
        ], 'jengo_');
        $driver->setRedis($client);

        // Raw later
        $driver->later(30, new RedisDummyJob('later-redis'), '', 'default');
        $this->assertSame(1, $driver->size('default'));

        // Pop
        $popped = $driver->pop('default');
        $this->assertInstanceOf(RedisJob::class, $popped);

        // RedisJob release with delay
        $popped->release(15);
        $this->assertSame(1, $driver->size('default'));

        // RedisJob release without delay
        $popped2 = $driver->pop('default');
        if ($popped2) {
            $popped2->release(0);
        }

        // Redis pushRaw
        $rawPayload = json_encode(JobPayload::create(new RedisDummyJob('raw-redis'))->jsonSerialize());
        $driver->pushRaw($rawPayload, 'default');
        $this->assertGreaterThanOrEqual(1, $driver->size('default'));

        // Status
        $status = $driver->status();
        $this->assertSame('redis', $status['driver']);
        $this->assertSame('ok', $status['status']);

        // Pop and Fire
        $popped4 = $driver->pop('default');
        if ($popped4) {
            $this->assertInstanceOf(RedisJob::class, $popped4);
            $popped4->fire();
            $popped4->delete();
        }

        // Clear
        $cleared = $driver->clear('default');
        $this->assertGreaterThanOrEqual(0, $cleared);
    }

    #[Test]
    public function database_driver_and_job_extended(): void
    {
        $forge = \Config\Database::forge('tests');
        $forge->dropTable('queue_jobs', true);
        $forge->dropTable('queue_failed_jobs', true);

        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true],
            'queue'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'default'],
            'payload'      => ['type' => 'TEXT'],
            'attempts'     => ['type' => 'INTEGER', 'default' => 0],
            'reserved_at'  => ['type' => 'INTEGER', 'null' => true],
            'available_at' => ['type' => 'INTEGER'],
            'created_at'   => ['type' => 'INTEGER'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('queue_jobs', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'connection' => ['type' => 'VARCHAR', 'constraint' => 255],
            'queue'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'payload'    => ['type' => 'TEXT'],
            'exception'  => ['type' => 'TEXT'],
            'failed_at'  => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('queue_failed_jobs', true);

        $db = \Config\Database::connect('tests');
        $driver = (new DatabaseQueueDriver(['DBGroup' => 'tests']))->setConnection($db);

        $job = new DbTestJob('test-job');
        $driver->push($job, '', 'default');
        $this->assertSame(1, $driver->size('default'));

        $driver->later(60, new DbTestJob('delayed-db-job'), '', 'default');
        $this->assertSame(2, $driver->size('default'));

        $rawPayload = json_encode(JobPayload::create(new DbTestJob('raw-db-job'))->jsonSerialize());
        $driver->pushRaw($rawPayload, 'default');
        $this->assertSame(3, $driver->size('default'));

        // Status check
        $status = $driver->status();
        $this->assertSame('database', $status['driver']);
        $this->assertSame('ok', $status['status']);

        // Pop and release
        $popped = $driver->pop('default');
        $this->assertInstanceOf(DatabaseJob::class, $popped);
        $this->assertSame(1, $popped->attempts());
        $popped->release(10);

        // Pop and fail
        $popped2 = $driver->pop('default');
        if ($popped2) {
            $popped2->fail(new \RuntimeException('DB Fail'));
        }

        // Test clear
        $clearedCount = $driver->clear('default');
        $this->assertGreaterThanOrEqual(0, $clearedCount);
        $this->assertSame(0, $driver->size('default'));

        // DatabaseFailedJobProvider flush with hours
        $provider = (new DatabaseFailedJobProvider(['DBGroup' => 'tests']))->setConnection($db);
        $provider->log('database', 'default', '{"data":"test"}', new \RuntimeException('Err'));
        $this->assertSame(1, $provider->count());
        $provider->flush(1);
    }

    #[Test]
    public function failed_job_entity_serialization(): void
    {
        $row = [
            'id' => 10,
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'Stack trace',
            'failed_at' => '2026-10-01 10:00:00',
        ];

        $entity = FailedJob::fromArray($row);
        $this->assertSame(10, $entity->id);
        $this->assertSame('redis', $entity->connection);
        $this->assertSame('TestJob', $entity->getJobPayload()->displayName);

        $serialized = $entity->jsonSerialize();
        $this->assertSame(10, $serialized['id']);
        $this->assertSame('emails', $serialized['queue']);
    }

    #[Test]
    public function queue_manager_default_driver_mutator(): void
    {
        $config = new QueueConfig();
        $manager = new QueueManager($config);

        $this->assertSame('sync', $manager->getDefaultDriver());
        $manager->setDefaultDriver('null');
        $this->assertSame('null', $manager->getDefaultDriver());
    }

    #[Test]
    public function queue_services_registration(): void
    {
        $queues = \Jengo\Queues\Config\Services::queues(null, false);
        $this->assertInstanceOf(QueueManager::class, $queues);

        $worker = \Jengo\Queues\Config\Services::queueWorker($queues, null, false);
        $this->assertInstanceOf(\Jengo\Queues\Support\Worker::class, $worker);

        $failed = \Jengo\Queues\Config\Services::queueFailedJobs(null, false);
        $this->assertInstanceOf(FailedJobManager::class, $failed);
    }
}
