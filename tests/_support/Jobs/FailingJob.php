<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use Jengo\Queues\Contracts\ShouldQueue;
use Jengo\Queues\Traits\InteractsWithQueue;
use Jengo\Queues\Traits\Queueable;
use RuntimeException;

class FailingJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public function handle(): void
    {
        throw new RuntimeException('Intentional job failure');
    }
}
