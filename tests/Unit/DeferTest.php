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
}
