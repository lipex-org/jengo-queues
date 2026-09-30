<?php

declare(strict_types=1);

namespace Jengo\Queues\Entities;

use JsonSerializable;

class JobPayload implements JsonSerializable
{
    public string $id;

    /**
     * @param string $uuid Unique job execution ID
     * @param string $displayName Class or job name
     * @param string $job Serialized job command string
     * @param int $attempts Current attempt count
     * @param int $maxTries Maximum allowed attempts
     * @param int $timeout Job timeout in seconds
     * @param int $backoff Retry delay / backoff in seconds
     * @param int $createdAt Timestamp created
     * @param int $availableAt Timestamp when eligible for processing
     * @param int $delay Delay in seconds
     * @param string $queue Target queue name
     * @param array<string, mixed> $data Extra context payload
     */
    public function __construct(
        public string $uuid,
        public string $displayName,
        public string $job,
        public int $attempts = 0,
        public int $maxTries = 3,
        public int $timeout = 60,
        public int $backoff = 0,
        public int $createdAt = 0,
        public int $availableAt = 0,
        public int $delay = 0,
        public string $queue = 'default',
        public array $data = []
    ) {
        $this->id = $this->uuid;

        if ($this->createdAt === 0) {
            $this->createdAt = time();
        }
        if ($this->availableAt === 0) {
            $this->availableAt = $this->createdAt + max(0, $this->delay);
        }
    }

    public static function create(object|string $command, ?string $queue = null, int $delay = 0): self
    {
        $uuid = bin2hex(random_bytes(16));
        $displayName = is_object($command) ? get_class($command) : $command;
        $serialized = is_object($command) ? serialize($command) : serialize(new $command());

        $maxTries = is_object($command) && property_exists($command, 'tries') ? (int) $command->tries : 3;
        $timeout = is_object($command) && property_exists($command, 'timeout') ? (int) $command->timeout : 60;
        $backoff = is_object($command) && property_exists($command, 'backoff') ? (int) $command->backoff : 0;
        $jobDelay = is_object($command) && property_exists($command, 'delay') && $command->delay > 0 ? (int) $command->delay : $delay;
        $targetQueue = $queue ?? (is_object($command) && property_exists($command, 'queue') && $command->queue ? (string) $command->queue : 'default');

        $now = time();

        return new self(
            uuid: $uuid,
            displayName: $displayName,
            job: $serialized,
            attempts: 0,
            maxTries: $maxTries,
            timeout: $timeout,
            backoff: $backoff,
            createdAt: $now,
            availableAt: $now + max(0, $jobDelay),
            delay: $jobDelay,
            queue: $targetQueue,
            data: ['queue' => $targetQueue]
        );
    }

    public static function fromJob(object|string $job, ?string $queue = null, int $delay = 0): self
    {
        return static::create($job, $queue, $delay);
    }

    public function jsonSerialize(): array
    {
        return [
            'id'           => $this->id,
            'uuid'         => $this->uuid,
            'displayName'  => $this->displayName,
            'job'          => $this->job,
            'attempts'     => $this->attempts,
            'maxTries'     => $this->maxTries,
            'timeout'      => $this->timeout,
            'backoff'      => $this->backoff,
            'createdAt'    => $this->createdAt,
            'availableAt'  => $this->availableAt,
            'delay'        => $this->delay,
            'queue'        => $this->queue,
            'data'         => $this->data,
        ];
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            uuid: (string) ($payload['uuid'] ?? $payload['id'] ?? bin2hex(random_bytes(16))),
            displayName: (string) ($payload['displayName'] ?? 'UnknownJob'),
            job: (string) ($payload['job'] ?? ''),
            attempts: (int) ($payload['attempts'] ?? 0),
            maxTries: (int) ($payload['maxTries'] ?? 3),
            timeout: (int) ($payload['timeout'] ?? 60),
            backoff: (int) ($payload['backoff'] ?? 0),
            createdAt: (int) ($payload['createdAt'] ?? time()),
            availableAt: (int) ($payload['availableAt'] ?? time()),
            delay: (int) ($payload['delay'] ?? 0),
            queue: (string) ($payload['queue'] ?? 'default'),
            data: (array) ($payload['data'] ?? [])
        );
    }

    public function resolveInstance(): object
    {
        return unserialize($this->job);
    }
}
