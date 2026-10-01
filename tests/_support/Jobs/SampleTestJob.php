<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;

class SampleTestJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public function __construct(public string $param1 = 'hello', public int $param2 = 42)
    {
    }

    public function handle(): void
    {
        // Sample execution
    }
}
