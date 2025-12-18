<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

/**
 * Testes para StreamManagerTrait
 * 
 * Cobre funcionalidades de streams TCP: listen, onReadable, onWritable
 */
class StreamManagerTraitTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Testa criação de server socket
     */
    public function testCreateServerSocket(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($server);
        fclose($server);
    }

    /**
     * Testa listen com aceitar conexão
     */
    public function testListenAcceptsConnection(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $clientConnected = false;
        $connectedAddr = null;

        $this->loop->listen($server, function($client) use (&$clientConnected, &$connectedAddr) {
            $clientConnected = true;
            $connectedAddr = stream_socket_get_name($client, true);
            fclose($client);
        });

        // Conecta cliente em outra Fiber
        $this->loop->defer(function() use ($host, $port) {
            usleep(10000); // 10ms de delay
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                fclose($client);
            }
        });

        // Para o loop após 100ms
        $this->loop->after(fn() => $this->loop->stop(), 0.1);

        $this->loop->run();
        fclose($server);

        $this->assertTrue($clientConnected, 'Cliente deveria ter sido aceito');
        $this->assertNotNull($connectedAddr);
    }

    /**
     * Testa onReadable básico
     */
    public function testOnReadableBasic(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $dataReceived = null;

        $this->loop->listen($server, function($client) use (&$dataReceived) {
            $this->loop->onReadable($client, function($data) use ($client, &$dataReceived) {
                if ($data === '') {
                    fclose($client);
                    return;
                }
                $dataReceived = $data;
            });
        });

        // Cliente conecta e envia dados
        $this->loop->defer(function() use ($host, $port) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                fwrite($client, 'test data');
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertNotNull($dataReceived);
        $this->assertStringContainsString('test data', $dataReceived);
    }

    /**
     * Testa onWritable básico
     */
    public function testOnWritableBasic(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $dataWritten = 0;

        $this->loop->listen($server, function($client) use (&$dataWritten) {
            $this->loop->onWritable($client, 'echo response', function($written, $total) use (&$dataWritten) {
                $dataWritten = $written;
            });
        });

        // Cliente conecta
        $this->loop->defer(function() use ($host, $port) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                sleep_non_blocking(0.1); // Espera dados
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertGreaterThan(0, $dataWritten, 'Algum dado deveria ter sido escrito');
    }

    /**
     * Testa echo server completo
     */
    public function testEchoServer(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $echoed = null;
        $clientReceived = null;

        $this->loop->listen($server, function($client) use (&$echoed) {
            $this->loop->onReadable($client, function($data) use ($client, &$echoed) {
                if ($data === '') {
                    fclose($client);
                    return;
                }

                $echoed = $data;

                // Echo de volta
                $this->loop->onWritable($client, "ECHO: $data", function($written, $total) use ($client) {
                    if ($written === $total) {
                        fclose($client);
                    }
                });
            });
        });

        // Cliente
        $this->loop->defer(function() use ($host, $port, &$clientReceived) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                fwrite($client, 'hello');
                $clientReceived = fread($client, 1024);
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertEquals('hello', $echoed);
        $this->assertStringContainsString('ECHO: hello', $clientReceived ?? '');
    }

    /**
     * Testa múltiplas conexões simultâneas
     */
    public function testMultipleConnections(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $connectionsHandled = 0;

        $this->loop->listen($server, function($client) use (&$connectionsHandled) {
            $connectionsHandled++;
            fclose($client);
        });

        // Conecta múltiplos clientes
        $this->loop->defer(function() use ($host, $port) {
            for ($i = 0; $i < 3; $i++) {
                usleep(5000);
                $client = stream_socket_client("tcp://$host:$port");
                if ($client) {
                    fclose($client);
                }
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertGreaterThanOrEqual(1, $connectionsHandled);
    }

    /**
     * Testa cancelamento de listen
     */
    public function testCancelListen(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $connectionsHandled = 0;

        $listenId = $this->loop->listen($server, function($client) use (&$connectionsHandled) {
            $connectionsHandled++;
            fclose($client);
        });

        $this->loop->cancel($listenId);

        $this->loop->after(fn() => null, 0.1);
        $this->loop->run();
        fclose($server);

        // Não deveria ter lidado com nenhuma conexão
        $this->assertEquals(0, $connectionsHandled);
    }

    /**
     * Testa cancelamento de onReadable
     */
    public function testCancelOnReadable(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $dataReceived = null;

        $this->loop->listen($server, function($client) {
            $readId = $this->loop->onReadable($client, function($data) {
                $dataReceived = $data;
            });

            // Cancela imediatamente
            $this->loop->cancel($readId);
            fclose($client);
        });

        $this->loop->defer(function() use ($host, $port) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                fwrite($client, 'test');
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertNull($dataReceived, 'Dados não deveriam ter sido lidos após cancelamento');
    }

    /**
     * Testa stream inválido
     */
    public function testInvalidStreamThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $this->loop->onReadable(null, function($data) {});
    }

    /**
     * Testa buffer grande
     */
    public function testLargeDataTransfer(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $allData = '';
        $dataComplete = false;

        $this->loop->listen($server, function($client) use (&$allData, &$dataComplete) {
            $this->loop->onReadable($client, function($data) use ($client, &$allData, &$dataComplete) {
                if ($data === '') {
                    fclose($client);
                    $dataComplete = true;
                    return;
                }
                $allData .= $data;
            }, length: 1024);
        });

        // Cliente envia dados grandes
        $this->loop->defer(function() use ($host, $port) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                $largeData = str_repeat('x', 5000);
                fwrite($client, $largeData);
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertGreaterThan(0, strlen($allData));
        $this->assertTrue($dataComplete);
    }

    /**
     * Testa EOF (conexão fechada)
     */
    public function testConnectionClosed(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);

        $addr = stream_socket_get_name($server, false);
        list($host, $port) = explode(':', $addr);

        $connectionClosed = false;

        $this->loop->listen($server, function($client) use (&$connectionClosed) {
            $this->loop->onReadable($client, function($data) use (&$connectionClosed) {
                if ($data === '') {
                    $connectionClosed = true;
                }
            });
        });

        // Cliente conecta e fecha
        $this->loop->defer(function() use ($host, $port) {
            usleep(10000);
            $client = stream_socket_client("tcp://$host:$port");
            if ($client) {
                fclose($client);
            }
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();
        fclose($server);

        $this->assertTrue($connectionClosed, 'Fechamento de conexão deveria ter sido detectado');
    }

    /**
     * Helper para sleep não-bloqueante
     */
    private function sleep_non_blocking($seconds)
    {
        $start = microtime(true);
        while (microtime(true) - $start < $seconds) {
            usleep(1000);
        }
    }
}
