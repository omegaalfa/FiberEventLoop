<?php

declare(strict_types=1);


namespace Omegaalfa\FiberEventLoop\Traits;

use Throwable;

trait TimerManagerTrait
{
    /**
     * @var array<int, array{timeout: float, callback: callable}>
     */
    protected array $timers = [];

    /**
     * @param float|int $seconds
     * @param callable $callback
     * @return int
     */
    public function after(callable $callback, float|int $seconds): int
    {
        $id = $this->generateId();
        $this->timers[$id] = [
            'timeout' => microtime(true) + $seconds,
            'callback' => $callback
        ];

        return $id;
    }


    /**
     * @param float|int $interval
     * @param callable $callback
     * @param int|null $times
     * @return int
     */
    public function repeat(float|int $interval, callable $callback, ?int $times = null): int
    {
        return $this->deferFiber(function (int $id) use ($interval, $callback, $times) {

            $count = 0;
            $infinite = is_null($times);

            while ($infinite || $count < $times) {

                if ($this->isCancelled($id)) {
                    break;
                }

                $this->sleep($interval);
                $callback();

                $count++;
            }

        });
    }

    /**
     * @param float|int $seconds
     * @return void
     * @throws Throwable
     */
    public function sleep(float|int $seconds): void
    {
        $stop = microtime(true) + $seconds;

        if ($seconds < 0.001) {
            while (microtime(true) < $stop) {
                $this->next();
            }
            return;
        }

        while (($remaining = $stop - microtime(true)) > 0) {
            usleep((int)(min($remaining, 0.001) * 1000000));
            $this->next();
        }
    }

    /**
     * @return void
     */
    protected function execTimers(): void
    {
        if (empty($this->timers)) {
            return;
        }

        $now = microtime(true);
        $toRemove = [];

        foreach ($this->timers as $id => $timer) {
            if ($this->isCancelled($id)) {
                $toRemove[] = $id;
                continue;
            }

            if ($now >= $timer['timeout']) {
                try {
                    $timer['callback']();
                } catch (Throwable $exception) {
                    $this->errors[$id] = $exception->getMessage();
                }

                $toRemove[] = $id;
            }
        }

        foreach ($toRemove as $id) {
            unset($this->timers[$id]);
        }
    }
}