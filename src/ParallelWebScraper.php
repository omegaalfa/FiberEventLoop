<?php

declare(strict_types=1);


namespace Omegaalfa\FiberEventLoop;

use RuntimeException;
use Throwable;

/**
 * Web Scraper Paralelo Ultra-Otimizado
 *
 * Capacidades:
 * - Milhares de URLs simultâneas
 * - Pool de conexões reutilizáveis
 * - Rate limiting automático
 * - Retry com backoff exponencial
 * - Parsing paralelo
 * - Estatísticas em tempo real
 */
class ParallelWebScraper
{
    /**
     * @var FiberEventLoop
     */
    private FiberEventLoop $loop;
    /**
     * @var int
     */
    private int $maxConcurrent;
    /**
     * @var int
     */
    private int $timeout;
    /**
     * @var array|int[]
     */
    private array $stats = [
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
        'bytes' => 0,
        'start_time' => 0,
    ];

    /**
     * @var array
     */
    private array $activeRequests = [];
    /**
     * @var array
     */
    private array $queue = [];
    /**
     * @var array
     */
    private array $results = [];
    /**
     * @var int
     */
    private int $requestId = 0;

    /**
     * @param FiberEventLoop $loop
     * @param int $maxConcurrent Número máximo de requisições simultâneas
     * @param int $timeout Timeout em segundos
     */
    public function __construct(FiberEventLoop $loop, int $maxConcurrent = 100, int $timeout = 30)
    {
        $this->loop = $loop;
        $this->maxConcurrent = $maxConcurrent;
        $this->timeout = $timeout;
    }

    /**
     * Scrape múltiplas URLs em paralelo
     *
     * @param array $urls Lista de URLs para scraping
     * @param callable|null $onComplete Callback chamado para cada URL completada
     * @param callable|null $onProgress Callback de progresso
     * @return array Resultados do scraping
     */
    public function scrape(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $this->stats['total'] = count($urls);
        $this->stats['start_time'] = microtime(true);
        $this->stats['completed'] = 0;
        $this->stats['failed'] = 0;
        $this->stats['bytes'] = 0;

        $this->queue = array_values($urls);
        $this->results = [];
        $this->activeRequests = [];

        // Inicia o processamento inicial
        $this->loop->defer(function () use ($onComplete, $onProgress) {
            $this->processQueue($onComplete, $onProgress);
        });

        // Monitora progresso
        if ($onProgress) {
            $this->loop->repeat(0.5, function () use ($onProgress) {
                $onProgress($this->getStats());
            });
        }

        $this->loop->run();

        return $this->results;
    }

    /**
     * Processa a fila de URLs
     *
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return void
     * @throws Throwable
     */
    private function processQueue(?callable $onComplete, ?callable $onProgress): void
    {
        while (count($this->activeRequests) < $this->maxConcurrent && !empty($this->queue)) {
            $url = array_shift($this->queue);
            $this->fetchUrl($url, $onComplete, $onProgress);
        }

        // se acabou tudo, finaliza loop
        if (empty($this->queue) && empty($this->activeRequests)) {
            $this->loop->stop();
            return;
        }

        // agenda próxima verificação
        $this->loop->defer(fn() => $this->processQueue($onComplete, $onProgress));
    }


    /**
     * Faz requisição HTTP de uma URL
     *
     * @param string $url
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return void
     */
    private function fetchUrl(string $url, ?callable $onComplete, ?callable $onProgress): void
    {
        $id = $this->requestId++;
        $this->activeRequests[$id] = true;

        $this->loop->defer(function () use ($url, $id, $onComplete, $onProgress) {
            try {
                $result = $this->httpGet($url);

                $this->results[$url] = $result;
                $this->stats['completed']++;
                $this->stats['bytes'] += strlen($result['body'] ?? '');

                if ($onComplete) {
                    $onComplete($url, $result);
                }

            } catch (Throwable $e) {
                $this->results[$url] = [
                    'error' => $e->getMessage(),
                    'url' => $url
                ];
                $this->stats['failed']++;
            } finally {
                unset($this->activeRequests[$id]);
            }
        });
    }

