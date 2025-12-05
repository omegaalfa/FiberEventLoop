<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop;


use Omegaalfa\FiberEventLoop\Traits\FiberManagerTrait;
use Omegaalfa\FiberEventLoop\Traits\StreamManagerTrait;
use Omegaalfa\FiberEventLoop\Traits\TimerManagerTrait;

class FiberEventLoop
{
    use FiberManagerTrait;
    use StreamManagerTrait;
    use TimerManagerTrait;

    /**
     * @var bool
     */
    protected bool $running = false;

    /**
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $this->running = true;
        $consecutiveEmpty = 0;

        while ($this->running && $this->hasWork()) {
            $hadWork = false;

            if (!empty($this->deferred)) {
                $this->execDeferred();
                $hadWork = true;
            }

            if (!empty($this->timers)) {
                $this->execTimers();
                $hadWork = true;
            }

            if (!empty($this->acceptStreams)) {
                $this->execAcceptStreams();
                $hadWork = true;
            }

            if (!empty($this->readStreams)) {
                $this->execReadStreams();
                $hadWork = true;
            }

            if (!empty($this->fibers)) {
                $this->execFibers();
                $hadWork = true;
            }

            if (!$hadWork) {
                $consecutiveEmpty++;

                if ($consecutiveEmpty > 20) {
                    usleep(100);
                } elseif ($consecutiveEmpty > 10) {
                    usleep(10);
                }
            } else {
                $consecutiveEmpty = 0;
            }
        }
    }

    /**
     * @return bool
     */
    protected function hasWork(): bool
    {
        return !empty($this->deferred)
            || !empty($this->fibers)
            || !empty($this->timers)
            || !empty($this->acceptStreams)
            || !empty($this->readStreams);
    }
}