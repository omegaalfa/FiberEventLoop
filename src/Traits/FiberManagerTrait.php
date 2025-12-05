<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop\Traits;

use Fiber;
use Throwable;

trait FiberManagerTrait
{
    /**
     * @var array<int, Fiber>
     */
    protected array $fibers = [];

    /**
     * @var array<int, callable>
     */
    protected array $deferred = [];

    /**
     * @var array<int, bool>
     */
    protected array $cancelled = [];

    /**
     * @var int
     */
    protected int $nextId = 1;

    /**
     * @param callable $callable
     * @return int
     */
    public function defer(callable $callable): int
    {
        $id = $this->generateId();
        $this->deferred[$id] = $callable;
        return $id;
    }

    /**
     * @return int
     */
    protected function generateId(): int
    {
        return $this->nextId++;
    }

    /**
     * @param int $id
     * @return void
     */
    public function cancel(int $id): void
    {
        $this->cancelled[$id] = true;

        unset(
            $this->fibers[$id],
            $this->deferred[$id],
            $this->timers[$id],
            $this->acceptStreams[$id],
            $this->readStreams[$id]
        );
    }

    /**
     * @param mixed|null $value
     * @return mixed
     * @throws Throwable
     */
    public function next(mixed $value = null): mixed
    {
        if (Fiber::getCurrent() === null) {
            return null;
        }

        return Fiber::suspend($value);
    }

    /**
     * @param callable $callable
     * @return int
     */
    protected function deferFiber(callable $callable): int
    {
        $fiber = new Fiber($callable);
        $fiberId = spl_object_id($fiber);
        $this->fibers[$fiberId] = $fiber;
        return $fiberId;
    }

    /**
     * @return void
     */
    protected function execFibers(): void
    {
        if (empty($this->fibers)) {
            return;
        }

        foreach ($this->fibers as $id => $fiber) {
            if ($this->isCancelled($id)) {
                unset($this->fibers[$id]);
                continue;
            }

            try {
                if (!$fiber->isStarted()) {
                    $fiber->start($id);
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if ($fiber->isTerminated()) {
                    unset($this->fibers[$id]);
                }

            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
                unset($this->fibers[$id]);
            }
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    protected function isCancelled(int $id): bool
    {
        return isset($this->cancelled[$id]);
    }

    /**
     * @return void
     */
    protected function execDeferred(): void
    {
        if (empty($this->deferred)) {
            return;
        }

        $callbacks = $this->deferred;
        $this->deferred = [];

        foreach ($callbacks as $id => $callback) {
            if ($this->isCancelled($id)) {
                continue;
            }

            try {
                $callback();
            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
            }
        }
    }
}