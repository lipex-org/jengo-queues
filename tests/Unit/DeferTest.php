<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

final class DeferTest extends CIUnitTestCase
{
    public static bool $executed = false;
    public static mixed $capturedArg = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::$executed = false;
        self::$capturedArg = null;
    }

    public function testDeferClosureExecutesImmediatelyOnSyncDriver(): void
    {
        // Using sync queue driver (default)
        $jobId = defer(function ($value) {
            \Tests\Unit\DeferTest::$executed = true;
            \Tests\Unit\DeferTest::$capturedArg = $value;
        }, 'test_param');

        $this->assertNotEmpty($jobId);
        $this->assertTrue(self::$executed);
        $this->assertSame('test_param', self::$capturedArg);
    }

    public function testDeferViaQueueFacade(): void
    {
        $jobId = Queue::defer(function () {
            \Tests\Unit\DeferTest::$executed = true;
        });

        $this->assertNotEmpty($jobId);
        $this->assertTrue(self::$executed);
    }

    public function testDeferCallableArray(): void
    {
        $jobId = defer([self::class, 'handleCallback'], 'hello');

        $this->assertNotEmpty($jobId);
        $this->assertTrue(self::$executed);
        $this->assertSame('hello', self::$capturedArg);
    }

    public static function handleCallback(string $param): void
    {
        self::$executed = true;
        self::$capturedArg = $param;
    }

    public static mixed $capturedService = null;

    public function testDeferWithDependencyInjection(): void
    {
        // Bind or resolve a dummy service if Jengo Container exists
        if (! class_exists(\Jengo\Base\Container\Container::class)) {
            $this->markTestSkipped('Jengo Base Container is not installed in this environment.');
        }

        $container = \Jengo\Base\Container\Container::getInstance();
        $dummyService = new DummyService();
        $container->instance(DummyService::class, $dummyService);

        self::$capturedService = null;
        self::$capturedArg = null;

        defer(function (DummyService $svc, string $name) {
            \Tests\Unit\DeferTest::$capturedService = $svc;
            \Tests\Unit\DeferTest::$capturedArg = $name;
        }, 'Jengo');

        $this->assertNotNull(self::$capturedService);
        $this->assertInstanceOf(DummyService::class, self::$capturedService);
        $this->assertSame('Jengo', self::$capturedArg);
    }

    public function testDeferWithServiceAttribute(): void
    {
        if (! class_exists(\Jengo\Base\Container\Container::class)) {
            $this->markTestSkipped('Jengo Base Container is not installed in this environment.');
        }

        self::$capturedService = null;
        self::$capturedArg = null;

        defer(function (#[\Jengo\Base\Container\Attributes\Service('logger')] $logger, string $action) {
            \Tests\Unit\DeferTest::$capturedService = $logger;
            \Tests\Unit\DeferTest::$capturedArg = $action;
        }, 'completed');

        $this->assertNotNull(self::$capturedService);
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, self::$capturedService);
        $this->assertSame('completed', self::$capturedArg);
    }

    public function testJobHandleDependencyInjection(): void
    {
        if (! class_exists(\Jengo\Base\Container\Container::class)) {
            $this->markTestSkipped('Jengo Base Container is not installed in this environment.');
        }

        self::$capturedService = null;

        $job = new JobWithDi('job_payload_value');
        dispatch($job);

        $this->assertNotNull(self::$capturedService);
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, self::$capturedService);
    }

    public function testDeferLaterDispatches(): void
    {
        $jobId = defer_later(10, function ($msg) {
            \Tests\Unit\DeferTest::$capturedArg = $msg;
        }, 'delayed_hello');

        $this->assertNotEmpty($jobId);
    }
}

class DummyService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

class JobWithDi implements \Jengo\Queues\Contracts\ShouldQueue
{
    use \Jengo\Queues\Traits\Queueable;

    public function __construct(public string $item)
    {
    }

    public function handle(#[\Jengo\Base\Container\Attributes\Service('logger')] $logger): void
    {
        \Tests\Unit\DeferTest::$capturedService = $logger;
    }
}

