<?php

declare(strict_types=1);

use Config\Services;
use Jengo\Queues\Contracts\JobInterface;
use Jengo\Queues\Facades\Queue;
use Jengo\Queues\Support\QueueManager;

if (!function_exists('queue')) {
    /**
     * Get the queue manager or a specific connection.
     */
    function queue(?string $connection = null): QueueManager|\Jengo\Queues\Contracts\QueueDriverInterface
    {
        $manager = Services::queues();

        if ($connection !== null) {
            return $manager->connection($connection);
        }

        return $manager;
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue.
     */
    function dispatch(object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return Queue::push($job, $data, $queue);
    }
}

if (!function_exists('dispatch_later')) {
    /**
     * Dispatch a job to the queue with a delay.
     */
    function dispatch_later(int $delay, object|string $job, mixed $data = '', ?string $queue = null): string|int
    {
        return Queue::later($delay, $job, $data, $queue);
    }
}

if (!function_exists('defer')) {
    /**
     * Defer execution of a closure, callback, or callable to the default background queue.
     */
    function defer(\Closure|callable|array|string $callback, mixed ...$args): string|int
    {
        return Queue::defer($callback, ...$args);
    }
}

if (!function_exists('defer_later')) {
    /**
     * Defer execution of a closure, callback, or callable to the background queue with a delay.
     */
    function defer_later(int $delay, \Closure|callable|array|string $callback, mixed ...$args): string|int
    {
        return Queue::deferLater($delay, $callback, ...$args);
    }
}


