<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Entities\JobPayload;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Jobs\SampleTestJob;

class JobPayloadTest extends CIUnitTestCase
{
    #[Test]
    public function it_creates_payload_from_job_instance(): void
    {
        $job = (new SampleTestJob('world', 100))
            ->onQueue('emails')
            ->delay(15)
            ->tries(5)
            ->backoff(30)
            ->timeout(120);

        $payload = JobPayload::fromJob($job, 'emails', 15);

        $this->assertSame(SampleTestJob::class, $payload->displayName);
        $this->assertSame('emails', $payload->queue);
        $this->assertSame(15, $payload->delay);
        $this->assertSame(5, $payload->maxTries);
        $this->assertSame(30, $payload->backoff);
        $this->assertSame(120, $payload->timeout);
        $this->assertSame(0, $payload->attempts);

        $resolved = $payload->resolveInstance();
        $this->assertInstanceOf(SampleTestJob::class, $resolved);
        $this->assertSame('world', $resolved->param1);
        $this->assertSame(100, $resolved->param2);
    }

    #[Test]
    public function it_serializes_and_deserializes_payload(): void
    {
        $job = new SampleTestJob('foo', 99);
        $payload = JobPayload::fromJob($job, 'default');

        $json = json_encode($payload->jsonSerialize());
        $array = json_decode($json, true);

        $restored = JobPayload::fromArray($array);

        $this->assertSame($payload->id, $restored->id);
        $this->assertSame($payload->displayName, $restored->displayName);
        $this->assertSame($payload->queue, $restored->queue);

        $instance = $restored->resolveInstance();
        $this->assertInstanceOf(SampleTestJob::class, $instance);
        $this->assertSame('foo', $instance->param1);
        $this->assertSame(99, $instance->param2);
    }
}
