<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\Traits\FiberManagerTrait;
use Omegaalfa\FiberEventLoop\Traits\StreamManagerTrait;
use Omegaalfa\FiberEventLoop\Traits\TimerManagerTrait;

/**
 * FiberEventLoop - Event Loop Reativo Ultra-Otimizado
 * 
 * Implementa o padrão Reactor usando PHP Fibers nativos (8.1+) para operações
 * assincronadas com sintaxe síncrona. Suporta timers, TCP streams, leitura de
 * arquivos e processamento paralelo de múltiplas operações I/O.
 * 
 * @package Omegaalfa\FiberEventLoop
 * @version 2.0.0
 * @author OmegaAlfa <webdesenvolver.agenda@gmail.com>
 * 
 * @example
 * ```php
 * $loop = new FiberEventLoop();
 * 
 * // Timer simples
 * $loop->after(fn() => echo "Executado!", 1.0);
 * 
 * // TCP Server
 * $server = stream_socket_server('tcp://0.0.0.0:8000');
 * $loop->listen($server, function($client) use ($loop) {
 *     $loop->onReadable($client, fn($data) => fwrite($client, "Echo: $data"));
 * });
 * 
 * $loop->run();
 * ```
 * 
 * @see https://www.php.net/manual/pt_BR/language.fibers.php
 */
class FiberEventLoop
{
    use FiberManagerTrait;
    use StreamManagerTrait;
    use TimerManagerTrait;

    /**
     * Flag indicando se o loop está em execução
     * 
     * @var bool
     */
    protected bool $running = false;

    /**
     * Array associativo com erros capturados durante execução
     * 
     * Formato: [int $operationId => string $errorMessage]
     * 
     * @var array<int, string>
     */
    protected array $errors = [];

    /**
     * Limite de iterações vazias antes de aplicar sleep adaptativo
     * 
     * Reduza para máxima latência (tight loop), aumente para economia de CPU.
     * Valores típicos: 1-10 (latência), 50-100 (eficiência)
     * 
     * @var int
     */
    protected int $emptyIterationThreshold = 5;

    /**
     * Tempo máximo de sleep durante idle (microsegundos)
     * 
     * Padrão: 50μs (0.05ms) - mantém responsividade enquanto economiza CPU
     * 
     * @var int
     */
    protected int $maxIdleUsleep = 50;

    /**
     * Tempo mínimo de sleep durante idle (microsegundos)
     * 
     * Padrão: 5μs (0.005ms) - muito rápido para máxima responsividade
     * 
     * @var int
     */
    protected int $minIdleUsleep = 5;

    /**
     * Ativa sleep adaptativo baseado no tempo sem trabalho
     * 
     * Quando ativo, ajusta automaticamente o sleep conforme o tempo idle.
     * Reduz CPU em até 90% quando em idle sem prejudicar latência.
     * 
     * @var bool
     */
    protected bool $adaptiveIdle = true;

    /**
     * Métricas de performance e observabilidade
     * 
     * Chaves:
     * - iterations (int): Total de iterações do loop
     * - empty_iterations (int): Iterações sem trabalho
     * - work_cycles (int): Ciclos que realizaram trabalho
     * - last_work_time (float): Tempo do último ciclo com trabalho (em segundos)
     * 
     * @var array{iterations: int, empty_iterations: int, work_cycles: int, last_work_time: float}
     */
    protected array $metrics = [
        'iterations' => 0,
        'empty_iterations' => 0,
        'work_cycles' => 0,
        'last_work_time' => 0
    ];

