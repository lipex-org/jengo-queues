<?php

declare(strict_types=1);

namespace Jengo\Queues\Entities;

use JsonSerializable;

class FailedJob implements JsonSerializable
{
    public function __construct(
        public int|string $id,
        public string $connection,
        public string $queue,
        public string $payload,
        public string $exception,
        public string $failedAt
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'] ?? 0,
            connection: (string) ($row['connection'] ?? 'default'),
            queue: (string) ($row['queue'] ?? 'default'),
            payload: (string) ($row['payload'] ?? '{}'),
            exception: (string) ($row['exception'] ?? ''),
            failedAt: (string) ($row['failed_at'] ?? $row['failedAt'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function getJobPayload(): JobPayload
    {
        $data = json_decode($this->payload, true) ?? [];
        return JobPayload::fromArray($data);
    }

    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'connection' => $this->connection,
            'queue'      => $this->queue,
            'payload'    => $this->payload,
            'exception'  => $this->exception,
            'failedAt'   => $this->failedAt,
        ];
    }
}
