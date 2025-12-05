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
     * @var array<int, array{stream: resource, callback: callable, length: int}>
     */
    protected array $readStreams = [];

    /**
     * @param $server
     * @param callable $callback
     * @return int
     */
    public function listen($server, callable $callback): int
    {
        $this->validateStream($server);

        $id = $this->generateId();
        $this->acceptStreams[$id] = [
            'server' => $server,
            'callback' => $callback
        ];

        return $id;
    }

    /**
     * @param $stream
     * @return void
     */
    protected function validateStream($stream): void
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
     * @param $stream
     * @param callable $callback
     * @param int $length
     * @return int
     */
    public function onReadable($stream, callable $callback, int $length = 8192): int
    {
        $this->validateStream($stream);

        $id = $this->generateId();
        $this->readStreams[$id] = [
            'stream' => $stream,
            'callback' => $callback,
            'length' => $length
        ];

        return $id;
    }

    /**
     * @param $stream
     * @param string $data
     * @param callable $callback
     * @param bool $blocking
     * @return int
     */
    public function onWritable($stream, string $data, callable $callback, bool $blocking = false): int
    {
        $this->validateStream($stream);

        return $this->deferFiber(function (int $id) use ($stream, $data, $callback, $blocking) {
            try {
                $this->streamWrite($stream, $data, $callback, $blocking, $id);
            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
            }
        });
    }

    /**
     * @param resource $stream
     * @param string $data
     * @param callable $callback
     * @param bool $blocking
     * @param int $id
     *
     * @return void
     * @throws Throwable
     */
    private function streamWrite($stream, string $data, callable $callback, bool $blocking, int $id): void
    {
        if (!stream_set_blocking($stream, $blocking)) {
            throw new RuntimeException("Failed to set blocking mode on stream");
        }

        $length = strlen($data);
        $written = 0;
        $retries = 0;
        $maxRetries = 1000;

        while ($written < $length) {
            if ($this->isCancelled($id)) {
                break;
            }

            $result = @fwrite($stream, substr($data, $written));

            if ($result === false) {
                $error = error_get_last();
                throw new RuntimeException(
                    "Failed to write to stream: " . ($error['message'] ?? 'unknown error')
                );
            }

            if ($result === 0) {
                if (++$retries > $maxRetries) {
                    throw new RuntimeException("Max write retries exceeded");
                }

                $this->next();
                continue;
            }

            $written += $result;
            $retries = 0;

            $callback($written, $length);
        }
    }

    /**
     * @param string $filename
     * @param callable $callback
     * @param bool $blocking
     * @param int $length
     *
     * @return int
     */
    public function onReadFile(string $filename, callable $callback, bool $blocking = false, int $length = 8192): int
    {
        return $this->deferFiber(function (int $id) use ($length, $filename, $callback, $blocking) {
            try {
                $this->streamReadFile($filename, $callback, $length, $blocking, $id);
            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
            }
        });
    }

    /**
     * @param string $filename
     * @param callable $callback
     * @param int $length
     * @param bool $blocking
     * @param int $id
     *
     * @return void
     * @throws Throwable
     */
    private function streamReadFile(string $filename, callable $callback, int $length, bool $blocking, int $id): void
    {
        if (!file_exists($filename)) {
            throw new RuntimeException("File not found: $filename");
        }

        if (!is_readable($filename)) {
            throw new RuntimeException("File not readable: $filename");
        }

        $handle = @fopen($filename, 'rb');

        if ($handle === false) {
            $error = error_get_last();
            throw new RuntimeException(
                "Could not open file: $filename - " . ($error['message'] ?? 'unknown error')
            );
        }

        try {
            $this->streamRead($handle, $callback, $length, $blocking, $id);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * @param resource $stream
     * @param callable $callback
     * @param int $length
     * @param bool $blocking
     * @param int $id
     *
     * @return void
     * @throws Throwable
     */
    private function streamRead($stream, callable $callback, int $length, bool $blocking, int $id): void
    {
        if (!stream_set_blocking($stream, $blocking)) {
            throw new RuntimeException("Failed to set blocking mode on stream");
        }

        $retries = 0;
        $maxRetries = 1000;

        while (!feof($stream)) {
            if ($this->isCancelled($id)) {
                break;
            }

            $data = @fread($stream, $length);

            if ($data === false) {
                $error = error_get_last();
                throw new RuntimeException(
                    "Error reading from stream: " . ($error['message'] ?? 'unknown error')
                );
            }

            if ($data === '') {
                if (feof($stream)) {
                    break;
                }

                if (++$retries > $maxRetries) {
                    throw new RuntimeException("Max read retries exceeded");
                }

                $this->next();
                continue;
            }

            $retries = 0;
            $callback($data);
        }
    }

    /**
     * @return void
     */
    protected function execAcceptStreams(): void
    {
        if (empty($this->acceptStreams)) {
            return;
        }

        foreach ($this->acceptStreams as $id => $accept) {
            if ($this->isCancelled($id)) {
                unset($this->acceptStreams[$id]);
                continue;
            }

            try {
                $client = @stream_socket_accept($accept['server'], 0);

                if ($client !== false) {
                    $accept['callback']($client);
                }

            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
                unset($this->acceptStreams[$id]);
            }
        }
    }

    /**
     * @return void
     */
    protected function execReadStreams(): void
    {
        if (empty($this->readStreams)) {
            return;
        }

        foreach ($this->readStreams as $id => $read) {
            if ($this->isCancelled($id)) {
                unset($this->readStreams[$id]);
                continue;
            }

            try {
                $data = @fread($read['stream'], $read['length']);

                if ($data !== false && $data !== '') {
                    $read['callback']($data);
                } elseif (feof($read['stream'])) {
                    $read['callback']('');
                    unset($this->readStreams[$id]);
                }

            } catch (Throwable $exception) {
                $this->errors[$id] = $exception->getMessage();
                unset($this->readStreams[$id]);
            }
        }
    }
}