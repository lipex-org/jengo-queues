<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;
use RuntimeException;

class WorkerFailJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public static int $attemptCount = 0;

    public function handle(): void
    {
        self::$attemptCount++;
        throw new RuntimeException('Job exploded');
    }
}
