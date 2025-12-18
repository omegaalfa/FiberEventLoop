<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop\Traits;

use Throwable;

/**
 * TimerManagerTrait - Gerenciador de Timers e Escalonamento
 * 
 * Fornece funcionalidades de timers como:
 * - Execução única após delay (after)
 * - Execução repetida em intervalos (repeat)
 * - Sleep não-bloqueante em Fibers (sleep)
 * 
 * Todos os timers operam com suporte a números decimais para precisão de milissegundos.
 * 
 * @package Omegaalfa\FiberEventLoop\Traits
 */
trait TimerManagerTrait
{
    /**
     * Array de timers agendados
     * 
     * Estrutura:
     * ```
     * [int $id => [
     *     'time' => float,           // Timestamp quando executar
     *     'callback' => callable,    // Função a executar
     *     'interval' => float|null   // Se repetir, intervalo em segundos (opcional)
     * ]]
     * ```
     * 
     * @var array<int, array{time: float, callback: callable, interval?: float}>
     */
    protected array $timers = [];

    /**
     * Agenda callback para executar UMA VEZ após delay
     * 
     * Cria um timer que será executado automaticamente na próxima vez que
     * seu tempo chegar. Pode ser cancelado com cancel() antes de executar.
     * 
     * Precisão: ±5ms em condições normais, depende da carga do sistema.
     * 
     * @param callable $callback Função sem parâmetros a executar
     * @param float|int $seconds Delay em segundos (suporta decimais: 0.5 = 500ms)
     * 
     * @return int ID do timer para referência (pode ser usado em cancel())
     * 
     * @example
     * ```php
     * // Simples
     * $loop->after(fn() => echo "OK\n", 1.0);
     * 
     * // Com delay decimal
     * $loop->after(fn() => echo "200ms\n", 0.2);
     * 
     * // Cancelável
     * $id = $loop->after(fn() => echo "Nunca\n", 5.0);
     * $loop->after(fn() => $loop->cancel($id), 1.0);
     * ```
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
     * Agenda callback para executar REPETIDAMENTE a cada N segundos
     * 
     * Cria um timer que se re-agenda automaticamente após cada execução.
     * 
     * @param float $seconds Intervalo entre execuções (suporta decimais)
     * @param callable $callback Função sem parâmetros a executar
     * 
     * @return int ID do timer (necessário para cancel())
     * 
     * @example
     * ```php
     * // Infinito
     * $loop->repeat(1.0, fn() => echo "Tick\n");
     * 
     * // 5 vezes
     * $loop->repeat(0.5, fn() => echo "Bip\n", 5);
     * ```
     * 
     * @see setInterval() Alternativa com sintaxe different
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
     * Agenda callback para executar REPETIDAMENTE em intervalo
     * 
     * Alternativa a setInterval() com suporte a limite de execuções.
     * Útil para operações que precisam parar após N execuções.
     * 
     * @param float|int $interval Intervalo entre execuções em segundos
     * @param callable $callback Função sem parâmetros a executar
     * @param int|null $times Limite de execuções (null = infinito)
     * 
     * @return int ID do timer (necessário para cancel())
     * 
     * @example
     * ```php
     * // Executa 10 vezes a cada 500ms
     * $loop->repeat(0.5, function() {
     *     echo "Contador\n";
     * }, times: 10);
     * 
     * // Com variável externa
     * $count = 0;
     * $loop->repeat(1.0, function() use (&$count) {
     *     echo "Contagem: " . ++$count . "\n";
     * }, 5);
     * ```
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

                // Reagenda para próximo intervalo
                $this->timers[$id]['time'] = microtime(true) + (float) $interval;
            },
            'interval' => (float) $interval,
        ];

        return $id;
    }

    /**
     * Sleep não-bloqueante em Fiber
     * 
     * Suspende a Fiber atual por N segundos SEM bloquear o event loop.
     * Outras operações continuam executando normalmente.
     * 
     * ⚠️ **IMPORTANTE**: Só funciona dentro de uma Fiber!
     * 
     * Se chamado fora de uma Fiber (diretamente no loop), vai falhar com:
     * "Fiber::suspend() outside of a Fiber"
     * 
     * @param float|int $seconds Tempo de espera em segundos
     * 
     * @return void
     * 
     * @throws \Error Se chamado fora de uma Fiber
     * 
     * @example
     * ```php
     * // ✅ FUNCIONA - dentro de repeat()
     * $loop->repeat(5.0, function() use ($loop) {
     *     echo "Antes\n";
     *     $loop->sleep(2.0);      // Suspende por 2s
     *     echo "Depois de 2s\n";
     * });
     * 
     * // ✅ FUNCIONA - dentro de onWritable()
     * $loop->onWritable($client, $data, function($w, $t) use ($loop) {
     *     $loop->sleep(0.5);
     * });
     * 
     * // ❌ NÃO FUNCIONA - fora de Fiber
     * $loop->sleep(1.0); // Erro!
     * 
     * // ✅ FUNCIONA - com defer + defer de Fiber
     * $loop->defer(function() use ($loop) {
     *     // Está em contexto de Fiber agora
     *     $loop->sleep(1.0);
     * });
     * ```
     * 
     * **Casos de uso:**
     * - Retry com backoff exponencial
     * - Rate limiting
     * - Operações sequenciais com delay
     * - Simulação de processamento
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

        // Coopera com o loop
        while (!$done) {
            $this->tick();
        }
    }

    /**
     * Executa todos os timers que venceram
     * 
     * Método interno chamado pelo loop principal. Itera todos os timers,
     * verifica quais venceram e os executa. Timers com intervalo são re-agendados.
     * 
     * Erros em callbacks são capturados e armazenados em $this->errors.
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
                continue; // Ainda não chegou a hora
            }

            try {
                ($timer['callback'])();
            } catch (Throwable $e) {
                $this->errors[$id] = $e->getMessage();
            }

            if (isset($timer['interval'])) {
                // Re-agenda intervalo
                $timer['time'] = $now + $timer['interval'];
            } else {
                unset($this->timers[$id]); // Timer único já executou
            }
        }

        unset($timer); // Quebra referência
    }

    /**
     * Calcula tempo de espera até próximo timer
     * 
     * Útil para otimização de sleep. Retorna o tempo que o loop pode
     * dormir sem perder precisão de um timer.
     * 
     * @return float|null Segundos até próximo timer, null se nenhum timer, 0 se vencido
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
                return 0.0; // Já venceu
            }

            $next = $next === null ? $delay : min($next, $delay);
        }

        return $next;
    }

    /**
     * Tick do loop de timers (sleep cooperativo)
     * 
     * Método interno que executa timers e dorme o tempo apropriado.
     * Usado apenas internamente pelo sleep().
     * 
     * @return void
     */
    protected function tick(): void
    {
        $this->execTimers();

        $sleep = $this->getNextTimerDelay();

        if ($sleep === null) {
            usleep(1_000); // 1ms idle mínimo
            return;
        }

        if ($sleep > 0.0005) {
            usleep((int) ($sleep * 1_000_000));
        }
    }
}
