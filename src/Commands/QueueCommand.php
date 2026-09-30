<?php

declare(strict_types=1);

namespace Jengo\Queues\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

class QueueCommand extends AbstractMasterCommand
{
    protected $group       = 'Jengo';
    protected $name        = 'jengo:queue';
    protected $description = 'Queue and worker management (work, listen, failed, clear, status).';
    protected $usage       = 'jengo:queue <variant> [options]';

    protected string $variantPath = 'Commands/Variants';
}
