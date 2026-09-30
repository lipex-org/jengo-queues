<?php

declare(strict_types=1);

namespace Jengo\Queues\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

class FailedVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'failed';
    }

    public static function description(): string
    {
        return 'List, retry, or forget failed queue jobs.';
    }

    public function arguments(): array
    {
        return [
            'action' => 'Action to perform: list, retry, forget, flush, count (default: list)',
            'id'     => 'ID of the failed job for retry or forget',
        ];
    }

    public function options(): array
    {
        return [
            '--queue' => 'Filter by queue name (for list or retry all)',
            '--hours' => 'Hours of failed jobs to retain (for flush)',
        ];
    }

    public function run(array $params): void
    {
        $action = $params[0] ?? 'list';
        $id     = $params[1] ?? null;
        $failed = Services::queueFailedJobs();

        switch ($action) {
            case 'list':
                $jobs = $failed->all();
                if (empty($jobs)) {
                    CLI::write('No failed jobs found.', 'green');
                    return;
                }

                $tbody = array_map(function ($job) {
                    $payload = $job->getJobPayload();
                    return [
                        $job->id,
                        $job->connection,
                        $job->queue,
                        $payload->displayName,
                        $job->failedAt,
                    ];
                }, $jobs);

                CLI::table($tbody, ['ID', 'Connection', 'Queue', 'Class', 'Failed At']);
                break;

            case 'retry':
                if ($id === 'all' || $id === null) {
                    $queue = CLI::getOption('queue');
                    $retried = $failed->retryAll($queue ? (string) $queue : null);
                    CLI::write("Retried {$retried} failed job(s).", 'green');
                } else {
                    if ($failed->retry($id)) {
                        CLI::write("Failed job [{$id}] pushed back onto the queue for retry.", 'green');
                    } else {
                        CLI::error("Failed job [{$id}] not found.");
                    }
                }
                break;

            case 'forget':
                if ($id === null) {
                    CLI::error('Please provide a failed job ID to forget (e.g. jengo:queue failed forget 1).');
                    return;
                }

                if ($failed->forget($id)) {
                    CLI::write("Failed job [{$id}] deleted successfully.", 'green');
                } else {
                    CLI::error("Failed job [{$id}] not found.");
                }
                break;

            case 'flush':
                $hours = CLI::getOption('hours');
                $count = $failed->flush($hours !== null ? (int) $hours : null);
                CLI::write("Flushed {$count} failed job(s).", 'green');
                break;

            case 'count':
                $count = $failed->count();
                CLI::write("Total failed jobs: {$count}", 'yellow');
                break;

            default:
                CLI::error("Unknown action [{$action}]. Valid actions are list, retry, forget, flush, count.");
                break;
        }
    }
}
