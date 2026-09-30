<?php

declare(strict_types=1);

namespace Jengo\Queues\Drivers;

use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Entities\JobPayload;
use Jengo\Queues\Jobs\RedisJob;
use RuntimeException;
use Throwable;

class RedisQueueDriver extends AbstractQueueDriver
{
    /**
     * @var object|null
     */
    protected ?object $redis = null;
    protected string $host;
    protected int $port;
    protected ?string $password;
    protected int $database;
    protected float $timeout;
    protected int $retryAfter;

    public function __construct(array $config = [], string $prefix = '')
    {
        parent::__construct($config, $prefix);

        $this->host = (string) ($config['host'] ?? '127.0.0.1');
        $this->port = (int) ($config['port'] ?? 6379);
        $this->password = isset($config['password']) && $config['password'] !== '' ? (string) $config['password'] : null;
        $this->database = (int) ($config['database'] ?? 0);
        $this->timeout = (float) ($config['timeout'] ?? 0.0);
        $this->retryAfter = (int) ($config['retryAfter'] ?? 90);
    }

    public function setRedis(object $redis): self
    {
        $this->redis = $redis;
        return $this;
    }

    public function getRedis(): object
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        if (!extension_loaded('redis')) {
            throw new RuntimeException('The Redis PHP extension is required to use the RedisQueueDriver.');
        }

        $redisClass = '\\Redis';
        $redis = new $redisClass();
        $redis->connect($this->host, $this->port, $this->timeout);

        if ($this->password !== null) {
            $redis->auth($this->password);
        }

        if ($this->database !== 0) {
            $redis->select($this->database);
        }

        return $this->redis = $redis;
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->pushRaw($this->createPayload($job, $this->qualifyQueue($queue), 0), $queue);
    }

    public function later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return $this->laterRaw($delay, $this->createPayload($job, $this->qualifyQueue($queue), $delay), $queue);
    }

    public function pushRaw(JobPayload|string $payload, ?string $queue = null): string
    {
        $queueName = $this->qualifyQueue($queue);
        $serialized = is_string($payload) ? $payload : json_encode($payload->jsonSerialize());
        
        $this->getRedis()->rPush($this->getQueueKey($queueName), $serialized);

        return $payload instanceof JobPayload ? $payload->id : uniqid('job_', true);
    }

    public function laterRaw(int $delay, JobPayload|string $payload, ?string $queue = null): string
    {
        $queueName = $this->qualifyQueue($queue);
        $serialized = is_string($payload) ? $payload : json_encode($payload->jsonSerialize());
        $score = time() + max(0, $delay);

        $this->getRedis()->zAdd($this->getDelayedKey($queueName), $score, $serialized);

        return $payload instanceof JobPayload ? $payload->id : uniqid('job_', true);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queueName = $this->qualifyQueue($queue);
        $this->migrateDelayedJobs($queueName);

        $rawJob = $this->getRedis()->lPop($this->getQueueKey($queueName));

        if (!$rawJob || !is_string($rawJob)) {
            return null;
        }

        $rawPayload = json_decode($rawJob, true) ?? [];
        $payload = JobPayload::fromArray($rawPayload);
        $payload->attempts++;

        // Add to reserved sorted set with score = expiry timestamp
        $expiry = time() + $this->retryAfter;
        $this->getRedis()->zAdd($this->getReservedKey($queueName), $expiry, $rawJob);

        return new RedisJob($this, $rawJob, $payload, $queueName);
    }

    public function migrateDelayedJobs(string $queue): void
    {
        $now = time();
        $delayedKey = $this->getDelayedKey($queue);
        $queueKey = $this->getQueueKey($queue);
        $reservedKey = $this->getReservedKey($queue);

        // Migrate ready delayed jobs to the main queue
        $options = ['limit' => [0, 50]];
        $readyJobs = $this->getRedis()->zRangeByScore($delayedKey, '-inf', (string) $now, $options);
        if (!empty($readyJobs)) {
            foreach ($readyJobs as $job) {
                if ($this->getRedis()->zRem($delayedKey, $job) > 0) {
                    $this->getRedis()->rPush($queueKey, $job);
                }
            }
        }

        // Migrate expired reserved jobs back to the main queue
        $expiredJobs = $this->getRedis()->zRangeByScore($reservedKey, '-inf', (string) $now, $options);
        if (!empty($expiredJobs)) {
            foreach ($expiredJobs as $job) {
                if ($this->getRedis()->zRem($reservedKey, $job) > 0) {
                    $this->getRedis()->rPush($queueKey, $job);
                }
            }
        }
    }

    public function deleteReserved(string $queue, string $rawJob): void
    {
        $this->getRedis()->zRem($this->getReservedKey($queue), $rawJob);
    }

    public function size(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        $mainSize = (int) $this->getRedis()->lLen($this->getQueueKey($queueName));
        $delayedSize = (int) $this->getRedis()->zCard($this->getDelayedKey($queueName));
        $reservedSize = (int) $this->getRedis()->zCard($this->getReservedKey($queueName));

        return $mainSize + $delayedSize + $reservedSize;
    }

    public function clear(?string $queue = null): int
    {
        $queueName = $this->qualifyQueue($queue);
        $total = $this->size($queueName);

        $this->getRedis()->del($this->getQueueKey($queueName));
        $this->getRedis()->del($this->getDelayedKey($queueName));
        $this->getRedis()->del($this->getReservedKey($queueName));

        return $total;
    }

    public function status(): array
    {
        try {
            $redis = $this->getRedis();
            $ping = $redis->ping();
            return [
                'driver' => 'redis',
                'status' => ($ping === '+PONG' || $ping === true || $ping === 'PONG') ? 'ok' : 'unknown',
                'host'   => $this->host,
                'port'   => $this->port,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => 'redis',
                'status' => 'error',
                'error'  => $e->getMessage(),
            ];
        }
    }

    protected function getQueueKey(string $queue): string
    {
        return 'queues:' . $queue;
    }

    protected function getDelayedKey(string $queue): string
    {
        return 'queues:' . $queue . ':delayed';
    }

    protected function getReservedKey(string $queue): string
    {
        return 'queues:' . $queue . ':reserved';
    }
}
