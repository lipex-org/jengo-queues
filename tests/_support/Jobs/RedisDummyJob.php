<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;

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
