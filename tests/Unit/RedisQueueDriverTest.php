<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Drivers\RedisQueueDriver;
use Jengo\Queues\Jobs\RedisJob;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Jobs\RedisDummyJob;
use Tests\Support\Redis\FakeRedisClient;

class RedisQueueDriverTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RedisDummyJob::$runCount = 0;
    }

    #[Test]
    public function it_interacts_with_redis_client(): void
    {
        $client = new FakeRedisClient();
        $driver = new RedisQueueDriver();
        $driver->setRedis($client);

        $id = $driver->push(new RedisDummyJob('hi'), '', 'default');
        $this->assertIsString($id);
        $this->assertCount(1, $client->lists['queues:default']);
        $this->assertSame(1, $driver->size('default'));
    }

    #[Test]
    public function it_pops_and_fires_redis_job(): void
    {
        $client = new FakeRedisClient();
        $driver = new RedisQueueDriver();
        $driver->setRedis($client);

        $jobInstance = new RedisDummyJob('redis-fire');
        $driver->push($jobInstance, '', 'default');

        $popped = $driver->pop('default');

        $this->assertInstanceOf(RedisJob::class, $popped);
        $this->assertSame(1, $popped->attempts());

        $popped->fire();
        $this->assertSame(1, RedisDummyJob::$runCount);

        $popped->delete();
        $this->assertSame(0, $driver->size('default'));
    }

    #[Test]
    public function it_delays_and_clears_redis_jobs(): void
    {
        $client = new FakeRedisClient();
        $driver = new RedisQueueDriver();
        $driver->setRedis($client);

        $driver->later(60, new RedisDummyJob('delayed'), '', 'default');
        $this->assertSame(1, $driver->size('default'));

        $cleared = $driver->clear('default');
        $this->assertSame(1, $cleared);
        $this->assertSame(0, $driver->size('default'));
    }

    #[Test]
    public function it_reports_status(): void
    {
        $client = new FakeRedisClient();
        $driver = new RedisQueueDriver();
        $driver->setRedis($client);

        $status = $driver->status();
        $this->assertSame('redis', $status['driver']);
        $this->assertSame('ok', $status['status']);
    }
}
