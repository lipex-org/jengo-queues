<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;
use Jengo\Queues\Commands\QueueCommand;
use Jengo\Queues\Commands\Variants\ClearVariant;
use Jengo\Queues\Commands\Variants\FailedVariant;
use Jengo\Queues\Commands\Variants\ListenVariant;
use Jengo\Queues\Commands\Variants\StatusVariant;
use Jengo\Queues\Commands\Variants\WorkVariant;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Facades\Queue;
use Jengo\Queues\Installers\QueueInstaller;
use Jengo\Queues\Support\FailedJobManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Jobs\SampleTestJob;
use Tests\Support\Jobs\WorkerFailJob;
use Tests\Support\Jobs\WorkerSuccessJob;
use Tests\Support\Providers\MockFailedJobProvider;

class QueueCommandTest extends CIUnitTestCase
{
    private MockInputOutput $io;

    protected function setUp(): void
    {
        parent::setUp();
        $this->io = new MockInputOutput();
        CLI::setInputOutput($this->io);
    }

    protected function tearDown(): void
    {
        Queue::unfake();
        \Config\Services::reset(true);
        CLI::resetInputOutput();
        parent::tearDown();
    }

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
        $this->assertNotEmpty(QueueInstaller::reasonForSkipping());

        $installer = new QueueInstaller();
        $this->assertIsBool($installer->shouldRun());
    }

    #[Test]
    public function status_variant_executes_and_renders_table(): void
    {
        $fake = Queue::fake();
        $variant = new StatusVariant();
        $this->assertIsArray($variant->arguments());
        $this->assertIsArray($variant->options());

        $variant->run(['default']);
        $out = $this->io->getOutput();
        $this->assertStringContainsString('Checking queue driver status for [default]', $out);
    }

    #[Test]
    public function clear_variant_prompts_and_cancels_when_declined(): void
    {
        $fake = Queue::fake();
        $fake->push(new SampleTestJob(), '', 'default');
        $this->assertSame(1, $fake->size('default'));

        $variant = new ClearVariant();
        $this->io->setInputs(['n']);
        $variant->run(['sync']);

        $out = $this->io->getOutput();
        $this->assertStringContainsString('Clear operation cancelled', $out);
        $this->assertSame(1, $fake->size('default'));
    }

    #[Test]
    public function clear_variant_clears_queue_when_confirmed(): void
    {
        $fake = Queue::fake();
        $fake->push(new SampleTestJob(), '', 'default');
        $this->assertSame(1, $fake->size('default'));

        $variant = new ClearVariant();
        $this->assertArrayHasKey('connection', $variant->arguments());
        $this->assertArrayHasKey('--force', $variant->options());
        $this->io->setInputs(['y']);
        $variant->run(['sync']);

        $out = $this->io->getOutput();
        $this->assertStringContainsString('Cleared 1 jobs from the [default] queue', $out);
        $this->assertSame(0, $fake->size('default'));
    }

    #[Test]
    public function work_variant_processes_jobs_with_callbacks(): void
    {
        $fake = Queue::fake();
        WorkerSuccessJob::$runCount = 0;
        $fake->push(new WorkerSuccessJob(), '', 'default');
        $fake->push(new WorkerFailJob(), '', 'default');

        $worker = \Config\Services::queueWorker();
        $variant = new WorkVariant();
        $this->assertArrayHasKey('connection', $variant->arguments());
        $this->assertArrayHasKey('--max-jobs', $variant->options());

        $worker->onProcessing(function ($job, $conn) {
            CLI::write(sprintf('[%s] Processing: %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'yellow');
        });
        $worker->onProcessed(function ($job, $conn) {
            CLI::write(sprintf('[%s] Processed:  %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'green');
        });
        $worker->onFailed(function ($job, $e, $final) {
            CLI::write(sprintf('[%s] Failed:    %s - %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName, $e->getMessage()), 'red');
        });

        $worker->daemon(
            connectionName: 'sync',
            queue: 'default',
            delay: 0,
            sleep: 0,
            maxTries: 3,
            memory: 128,
            timeout: 60,
            maxJobs: 2
        );

        $out = $this->io->getOutput();
        $this->assertStringContainsString('Processing: Tests\Support\Jobs\WorkerSuccessJob', $out);
        $this->assertSame(1, WorkerSuccessJob::$runCount);
    }

    #[Test]
    public function listen_variant_runs_and_processes_jobs(): void
    {
        $fake = Queue::fake();
        WorkerSuccessJob::$runCount = 0;
        $fake->push(new WorkerSuccessJob(), '', 'default');

        $worker = \Config\Services::queueWorker();
        $variant = new ListenVariant();
        $this->assertArrayHasKey('connection', $variant->arguments());
        $this->assertArrayHasKey('--queue', $variant->options());

        $worker->onProcessing(function ($job, $conn) {
            CLI::write(sprintf('[%s] Processing: %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'yellow');
        });
        $worker->onProcessed(function ($job, $conn) {
            CLI::write(sprintf('[%s] Processed:  %s', date('Y-m-d H:i:s'), $job->getPayload()->displayName), 'green');
        });

        $worker->daemon(
            connectionName: 'sync',
            queue: 'default',
            delay: 0,
            sleep: 0,
            maxTries: 3,
            maxJobs: 1
        );

        $out = $this->io->getOutput();
        $this->assertStringContainsString('Processing: Tests\Support\Jobs\WorkerSuccessJob', $out);
        $this->assertSame(1, WorkerSuccessJob::$runCount);
    }

    #[Test]
    public function queue_installer_executes_successfully(): void
    {
        $installer = new QueueInstaller();
        $this->assertIsBool($installer->shouldRun());
        $installer->install();
        $this->assertSame(1, $installer->runs);
    }

    #[Test]
    public function failed_variant_actions(): void
    {
        $mockProvider = new MockFailedJobProvider();
        $failedManager = new FailedJobManager($mockProvider);
        \Config\Services::injectMock('queueFailedJobs', $failedManager);

        $variant = new FailedVariant();
        $this->assertArrayHasKey('action', $variant->arguments());
        $this->assertArrayHasKey('--hours', $variant->options());

        // 1. List with no failed jobs
        $failedManager->flush();
        $variant->run(['list']);
        $outListEmpty = $this->io->getOutput();
        $this->assertStringContainsString('No failed jobs found', $outListEmpty);

        // 2. Count action
        $variant->run(['count']);
        $outCount = $this->io->getOutput();
        $this->assertStringContainsString('Total failed jobs: 0', $outCount);

        // 3. Log dummy failed job and list
        $payload = json_encode(JobPayload::create(new SampleTestJob())->jsonSerialize());
        $id = $failedManager->log('sync', 'default', $payload, new \RuntimeException('Failed for CLI'));
        $this->assertSame(1, $failedManager->count());

        $variant->run(['list']);
        $outList = $this->io->getOutput();
        $this->assertStringContainsString('SampleTestJob', $outList);

        // 4. Retry specific job
        $variant->run(['retry', (string) $id]);
        $outRetry = $this->io->getOutput();
        $this->assertStringContainsString("Failed job [{$id}] pushed back onto the queue", $outRetry);

        // 5. Retry missing job
        $variant->run(['retry', '99999']);
        $outRetryMiss = $this->io->getOutput();
        $this->assertStringContainsString('not found', $outRetryMiss);

        // 6. Retry all
        $id2 = $failedManager->log('sync', 'default', $payload, new \RuntimeException('Another Fail'));
        $variant->run(['retry', 'all']);
        $outRetryAll = $this->io->getOutput();
        $this->assertStringContainsString('Retried 1 failed job(s)', $outRetryAll);

        // 7. Forget missing and valid
        $id3 = $failedManager->log('sync', 'default', $payload, new \RuntimeException('Forget Me'));
        $variant->run(['forget']); // Missing ID argument
        $outForgetNoId = $this->io->getOutput();
        $this->assertStringContainsString('Please provide a failed job ID', $outForgetNoId);

        $variant->run(['forget', (string) $id3]);
        $outForget = $this->io->getOutput();
        $this->assertStringContainsString("Failed job [{$id3}] deleted successfully", $outForget);

        $variant->run(['forget', '99999']);
        $outForgetMiss = $this->io->getOutput();
        $this->assertStringContainsString('not found', $outForgetMiss);

        // 8. Flush
        $failedManager->log('sync', 'default', $payload, new \RuntimeException('Flush 1'));
        $failedManager->log('sync', 'default', $payload, new \RuntimeException('Flush 2'));
        $variant->run(['flush']);
        $outFlush = $this->io->getOutput();
        $this->assertStringContainsString('Flushed 2 failed job(s)', $outFlush);

        // 9. Unknown action
        $variant->run(['unknown_action']);
        $outUnknown = $this->io->getOutput();
        $this->assertStringContainsString('Unknown action [unknown_action]', $outUnknown);
    }
}
