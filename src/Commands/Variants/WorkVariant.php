<?php

declare(strict_types=1);

namespace Jengo\Queues\Commands\Variants;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Queues\Contracts\JobInterface;
use Throwable;

class WorkVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'work';
    }

    public static function description(): string
    {
        return 'Start processing jobs on the queue as a daemon.';
    }

    public function arguments(): array
    {
        return [
            'connection' => 'The name of the queue connection to work (default from config)',
        ];
    }

    public function options(): array
    {
        return [
            '--queue'   => 'The names of the queues to work, comma-separated (default: default)',
            '--delay'   => 'The number of seconds to delay failed jobs before retrying (default: 0)',
            '--sleep'   => 'Number of seconds to sleep when no job is available (default: 3)',
            '--tries'   => 'Number of times to attempt a job before failing it (default: 3)',
            '--memory'  => 'The memory limit in megabytes (default: 128)',
            '--timeout' => 'The number of seconds a child process can run (default: 60)',
            '--max-jobs'=> 'The number of jobs to process before stopping (default: 0 / unlimited)',
        ];
    }

    public function run(array $params): void
    {
        $connection = $params[0] ?? (string) (CLI::getOption('connection') ?? 'default');
        $queue      = (string) (CLI::getOption('queue') ?? 'default');
        $delay      = (int) (CLI::getOption('delay') ?? 0);
        $sleep      = (int) (CLI::getOption('sleep') ?? 3);
        $tries      = (int) (CLI::getOption('tries') ?? 3);
        $memory     = (int) (CLI::getOption('memory') ?? 128);
        $timeout    = (int) (CLI::getOption('timeout') ?? 60);
        $maxJobs    = (int) (CLI::getOption('max-jobs') ?? 0);

        CLI::write("Starting worker on [{$connection}] queue [{$queue}]...", 'green');

        $worker = Services::queueWorker();

        $worker->onProcessing(function (JobInterface $job, string $conn) {
            CLI::write(sprintf('[%s] Processing: %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'yellow');
        });

        $worker->onProcessed(function (JobInterface $job, string $conn) {
            CLI::write(sprintf('[%s] Processed:  %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'green');
        });

        $worker->onFailed(function (JobInterface $job, Throwable $e, bool $final) {
            $msg = sprintf('[%s] %s:     %s - %s', date('Y-m-d H:i:s'), $final ? 'Failed' : 'Retrying', $job->getPayload()->displayName, $e->getMessage());
            CLI::write($msg, 'red');
        });

        $worker->daemon(
            connectionName: $connection,
            queue: $queue,
            delay: $delay,
            sleep: $sleep,
            maxTries: $tries,
            memory: $memory,
            timeout: $timeout,
            maxJobs: $maxJobs
        );
    }
}
