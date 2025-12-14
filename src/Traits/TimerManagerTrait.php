<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop\Traits;

use Throwable;

trait TimerManagerTrait
{
    /**
     * @var array<int, array{time: float, callback: callable, interval?: float}>
     */
    protected array $timers = [];

    /**
     * Executa uma vez após N segundos
     *
     * @param callable $callback
     * @param float|int $seconds
     * @return int
     */
    public function after(callable $callback, float|int $seconds): int
    {
        $id = $this->generateId();

        $this->timers[$id] = [
            'time' => microtime(true) + (float) $seconds,
            'callback' => $callback,
        ];

        return $id;
    }

    /**
     * Executa repetidamente a cada N segundos
     *
     * @param float $seconds
     * @param callable $callback
     * @return int
     */
    public function setInterval(float $seconds, callable $callback): int
    {
        $id = $this->generateId();

        $this->timers[$id] = [
            'time' => microtime(true) + $seconds,
            'callback' => $callback,
            'interval' => $seconds,
        ];

        return $id;
    }

    /**
     * Compatibilidade com API antiga
     *
     * @param float|int $interval
     * @param callable $callback
     * @param int|null $times
     * @return int
     */
    public function repeat(float|int $interval, callable $callback, ?int $times = null): int
    {
        $id = $this->generateId();
        $count = 0;

        $this->timers[$id] = [
            'time' => microtime(true) + (float) $interval,
            'callback' => function () use (
                $callback,
                $interval,
                $times,
                &$count,
                $id
            ) {
                if ($times !== null && $count >= $times) {
                    unset($this->timers[$id]);
                    return;
                }

                $callback();
                $count++;

                // reagenda
                $this->timers[$id]['time'] = microtime(true) + (float) $interval;
            },
            'interval' => (float) $interval,
        ];

        return $id;
    }

    /**
     * Sleep cooperativo (não bloqueia CPU)
     *
     * @param float|int $seconds
     * @return void
     */
    public function sleep(float|int $seconds): void
    {
        $done = false;

        $this->after(
            function () use (&$done) {
                $done = true;
            },
            $seconds
        );

        // coopera com o loop
        while (!$done) {
            $this->tick();
        }
    }


    /**
     * Executa timers vencidos
     *
     * @return void
     */
    protected function execTimers(): void
    {
        if (!$this->timers) {
            return;
        }

        $now = microtime(true);

        foreach ($this->timers as $id => &$timer) {
            if ($this->isCancelled($id)) {
                unset($this->timers[$id]);
                continue;
            }

            if ($now < $timer['time']) {
                continue;
            }

            try {
                ($timer['callback'])();
            } catch (Throwable $e) {
                $this->errors[$id] = $e->getMessage();
            }

            if (isset($timer['interval'])) {
                // reagenda intervalo
                $timer['time'] = $now + $timer['interval'];
            } else {
                unset($this->timers[$id]);
            }
        }

        unset($timer); // quebra referência
    }

    /**
     * Retorna quanto tempo o loop pode dormir
     *
     * @return float|null
     */
    protected function getNextTimerDelay(): ?float
    {
        if (!$this->timers) {
            return null;
        }

        $now = microtime(true);
        $next = null;

        foreach ($this->timers as $timer) {
            $delay = $timer['time'] - $now;
            if ($delay < 0) {
                return 0.0;
            }

            $next = $next === null ? $delay : min($next, $delay);
        }

        return $next;
    }

    /**
     * Sleep cooperativo usado SOMENTE pelo loop principal
     *
     * @return void
     */
    protected function tick(): void
    {
        $this->execTimers();

        $sleep = $this->getNextTimerDelay();

        if ($sleep === null) {
            usleep(1_000); // idle mínimo
            return;
        }

        if ($sleep > 0.0005) {
            usleep((int) ($sleep * 1_000_000));
        }
    }
}
