<?php

declare(strict_types=1);

namespace Jengo\Queues\Jobs;

use Closure;
use Jengo\Queues\Traits\Queueable;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

class DeferredCallbackJob
{
    use Queueable;

    protected string|array|SerializableClosure $callback;
    protected array $arguments = [];

    /**
     * @param callable|Closure|array|string $callback
     * @param array $arguments
     */
    public function __construct(callable|Closure|array|string $callback, array $arguments = [])
    {
        if ($callback instanceof Closure) {
            $this->callback = new SerializableClosure($callback);
        } else {
            $this->callback = $callback;
        }

        $this->arguments = $arguments;
    }

    /**
     * Execute the deferred callback.
     */
    public function handle(): void
    {
        $callable = $this->resolveCallable();

        $callable(...$this->arguments);
    }

    /**
     * Resolve callable instance.
     */
    protected function resolveCallable(): callable
    {
        if ($this->callback instanceof SerializableClosure) {
            return $this->callback->getClosure();
        }

        return $this->callback;
    }
}
