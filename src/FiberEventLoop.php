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
     * @var int
     */
    protected int $emptyIterationThreshold = 5;    // Threshold reduzido
    /**
     * @var int
     */
    protected int $maxIdleUsleep = 50;             // Máximo 50μs de sleep
    /**
     * @var int
     */
    protected int $minIdleUsleep = 5;              // Mínimo 5μs de sleep
    /**
     * @var bool
     */
    protected bool $adaptiveIdle = true;           // Idle adaptativo

    /**
     * Métricas de performance
     */
    protected array $metrics = [
        'iterations' => 0,
        'empty_iterations' => 0,
        'work_cycles' => 0,
        'last_work_time' => 0
    ];

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
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Loop principal OTIMIZADO
     *
     * @return void
     */
    public function run(): void
    {
        $this->running = true;
        $consecutiveEmpty = 0;
        $lastWorkTime = microtime(true);

        while ($this->running && $this->hasWork()) {
            $this->metrics['iterations']++;
            $hadWork = false;
            $startTime = microtime(true);

            // PRIORIDADE 1: Accept streams (crítico para conexões)
            if (!empty($this->acceptStreams)) {
                $this->execAcceptStreams();
                $hadWork = true;
            }

            // PRIORIDADE 2: Read streams (dados chegando)
            if (!empty($this->readStreams)) {
                $this->execReadStreams();
                $hadWork = true;
            }

            // PRIORIDADE 3: Write streams (dados saindo)
            if (!empty($this->writeStreams)) {
                $this->execWriteStreams();
                $hadWork = true;
            }

            // PRIORIDADE 4: Fibers (processamento)
            if (!empty($this->fibers)) {
                $this->execFibers();
                $hadWork = true;
            }

            // PRIORIDADE 5: Timers (menos crítico)
            if (!empty($this->timers)) {
                $this->execTimers();
                $hadWork = true;
            }

            // PRIORIDADE 6: Deferred (menos crítico)
            if (!empty($this->deferred)) {
                $this->execDeferred();
                $hadWork = true;
            }

            // Gerenciamento de idle OTIMIZADO
            if ($hadWork) {
                $consecutiveEmpty = 0;
                $lastWorkTime = microtime(true);
                $this->metrics['work_cycles']++;
                $this->metrics['last_work_time'] = microtime(true) - $startTime;
            } else {
                $consecutiveEmpty++;
                $this->metrics['empty_iterations']++;

                // Idle adaptativo para economizar CPU
                if ($consecutiveEmpty > $this->emptyIterationThreshold) {
                    if ($this->adaptiveIdle) {
                        // Sleep adaptativo baseado no tempo sem trabalho
                        $idleTime = microtime(true) - $lastWorkTime;

                        if ($idleTime > 0.001) { // > 1ms sem trabalho
                            usleep($this->maxIdleUsleep);
                        } elseif ($idleTime > 0.0001) { // > 0.1ms sem trabalho
                            usleep($this->minIdleUsleep);
                        }
                        // Senão, continua tight loop
                    } else if ($consecutiveEmpty > 50) {
                        usleep($this->maxIdleUsleep);
                    } elseif ($consecutiveEmpty > 20) {
                        usleep($this->minIdleUsleep);
                    }
                }
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
            || !empty($this->readStreams)
            || !empty($this->writeStreams);
    }

    /**
     * Configuração de otimização em runtime
     *
     * @param string $level
     * @return void
     */
    public function setOptimizationLevel(string $level): void
    {
        switch ($level) {
            case 'latency':
                // Mínima latência, máximo CPU
                $this->emptyIterationThreshold = 1000;
                $this->adaptiveIdle = false;
                $this->maxAcceptPerIteration = 500;
                $this->defaultBufferSize = 131072; // 128KB
                break;

            case 'throughput':
                // Máximo throughput, equilibrado
                $this->emptyIterationThreshold = 10;
                $this->adaptiveIdle = true;
                $this->maxAcceptPerIteration = 200;
                $this->defaultBufferSize = 65536; // 64KB
                break;

            case 'benchmark':
                // NOVO: Modo otimizado para benchmarks (ApacheBench, wrk, etc)
                $this->emptyIterationThreshold = 10;
                $this->adaptiveIdle = false;
                $this->maxAcceptPerIteration = 500;       // Aceita muito mais
                $this->defaultBufferSize = 65536;
                break;
            case 'efficient':
                // Economia de CPU
                $this->emptyIterationThreshold = 2;
                $this->adaptiveIdle = true;
                $this->maxAcceptPerIteration = 50;
                $this->defaultBufferSize = 32768; // 32KB
                break;

            case 'balanced':
            default:
                // Equilibrado (padrão)
                $this->emptyIterationThreshold = 5;
                $this->adaptiveIdle = true;
                $this->maxAcceptPerIteration = 100;
                $this->defaultBufferSize = 65536;
                break;
        }
    }
}