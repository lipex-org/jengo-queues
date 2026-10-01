<?php

declare(strict_types=1);

namespace Tests\Support\Redis;

class FakeRedisClient
{
    public array $lists = [];
    public array $zsets = [];
    public array $calls = [];

    public function rPush(string $key, string $value): int
    {
        $this->calls['rPush'][] = [$key, $value];
        if (!isset($this->lists[$key])) {
            $this->lists[$key] = [];
        }
        $this->lists[$key][] = $value;
        return count($this->lists[$key]);
    }

    public function lPop(string $key): ?string
    {
        $this->calls['lPop'][] = $key;
        if (!isset($this->lists[$key]) || empty($this->lists[$key])) {
            return null;
        }
        return array_shift($this->lists[$key]);
    }

    public function zAdd(string $key, float|int $score, string $member): int
    {
        $this->calls['zAdd'][] = [$key, $score, $member];
        $this->zsets[$key][$member] = $score;
        return 1;
    }

    public function zRangeByScore(string $key, mixed $min, mixed $max, array $options = []): array
    {
        if (!isset($this->zsets[$key])) {
            return [];
        }
        return array_keys($this->zsets[$key]);
    }

    public function zRem(string $key, string $member): int
    {
        $this->calls['zRem'][] = [$key, $member];
        if (isset($this->zsets[$key][$member])) {
            unset($this->zsets[$key][$member]);
            return 1;
        }
        return 0;
    }

    public function lLen(string $key): int
    {
        return count($this->lists[$key] ?? []);
    }

    public function zCard(string $key): int
    {
        return count($this->zsets[$key] ?? []);
    }

    public function del(string $key): int
    {
        unset($this->lists[$key], $this->zsets[$key]);
        return 1;
    }

    public function ping(): string
    {
        return '+PONG';
    }
}
