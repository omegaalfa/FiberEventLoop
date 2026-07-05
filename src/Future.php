<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop;

use Fiber;
use RuntimeException;
use Throwable;

final class Future
{
    /**
     * @var bool
     */
    private bool $settled = false;

    /**
     * @var mixed|null
     */
    private mixed $value = null;

    /**
     * @var Throwable|null
     */
    private ?Throwable $exception = null;

    /**
     * @param FiberEventLoop $loop
     */
    public function __construct(private readonly FiberEventLoop $loop)
    {
    }

    /**
     * @param mixed $value
     * @return void
     */
    public function resolve(mixed $value): void
    {
        if ($this->settled) {
            return;
        }

        $this->settled = true;
        $this->value = $value;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function reject(Throwable $exception): void
    {
        if ($this->settled) {
            return;
        }

        $this->settled = true;
        $this->exception = $exception;
    }

    /**
     * @return bool
     */
    public function isSettled(): bool
    {
        return $this->settled;
    }

    /**
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->exception !== null;
    }

    /**
     * @return FiberEventLoop
     */
    public function getLoop(): FiberEventLoop
    {
        return $this->loop;
    }

    /**
     * @throws Throwable
     */
    public function await(): mixed
    {
        if (!$this->settled) {
            if (Fiber::getCurrent() !== null) {
                while (!$this->settled) {
                    $this->loop->next();
                }
            } else {
                $this->loop->run();
            }
        }

        if (!$this->settled) {
            throw new RuntimeException('Future did not settle before the event loop became idle');
        }

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->value;
    }
}