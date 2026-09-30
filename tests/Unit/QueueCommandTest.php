<?php

declare(strict_types=1);

namespace Jengo\Queues\Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Commands\QueueCommand;
use Jengo\Queues\Commands\Variants\ClearVariant;
use Jengo\Queues\Commands\Variants\FailedVariant;
use Jengo\Queues\Commands\Variants\ListenVariant;
use Jengo\Queues\Commands\Variants\StatusVariant;
use Jengo\Queues\Commands\Variants\WorkVariant;
use Jengo\Queues\Installers\QueueInstaller;
use PHPUnit\Framework\Attributes\Test;

class QueueCommandTest extends CIUnitTestCase
{
    #[Test]
    public function command_and_variants_have_correct_metadata(): void
    {
        $this->assertSame('work', WorkVariant::name());
        $this->assertNotEmpty(WorkVariant::description());

        $this->assertSame('listen', ListenVariant::name());
        $this->assertNotEmpty(ListenVariant::description());

        $this->assertSame('failed', FailedVariant::name());
        $this->assertNotEmpty(FailedVariant::description());

        $this->assertSame('clear', ClearVariant::name());
        $this->assertNotEmpty(ClearVariant::description());

        $this->assertSame('status', StatusVariant::name());
        $this->assertNotEmpty(StatusVariant::description());

        $this->assertSame('queue', QueueInstaller::name());
        $this->assertNotEmpty(QueueInstaller::description());
    }
}
