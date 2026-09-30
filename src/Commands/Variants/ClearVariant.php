<?php

declare(strict_types=1);

namespace Jengo\Queues\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

class ClearVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'clear';
    }

    public static function description(): string
    {
        return 'Delete all pending jobs from a specified queue.';
    }

    public function arguments(): array
    {
        return [
            'connection' => 'The name of the queue connection to clear (default from config)',
        ];
    }

    public function options(): array
    {
        return [
            '--queue' => 'The name of the queue to clear (default: default)',
            '--force' => 'Force the operation without prompting',
        ];
    }

    public function run(array $params): void
    {
        $connection = $params[0] ?? (string) (CLI::getOption('connection') ?? 'default');
        $queue      = (string) (CLI::getOption('queue') ?? 'default');
        $force      = (bool) CLI::getOption('force');

        if (!$force && CLI::isCLI()) {
            $confirm = CLI::prompt("Are you sure you want to clear the [{$queue}] queue on [{$connection}]?", ['y', 'n'], 'n');
            if ($confirm !== 'y') {
                CLI::write('Clear operation cancelled.', 'yellow');
                return;
            }
        }

        $manager = Services::queues();
        $count = $manager->connection($connection)->clear($queue);

        CLI::write("Cleared {$count} jobs from the [{$queue}] queue.", 'green');
    }
}
