<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Drivers\RedisQueueDriver;
use Jengo\Queues\Jobs\RedisJob;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class RedisDummyJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public static int $runCount = 0;

    public function __construct(public string $msg = 'hello')
    {
    }

    public function handle(): void
    {
        self::$runCount++;
    }
}

class FakeRedisClient
{
    public array $lists = [];
    public array $zsets = [];
    public array $calls = [];

    public function rPush(string $key, string $value): int
    {
        $this->calls['rPush'][] = [$key, $value];
        $this->lists[$key][] = $value;
        return count($this->lists[$key]);
    }

    public function lPop(string $key): ?string
    {
        $this->calls['lPop'][] = $key;
        return array_shift($this->lists[$key]) ?? null;
    }

    public function zAdd(string $key, float|int $score, string $member): int
    {
        $this->calls['zAdd'][] = [$key, $score, $member];
        $this->zsets[$key][$member] = $score;
        return 1;
    }

    public function zRangeByScore(string $key, mixed $min, mixed $max, array $options = []): array
    {
        return [];
    }

    public function zRem(string $key, string $member): int
    {
        $this->calls['zRem'][] = [$key, $member];
        if (isset($this->zsets[$key][$member])) {
            unset($this->zsets[$key][$member]);
            return 1;
        }
        return 0;
    }

    public function lLen(string $key): int
    {
        return count($this->lists[$key] ?? []);
    }

    public function zCard(string $key): int
    {
        return count($this->zsets[$key] ?? []);
    }

    public function del(string $key): int
    {
        unset($this->lists[$key], $this->zsets[$key]);
        return 1;
    }

    public function ping(): string
    {
        return '+PONG';
    }
}

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
