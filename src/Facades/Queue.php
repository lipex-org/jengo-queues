<?php

declare(strict_types=1);

namespace Jengo\Queues\Facades;

use Config\Services;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Contracts\QueueDriverInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Testing\QueueFake;

/**
 * @method static QueueDriverInterface connection(?string $name = null)
 * @method static string|int push(object|string $job, mixed $data = '', ?string $queue = null)
 * @method static string|int later(int $delay, object|string $job, mixed $data = '', ?string $queue = null)
 * @method static JobInterface|null pop(?string $queue = null)
 * @method static int size(?string $queue = null)
 * @method static int clear(?string $queue = null)
 * @method static array status()
 * @method static QueueFake fake()
 * @method static bool isFaking()
 * @method static void unfake()
 */
class Queue
{
    public static function getManager(): QueueManager
    {
        return Services::queues();
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::getManager()->$method(...$arguments);
    }
}
