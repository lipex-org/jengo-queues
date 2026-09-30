<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Jobs\DatabaseJob;
use Throwable;

class DatabaseQueueDriver extends AbstractQueueDriver
{
    protected string $table;
    protected string $dbGroup;
    protected int $retryAfter;
    protected ?BaseConnection $db = null;

    public function __construct(array $config = [], string $prefix = '')
    {
        parent::__construct($config, $prefix);

        $this->table = (string) ($config['table'] ?? 'queue_jobs');
        $this->dbGroup = (string) ($config['DBGroup'] ?? 'default');
        $this->retryAfter = (int) ($config['retryAfter'] ?? 90);
    }

    public function setConnection(BaseConnection $db): self
    {
        $this->db = $db;
        return $this;
    }

    protected function getDb(): BaseConnection
    {
        if ($this->db !== null) {
            return $this->db;
        }

        return $this->db = Database::connect($this->dbGroup);
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->pushToDatabase($job, $queue, 0);
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->pushToDatabase($job, $queue, $delay);
    }

    public function pushRaw(JobPayload|string $payload, ?string $queue = null): string|int
    {
        $queueName = $this->qualifyQueue($queue);
        $payloadObj = is_string($payload) ? JobPayload::fromArray(json_decode($payload, true) ?? []) : $payload;

        $now = time();
        $availableAt = $payloadObj->availableAt > 0 ? $payloadObj->availableAt : $now + max(0, $payloadObj->delay);

        $builder = $this->getDb()->table($this->table);
        $builder->insert([
            'queue'        => $queueName,
            'payload'      => json_encode($payloadObj->jsonSerialize()),
            'attempts'     => $payloadObj->attempts,
            'reserved_at'  => null,
            'available_at' => $availableAt,
            'created_at'   => $now,
        ]);

        return (int) $this->getDb()->insertID();
    }

    protected function pushToDatabase(object|string $job, ?string $queue = null, int $delay = 0): int
    {
        $queueName = $this->qualifyQueue($queue);
        $payload = $this->createPayload($job, $queueName, $delay);

        $now = time();
        $availableAt = $now + max(0, $delay);

        $builder = $this->getDb()->table($this->table);
        $builder->insert([
            'queue'        => $queueName,
            'payload'      => json_encode($payload->jsonSerialize()),
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => $availableAt,
            'created_at'   => $now,
        ]);

        return (int) $this->getDb()->insertID();
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queueName = $this->qualifyQueue($queue);
        $now = time();
        $expiration = $now - $this->retryAfter;

        $db = $this->getDb();

        try {
            // Find next available job
            $builder = $db->table($this->table);
            $job = $builder->where('queue', $queueName)
                ->groupStart()
                    ->where('reserved_at IS NULL', null, false)
                    ->orWhere('reserved_at <=', $expiration)
                ->groupEnd()
                ->where('available_at <=', $now)
                ->orderBy('id', 'asc')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!$job) {
                return null;
            }

            // Reserve the job
            $db->table($this->table)
                ->where('id', (int) $job['id'])
                ->update([
                    'reserved_at' => $now,
                    'attempts'    => ((int) $job['attempts']) + 1,
                ]);

            $rawPayload = json_decode((string) $job['payload'], true) ?? [];
            $payload = JobPayload::fromArray($rawPayload);
            $payload->attempts = (int) $job['attempts'] + 1;

            return new DatabaseJob($this, $job, $payload, $queueName);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function deleteReservedJob(string $queue, int $id): void
    {
        $this->getDb()->table($this->table)->where('id', $id)->delete();
    }

    public function releaseJob(string $queue, int $id, int $delay = 0): void
    {
        $now = time();
        $this->getDb()->table($this->table)->where('id', $id)->update([
            'reserved_at'  => null,
            'available_at' => $now + max(0, $delay),
        ]);
    }

    public function size(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        return $this->getDb()->table($this->table)->where('queue', $queueName)->countAllResults();
    }

    public function clear(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        $count = $this->size($queue);
        $this->getDb()->table($this->table)->where('queue', $queueName)->delete();
        return $count;
    }

    public function status(): array
    {
        try {
            $db = $this->getDb();
            $tableExists = $db->tableExists($this->table);
            return [
                'driver'       => 'database',
                'status'       => $tableExists ? 'ok' : 'missing_table',
                'table'        => $this->table,
                'pending_jobs' => $tableExists ? $db->table($this->table)->countAllResults() : 0,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => 'database',
                'status' => 'error',
                'error'  => $e->getMessage(),
            ];
        }
    }
}
