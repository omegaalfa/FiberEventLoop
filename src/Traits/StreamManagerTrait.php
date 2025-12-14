<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop\Traits;

use RuntimeException;
use Throwable;

trait StreamManagerTrait
{
    /**
     * @var array<int, array{server: resource, callback: callable}>
     */
    protected array $acceptStreams = [];

    /**
     * @var array<int, array{stream: resource, callback: callable, length: int, buffer: string}>
     */
    protected array $readStreams = [];

    /**
     * @var array<int, array{stream: resource, data: string, callback: callable, written: int}>
     */
    protected array $writeStreams = [];

    /**
     * @var int
     */
    protected int $maxAcceptPerIteration = 300;      // Aumentado para aceitar mais conexões

    /**
     * @var int
     */
    protected int $maxSelectStreams = 1000;           // Limite de streams por select

    /**
     * @var int
     */
    protected int $defaultBufferSize = 65536;         // 64KB buffer (otimizado para TCP)

    /**
     * @param resource $server
     * @param callable $callback
     * @return int
     */
    public function listen(mixed $server, callable $callback): int
    {
        $this->validateStream($server);
        $this->configureSocket($server);

        $id = $this->generateId();
        $this->acceptStreams[$id] = [
            'server' => $server,
            'callback' => $callback
        ];

        return $id;
    }

    /**
     * @param resource $stream
     * @return void
     */
    protected function validateStream(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Invalid stream resource');
        }