    /**
     * Para o event loop gracefully
     * 
     * Define a flag de execução como false, fazendo o loop parar após a próxima
     * iteração. Operações em progresso completarão normalmente.
     * 
     * @return void
     * 
     * @example
     * ```php
     * $loop->after(fn() => $loop->stop(), 10.0);
     * $loop->run(); // Executa por 10 segundos
     * ```
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Obtém todos os erros capturados durante a execução
     * 
     * Retorna um array associativo mapeando IDs de operação para mensagens de erro.
     * Erros não interrompem o loop - ele continua processando outras operações.
     * 
     * @return string[] Array de mensagens de erro [int $id => string $error]
     * 
     * @example
     * ```php
     * $loop->repeat(1.0, fn() => throw new Exception("Erro!"), 3);
     * $loop->run();
     * 
     * foreach ($loop->getErrors() as $id => $error) {
     *     echo "Erro [$id]: $error\n";
     * }
     * ```
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtém métricas de performance e observabilidade
     * 
     * Retorna estatísticas sobre o comportamento do loop durante execução,
     * útil para debugging e otimização.
     * 
     * @return array{iterations: int, empty_iterations: int, work_cycles: int, last_work_time: float}
     *         Array com chaves:
     *         - iterations: Total de iterações (loops completados)
     *         - empty_iterations: Iterações sem qualquer trabalho
     *         - work_cycles: Iterações que realizaram trabalho
     *         - last_work_time: Tempo do último ciclo de trabalho em segundos
     * 
     * @example
     * ```php
     * $loop->run();
     * 
     * $metrics = $loop->getMetrics();
     * echo "Iterações: " . $metrics['iterations'] . "\n";
     * echo "Ociosas: " . $metrics['empty_iterations'] . "\n";
     * echo "Ciclos com trabalho: " . $metrics['work_cycles'] . "\n";
     * echo "Tempo médio: " . $metrics['last_work_time'] . "s\n";
     * ```
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Inicia o event loop (bloqueante)
     * 
     * Executa o loop reativo indefinidamente até que:
     * 1. stop() seja chamado
     * 2. Não haja mais operações agendadas
     * 3. Ocorra exceção não capturada
     * 
     * O loop mantém referências a todas as operações e as processa em ordem
     * de prioridade:
     * 1. Accept streams (aceitar conexões)
     * 2. Read streams (ler dados)
     * 3. Write streams (escrever dados)
     * 4. Fibers (processamento)
     * 5. Timers (eventos cronométrados)
     * 6. Deferred (callbacks simples)
     * 
     * Implementa idle adaptativo que reduz CPU para ~0% quando ocioso.
     * 
     * @return void
     * 
     * @throws Exception Erros não capturados em callbacks propagam para o chamador
     * 
     * @example
     * ```php
     * $loop->repeat(1.0, fn() => echo "Tick\n", 5);
     * $loop->run(); // Executa 5 vezes em 5 segundos
     * ```
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
     * Verifica se há trabalho agendado
     * 
     * Método interno que determina se o loop deve continuar executando.
     * Retorna false quando não há mais operações pendentes.
     * 
     * @return bool True se há trabalho pendente, false caso contrário
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
     * Ajusta o nível de otimização do loop em runtime
     * 
     * Permite trocar dinamicamente entre modos de operação sem reiniciar o loop.
     * Útil para adaptação dinâmica baseada em carga ou requisitos de performance.
     * 
     * @param string $level Nível de otimização:
     *                       - 'latency': Mínima latência, máximo CPU (tight loop)
     *                       - 'throughput': Máximo throughput com bom equilíbrio
     *                       - 'efficient': Economia de CPU com aceitável latência
     *                       - 'balanced': Equilíbrio entre CPU e latência (padrão)
     *                       - 'benchmark': Otimizado para ferramentas de benchmark
     * 
     * @return void
     * 
     * @example
     * ```php
     * $loop = new FiberEventLoop();
     * 
     * // Começa equilibrado
     * $loop->setOptimizationLevel('balanced');
     * 
     * // Detecta carga e muda para throughput
     * if ($requestCount > 1000) {
     *     $loop->setOptimizationLevel('throughput');
     * }
     * ```
     */
    public function setOptimizationLevel(string $level): void
    {
        switch ($level) {
            case 'latency':
                // Mínima latência, máximo CPU (tight loop)
                $this->emptyIterationThreshold = 1000;  // Praticamente desativa sleep
                $this->adaptiveIdle = false;            // Sem sleep adaptativo
                $this->maxAcceptPerIteration = 500;     // Aceita muitas conexões
                $this->defaultBufferSize = 131072;      // 128KB (maior buffer)
                break;

            case 'throughput':
                // Máximo throughput com equilíbrio
                $this->emptyIterationThreshold = 10;
                $this->adaptiveIdle = true;
                $this->maxAcceptPerIteration = 200;
                $this->defaultBufferSize = 65536;       // 64KB (padrão)
                break;

            case 'benchmark':
                // Otimizado para ferramentas de benchmark (ApacheBench, wrk, etc)
                $this->emptyIterationThreshold = 10;
                $this->adaptiveIdle = false;            // Sem sleep
                $this->maxAcceptPerIteration = 500;     // Muitas conexões
                $this->defaultBufferSize = 65536;
                break;

            case 'efficient':
                // Economia de CPU com latência aceitável
                $this->emptyIterationThreshold = 2;
                $this->adaptiveIdle = true;             // Sleep agressivo
                $this->maxAcceptPerIteration = 50;      // Poucas conexões por ciclo
                $this->defaultBufferSize = 32768;       // 32KB (menor buffer)
                break;

            case 'balanced':
            default:
                // Equilíbrio entre CPU e latência (padrão recomendado)
                $this->emptyIterationThreshold = 5;
                $this->adaptiveIdle = true;
                $this->maxAcceptPerIteration = 100;
                $this->defaultBufferSize = 65536;       // 64KB
                break;
        }
    }
}