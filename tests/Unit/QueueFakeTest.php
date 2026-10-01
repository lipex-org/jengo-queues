<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Facades\Queue;
use Jengo\Queues\Testing\QueueFake;
use Jengo\Queues\Testing\QueueTestAssertionsTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\Jobs\TestEmailJob;
use Tests\Support\Jobs\TestNotificationJob;

class QueueFakeTest extends CIUnitTestCase
{
    use QueueTestAssertionsTrait;

    protected function tearDown(): void
    {
        $this->tearDownQueueFake();
        parent::tearDown();
    }

    #[Test]
    public function fake_intercepts_pushes(): void
    {
        $fake = $this->fakeQueue();
        $this->assertTrue(Queue::isFaking());

        $this->assertNothingPushed();

        Queue::push(new TestEmailJob('user@example.com'), '', 'emails');
        Queue::push(new TestEmailJob('admin@example.com'), '', 'emails');
        Queue::push(new TestNotificationJob('Welcome!'), '', 'notifications');

        $this->assertPushed(TestEmailJob::class);
        $this->assertPushedTimes(TestEmailJob::class, 2);
        $this->assertPushedOn('emails', TestEmailJob::class);
        $this->assertPushed(TestNotificationJob::class);
        $this->assertPushedTimes(TestNotificationJob::class, 1);

        $this->assertPushed(TestEmailJob::class, function (TestEmailJob $job, string $queue) {
            return $job->recipient === 'user@example.com' && $queue === 'emails';
        });

        $this->assertSame(2, $fake->size('emails'));
        $this->assertSame(1, $fake->size('notifications'));
    }

    #[Test]
    public function fake_fails_assertions_when_job_not_pushed(): void
    {
        $this->fakeQueue();

        $this->expectException(AssertionFailedError::class);
        $this->assertPushed(TestEmailJob::class);
    }

    #[Test]
    public function fake_pop_and_clear_work_correctly(): void
    {
        $fake = $this->fakeQueue();

        Queue::push(new TestEmailJob('first@example.com'), '', 'emails');
        Queue::push(new TestEmailJob('second@example.com'), '', 'emails');

        $job = $fake->pop('emails');
        $this->assertNotNull($job);
        $this->assertSame('emails', $job->getPayload()->queue);
        $this->assertSame(1, $fake->size('emails'));

        $cleared = $fake->clear('emails');
        $this->assertSame(1, $cleared);
        $this->assertSame(0, $fake->size('emails'));
    }
}