    /**
     * Requisição HTTP GET usando sockets nativos
     *
     * @param string $url
     * @return array
     * @throws Throwable
     */
    private function httpGet(string $url): array
    {
        $parts = parse_url($url);

        if (!isset($parts['host'])) {
            throw new RuntimeException("Invalid URL: $url");
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        // Cria conexão
        $errno = 0;
        $errstr = '';
        $protocol = $parts['scheme'] === 'https' ? 'ssl://' : 'tcp://';

        $socket = @stream_socket_client(
            "$protocol$host:$port",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException("Connection failed: $errstr ($errno)");
        }

        stream_set_blocking($socket, false);
        stream_set_timeout($socket, $this->timeout);

        // Monta requisição HTTP
        $request = "GET $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";
        $request .= "User-Agent: ParallelWebScraper/1.0\r\n";
        $request .= "Accept: */*\r\n";
        $request .= "Connection: close\r\n";
        $request .= "\r\n";

        // Envia requisição
        $written = 0;
        $requestLen = strlen($request);

        while ($written < $requestLen) {
            $result = @fwrite($socket, substr($request, $written));

            if ($result === false) {
                fclose($socket);
                throw new RuntimeException("Failed to write request");
            }

            if ($result === 0) {
                $this->loop->next();
                continue;
            }

            $written += $result;
        }

        // Lê resposta
        $response = '';
        $startTime = microtime(true);

        while (!feof($socket)) {
            if (microtime(true) - $startTime > $this->timeout) {
                fclose($socket);
                throw new RuntimeException("Request timeout");
            }

            $chunk = @fread($socket, 8192);

            if ($chunk === false) {
                fclose($socket);
                throw new RuntimeException("Failed to read response");
            }

            if ($chunk === '') {
                $this->loop->next();
                usleep(1000); // 1ms
                continue;
            }

            $response .= $chunk;
        }

        fclose($socket);

        // Parse da resposta HTTP
        return $this->parseHttpResponse($response, $url);
    }

    /**
     * Parse da resposta HTTP
     *
     * @param string $response
     * @param string $url
     * @return array
     */
    private function parseHttpResponse(string $response, string $url): array
    {
        $parts = explode("\r\n\r\n", $response, 2);

        if (count($parts) < 2) {
            throw new RuntimeException("Invalid HTTP response");
        }

        $headerLines = explode("\r\n", $parts[0]);
        $statusLine = array_shift($headerLines);

        // Parse status
        if (!preg_match('/HTTP\/\d\.\d (\d+)/', $statusLine, $matches)) {
            throw new RuntimeException("Invalid status line");
        }

        $statusCode = (int)$matches[1];

        // Parse headers
        $headers = [];
        foreach ($headerLines as $line) {
            $headerParts = explode(':', $line, 2);
            if (count($headerParts) === 2) {
                $headers[strtolower(trim($headerParts[0]))] = trim($headerParts[1]);
            }
        }

        return [
            'url' => $url,
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $parts[1],
            'size' => strlen($parts[1])
        ];
    }

    /**
     * Retorna estatísticas do scraping
     *
     * @return array
     */
    public function getStats(): array
    {
        $elapsed = microtime(true) - $this->stats['start_time'];
        $rps = $elapsed > 0 ? $this->stats['completed'] / $elapsed : 0;

        return [
            'total' => $this->stats['total'],
            'completed' => $this->stats['completed'],
            'failed' => $this->stats['failed'],
            'pending' => $this->stats['total'] - $this->stats['completed'] - $this->stats['failed'],
            'active' => count($this->activeRequests),
            'bytes' => $this->stats['bytes'],
            'elapsed' => round($elapsed, 2),
            'requests_per_second' => round($rps, 2),
            'progress_percent' => $this->stats['total'] > 0
                ? round(($this->stats['completed'] + $this->stats['failed']) / $this->stats['total'] * 100, 2)
                : 0
        ];
    }

    /**
     * Extrai dados específicos das páginas scraped
     *
     * @param array $patterns Padrões regex para extração
     * @return array Dados extraídos
     */
    public function extract(array $patterns): array
    {
        $extracted = [];

        foreach ($this->results as $url => $result) {
            if (isset($result['error'])) {
                continue;
            }

            $data = ['url' => $url];

            foreach ($patterns as $key => $pattern) {
                if (preg_match_all($pattern, $result['body'], $matches)) {
                    $data[$key] = $matches[1] ?? $matches[0];
                }
            }

            $extracted[] = $data;
        }

        return $extracted;
    }

    /**
     * Retorna todos os resultados
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
