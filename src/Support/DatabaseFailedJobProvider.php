<?php

declare(strict_types=1);

namespace Jengo\Queues\Support;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Jengo\Queues\Contracts\FailedJobProviderInterface;
use Jengo\Queues\Entities\FailedJob;
use Throwable;

class DatabaseFailedJobProvider implements FailedJobProviderInterface
{
    protected string $table;
    protected string $dbGroup;
    protected ?BaseConnection $db = null;

    public function __construct(array $config = [])
    {
        $this->table = (string) ($config['table'] ?? 'queue_failed_jobs');
        $this->dbGroup = (string) ($config['DBGroup'] ?? 'default');
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

    public function log(string $connection, string $queue, string $payload, Throwable $exception): string|int
    {
        $now = date('Y-m-d H:i:s');
        $exceptionString = (string) $exception;

        $this->getDb()->table($this->table)->insert([
            'connection' => $connection,
            'queue'      => $queue,
            'payload'    => $payload,
            'exception'  => $exceptionString,
            'failed_at'  => $now,
        ]);

        return (int) $this->getDb()->insertID();
    }

    /**
     * @return FailedJob[]
     */
    public function all(): array
    {
        $rows = $this->getDb()->table($this->table)->orderBy('id', 'desc')->get()->getResultArray();

        return array_map(fn (array $row) => FailedJob::fromArray($row), $rows);
    }

    public function find(string|int $id): ?FailedJob
    {
        $row = $this->getDb()->table($this->table)->where('id', $id)->get()->getRowArray();

        return $row ? FailedJob::fromArray($row) : null;
    }

    public function forget(string|int $id): bool
    {
        $this->getDb()->table($this->table)->where('id', $id)->delete();
        return $this->getDb()->affectedRows() > 0;
    }

    public function flush(?int $hours = null): int
    {
        $builder = $this->getDb()->table($this->table);

        if ($hours !== null) {
            $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));
            $builder->where('failed_at <=', $cutoff);
        }

        $count = $builder->countAllResults(false);
        $builder->delete();

        return $count;
    }

    public function count(): int
    {
        return $this->getDb()->table($this->table)->countAllResults();
    }
}
