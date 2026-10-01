<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;

class DbTestJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public static int $handledCount = 0;

    public function __construct(public string $text = 'default')
    {
    }

    public function handle(): void
    {
        self::$handledCount++;
    }
}
