<?php

declare(strict_types=1);

namespace Jengo\Queues\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

class StatusVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'status';
    }

    public static function description(): string
    {
        return 'Check the connectivity, driver status, and pending jobs.';
    }

    public function arguments(): array
    {
        return [
            'connection' => 'The name of the queue connection to inspect (default: default)',
        ];
    }

    public function options(): array
    {
        return [];
    }

    public function run(array $params): void
    {
        $connection = $params[0] ?? (string) (CLI::getOption('connection') ?? 'default');
        $manager = Services::queues();
        $driver = $manager->connection($connection);

        CLI::write("Checking queue driver status for [{$connection}]...", 'yellow');

        $status = $driver->status();

        CLI::table(
            array_map(
                static fn ($k, $v) => [$k, is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)],
                array_keys($status),
                array_values($status)
            ),
            ['Metric / Setting', 'Value']
        );
    }
}