        $type = get_resource_type($stream);
        if (!in_array($type, ['stream', 'persistent stream'], true)) {
            throw new RuntimeException("Invalid resource type: $type");
        }
    }

    /**
     * Configura socket para máxima performance
     *
     * @param resource $stream
     * @return void
     */
    protected function configureSocket(mixed $stream): void
    {
        if (!is_resource($stream)) {
            return;
        }

        // Non-blocking obrigatório
        stream_set_blocking($stream, false);

        // Buffer TCP otimizado
        stream_set_read_buffer($stream, $this->defaultBufferSize);
        stream_set_write_buffer($stream, $this->defaultBufferSize);

        // Timeout zero para non-blocking
        stream_set_timeout($stream, 0);

        // Opções de socket para performance
        if (function_exists('socket_import_stream')) {
            $socket = socket_import_stream($stream);
            if ($socket !== false) {
                @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
                @socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, $this->defaultBufferSize);
                @socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, $this->defaultBufferSize);
            }
        }
    }

    /**
     * @param resource $stream
     * @param callable $callback
     * @param int $length
     * @return int
     */
    public function onReadable(mixed $stream, callable $callback, int $length = 65536): int
    {
        $this->validateStream($stream);
        $this->configureSocket($stream);

        $id = $this->generateId();
        $this->readStreams[$id] = [
            'stream' => $stream,
            'callback' => $callback,
            'length' => $length,
            'buffer' => '' // Buffer interno para leituras parciais
        ];

        return $id;
    }

    /**
     * @param resource $stream
     * @param string $data
     * @param callable $callback
     * @return int
     */
    public function onWritable(mixed $stream, string $data, callable $callback): int
    {
        $this->validateStream($stream);
        $this->configureSocket($stream);

        $id = $this->generateId();
        $this->writeStreams[$id] = [
            'stream' => $stream,
            'data' => $data,
            'callback' => $callback,
            'written' => 0
        ];

        return $id;
    }

    /**
     * Leitura de arquivo otimizada
     *
     * @param string $filename
     * @param callable $callback
     * @param int $length
     * @return int
     */
    public function onReadFile(string $filename, callable $callback, int $length = 65536): int
    {
        return $this->deferFiber(function (int $id) use ($length, $filename, $callback) {
            try {
                if (!file_exists($filename)) {
                    throw new RuntimeException("File not found: $filename");
                }

                $handle = @fopen($filename, 'rb');
                if ($handle === false) {
                    throw new RuntimeException("Could not open file: $filename");
                }

                $this->configureSocket($handle);

                try {
                    while (!feof($handle)) {
                        if ($this->isCancelled($id)) {
                            break;
                        }

                        $data = @fread($handle, $length);

                        if ($data !== false && $data !== '') {
                            $callback($data);
                        }

                        $this->next(); // Yield para outras tarefas
                    }
                } finally {
                    fclose($handle);
                }
            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
            }
        });
    }

    /**
     * OTIMIZADO: Aceita múltiplas conexões com batching
     *
     * @return void
     */
    protected function execAcceptStreams(): void
    {
        if (empty($this->acceptStreams)) {
            return;
        }

        $servers = [];
        foreach ($this->acceptStreams as $id => $accept) {
            if (!$this->isCancelled($id) && is_resource($accept['server'])) {
                $servers[$id] = $accept['server'];
            } else {
                unset($this->acceptStreams[$id]);
            }
        }

        if (empty($servers)) {
            return;
        }

        // Processa cada server
        foreach ($servers as $id => $server) {
            if ($this->isCancelled($id)) {
                unset($this->acceptStreams[$id]);
                continue;
            }

            // Aceita TODAS as conexões pendentes (até o limite)
            $accepted = 0;
            while ($accepted < $this->maxAcceptPerIteration) {
                $client = @stream_socket_accept($server, 0);

                if ($client === false) {
                    break; // Não há mais conexões pendentes
                }

                // Configura o cliente imediatamente
                $this->configureSocket($client);

                try {
                    $this->acceptStreams[$id]['callback']($client);
                    $accepted++;
                } catch (Throwable $exception) {
                    $this->errors[$id] = $exception->getMessage();
                    @fclose($client);
                }
            }
        }
    }

    /**
     * OTIMIZADO: Leitura em batch com buffer interno
     *
     * @return void
     */
    protected function execReadStreams(): void
    {
        if (empty($this->readStreams)) {
            return;
        }

        $streams = [];
        foreach ($this->readStreams as $id => $read) {
            if (!$this->isCancelled($id) && is_resource($read['stream'])) {
                $streams[$id] = $read['stream'];
            } else {
                unset($this->readStreams[$id]);
            }
        }

        if (empty($streams)) {
            return;
        }

        // Limita streams para evitar overhead do select
        if (count($streams) > $this->maxSelectStreams) {
            $streams = array_slice($streams, 0, $this->maxSelectStreams, true);
        }

        $read = $streams;
        $write = $except = [];

        $result = @stream_select($read, $write, $except, 0, 0);

        if ($result === false || $result === 0 || empty($read)) {
            return;
        }

        // Processa streams prontos
        foreach ($read as $id => $stream) {
            if ($this->isCancelled($id)) {
                unset($this->readStreams[$id]);
                continue;
            }

            try {
                // Lê dados do buffer TCP
                $data = @fread($stream, $this->readStreams[$id]['length']);

                if ($data === false) {
                    // Erro de leitura
                    if (feof($stream)) {
                        $this->readStreams[$id]['callback']('');
                        unset($this->readStreams[$id]);
                    }
                    continue;
                }

                if ($data === '') {
                    // Socket fechado
                    if (feof($stream)) {
                        $this->readStreams[$id]['callback']('');
                        unset($this->readStreams[$id]);
                    }
                    continue;
                }

                // Adiciona ao buffer interno
                $this->readStreams[$id]['buffer'] .= $data;

                // Callback com dados acumulados
                $this->readStreams[$id]['callback']($this->readStreams[$id]['buffer']);
                $this->readStreams[$id]['buffer'] = ''; // Limpa buffer após callback

            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
                unset($this->readStreams[$id]);
            }
        }
    }

    /**
     * NOVO: Escrita assíncrona otimizada
     *
     * @return void
     */
    protected function execWriteStreams(): void
    {
        if (empty($this->writeStreams)) {
            return;
        }

        $streams = [];
        foreach ($this->writeStreams as $id => $write) {
            if (!$this->isCancelled($id) && is_resource($write['stream'])) {
                $streams[$id] = $write['stream'];
            } else {
                unset($this->writeStreams[$id]);
            }
        }

        if (empty($streams)) {
            return;
        }

        $read = $except = [];
        $write = $streams;

        $result = @stream_select($read, $write, $except, 0, 0);

        if ($result === false || $result === 0 || empty($write)) {
            return;
        }

        // Processa escritas
        foreach ($write as $id => $stream) {
            if ($this->isCancelled($id)) {
                unset($this->writeStreams[$id]);
                continue;
            }

            try {
                $remaining = strlen($this->writeStreams[$id]['data']) - $this->writeStreams[$id]['written'];

                if ($remaining <= 0) {
                    $this->writeStreams[$id]['callback'](
                        $this->writeStreams[$id]['written'],
                        strlen($this->writeStreams[$id]['data'])
                    );
                    unset($this->writeStreams[$id]);
                    continue;
                }

                $chunk = substr(
                    $this->writeStreams[$id]['data'],
                    $this->writeStreams[$id]['written'],
                    $this->defaultBufferSize
                );

                $written = @fwrite($stream, $chunk);

                if ($written === false || $written === 0) {
                    continue;
                }

                $this->writeStreams[$id]['written'] += $written;

                // Callback de progresso
                $this->writeStreams[$id]['callback'](
                    $this->writeStreams[$id]['written'],
                    strlen($this->writeStreams[$id]['data'])
                );

                // Remove se completo
                if ($this->writeStreams[$id]['written'] >= strlen($this->writeStreams[$id]['data'])) {
                    unset($this->writeStreams[$id]);
                }

            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
                unset($this->writeStreams[$id]);
            }
        }
    }
}