<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop;

use RuntimeException;
use Throwable;

/**
 * Web Scraper Paralelo Ultra-Otimizado
 *
 * Suporta TODOS os métodos HTTP:
 * - GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS
 * - Custom headers
 * - Request body (JSON, form-data, raw)
 * - stream_select() para I/O não-bloqueante
 */
class ParallelWebScraper
{
    /** ID do timer de progresso */
    protected ?int $progressTimerId = null;
    /** Loop de eventos responsável por gerenciar as operações assíncronas */
    private FiberEventLoop $loop;
    /** Número máximo de conexões simultâneas */
    private int $maxConcurrent;
    /** Timeout máximo por conexão (em segundos) */
    private int $timeout;
    /**
     * Estatísticas de execução
     * @var array<string, int|float>
     */
    private array $stats = [
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
        'bytes' => 0,
        'start_time' => 0,
    ];
    /**
     * Conexões atualmente ativas
     * @var array<int, array<string, mixed>>
     */
    private array $connections = [];
    /**
     * Fila de requisições pendentes
     * @var array<int, array<string, mixed>>
     */
    private array $queue = [];
    /**
     * Resultados finais das requisições
     * @var array<string, array<string, mixed>>
     */
    private array $results = [];
    /** Contador interno de ID das conexões */
    private int $connectionId = 0;

    /**
     * Construtor
     *
     * @param FiberEventLoop $loop Sistema de loop de eventos
     * @param int $maxConcurrent Número máximo de conexões simultâneas
     * @param int $timeout Tempo máximo de espera por requisição (segundos)
     */
    public function __construct(FiberEventLoop $loop, int $maxConcurrent = 100, int $timeout = 30)
    {
        $this->loop = $loop;
        $this->maxConcurrent = $maxConcurrent;
        $this->timeout = $timeout;
    }

    /**
     * Método GET (conveniência)
     *
     * @param array<int, string> $urls
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function get(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        return $this->scrape($urls, $onComplete, $onProgress);
    }

    /**
     * Scrape múltiplas URLs em paralelo (apenas GET)
     *
     * @param array<int, string> $urls Lista simples de URLs
     * @param callable|null $onComplete Executado a cada requisição concluída
     * @param callable|null $onProgress Executado periodicamente para progresso
     * @return array<string, array<string, mixed>>
     */
    public function scrape(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        // Converte URLs simples em requests
        $requests = array_map(fn($url) => ['url' => $url, 'method' => 'GET'], $urls);
        return $this->scrapeRequests($requests, $onComplete, $onProgress);
    }

    /**
     * Executa múltiplas requisições HTTP em paralelo (qualquer método)
     *
     * @param array<int, array<string, mixed>> $requests Array de requisições HTTP
     * @param callable|null $onComplete Função chamada ao concluir cada requisição
     * @param callable|null $onProgress Função chamada para exibir progresso
     *
     * @return array<string, array<string, mixed>> Resultado de todas as requisições
     */
    public function scrapeRequests(array $requests, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $this->initStats(count($requests));
        $this->queue = array_values($requests);
        $this->results = [];
        $this->connections = [];

        // Timer principal
        $mainTimerId = $this->loop->repeat(0.001, function () use ($onComplete) {
            $this->tick($onComplete);
        });

        // Timer de progresso
        if ($onProgress) {
            $this->progressTimerId = $this->loop->repeat(0.5, function () use ($onProgress) {
                $onProgress($this->getStats());
            });
        }

        $this->loop->run();

        return $this->results;
    }

    /**
     * Inicializa as estatísticas
     *
     * @param int $total Total de requisições
     * @return void
     */
    private function initStats(int $total): void
    {
        $this->stats = [
            'total' => $total,
            'completed' => 0,
            'failed' => 0,
            'bytes' => 0,
            'start_time' => microtime(true),
        ];
    }

    /**
     * Inicializa as estatísticas
     *
     * @param int $total Total de requisições
     * @return void
     */
    private function tick(?callable $onComplete): void
    {
        while (count($this->connections) < $this->maxConcurrent && !empty($this->queue)) {
            $request = array_shift($this->queue);
            $this->createConnection($request);

            if (count($this->connections) % 10 === 0) {
                usleep(100);
            }
        }

        if (!empty($this->connections)) {
            $this->processConnections($onComplete);
        }

        if (empty($this->queue) && empty($this->connections)) {
            $this->loop->stop();
        }
    }

    /**
     * Cria conexão HTTP
     *
     * @param array<string, mixed> $request
     * @return void
     */
    private function createConnection(array $request): void
    {
        try {
            $url = $request['url'];
            $method = strtoupper($request['method'] ?? 'GET');
            $body = $request['body'] ?? '';
            $customHeaders = $request['headers'] ?? [];

            $parts = parse_url($url);

            if (!isset($parts['host'])) {
                throw new RuntimeException("Invalid URL");
            }

            $host = $parts['host'];
            $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            $protocol = $parts['scheme'] === 'https' ? 'ssl://' : 'tcp://';

            // Contexto SSL
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);

            $socket = @stream_socket_client(
                "$protocol$host:$port",
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                $context
            );

            if (!$socket) {
                $errorMsg = !empty($errstr) ? $errstr : "Connection refused or timeout";
                throw new RuntimeException("Connection failed: $errorMsg (errno: $errno)");
            }

            stream_set_blocking($socket, false);
            stream_set_timeout($socket, $this->timeout);

            $id = $this->connectionId++;

            // Monta requisição HTTP completa
            $httpRequest = $this->buildHttpRequest($method, $path, $host, $body, $customHeaders);

            $this->connections[$id] = [
                'socket' => $socket,
                'url' => $url,
                'method' => $method,
                'host' => $host,
                'state' => 'writing',
                'request' => $httpRequest,
                'request_pos' => 0,
                'response' => '',
                'start_time' => microtime(true),
            ];

        } catch (Throwable $e) {
            $this->markFailed($request['url'], $e->getMessage());
        }
    }

    /**
     * Monta a requisição HTTP bruta
     *
     * @param string $method Método HTTP (GET, POST, etc)
     * @param string $path Caminho + query
     * @param string $host Host do domínio
     * @param string $body Corpo da requisição
     * @param array<string, string> $customHeaders Headers customizados
     *
     * @return string Requisição HTTP crua
     */
    private function buildHttpRequest(string $method, string $path, string $host, string $body, array $customHeaders): string
    {
        $lines = [];

        // Request line
        $lines[] = "$method $path HTTP/1.1";

        // Headers obrigatórios
        $lines[] = "Host: $host";
        $lines[] = "User-Agent: ParallelWebScraper/2.1";
        $lines[] = "Accept: */*";

        // Se tem body, adiciona Content-Length
        if (!empty($body)) {
            $lines[] = "Content-Length: " . strlen($body);

            // Se não tem Content-Type definido, usa application/x-www-form-urlencoded
            if (!isset($customHeaders['Content-Type']) && !isset($customHeaders['content-type'])) {
                $lines[] = "Content-Type: application/x-www-form-urlencoded";
            }
        }

        // Headers customizados
        foreach ($customHeaders as $name => $value) {
            $lines[] = "$name: $value";
        }

        $lines[] = "Connection: close";
        $lines[] = "";

        // Body (se houver)
        if (!empty($body)) {
            $lines[] = $body;
        } else {
            $lines[] = "";
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param string $url
     * @param string $error
     * @return void
     */
    private function markFailed(string $url, string $error): void
    {
        $this->results[$url] = [
            'error' => $error,
            'url' => $url,
            'status' => 0
        ];
        $this->stats['failed']++;
    }

    /**
     * Processa conexões em leitura e escrita usando stream_select
     *
     * @param callable|null $onComplete Callback ao finalizar
     *
     * @return void
     */
    private function processConnections(?callable $onComplete): void
    {
        if (empty($this->connections)) {
            return;
        }

        $read = [];
        $write = [];
        $except = null;
        $now = microtime(true);

        foreach ($this->connections as $id => $conn) {
            if ($now - $conn['start_time'] > $this->timeout) {
                $this->closeConnection($id, "Timeout", $onComplete);
                continue;
            }

            if ($conn['state'] === 'writing') {
                $write[] = $conn['socket'];
            } elseif ($conn['state'] === 'reading') {
                $read[] = $conn['socket'];
            }
        }

        if (empty($read) && empty($write)) {
            return;
        }

        $result = @stream_select($read, $write, $except, 0, 5000);

        if ($result === false || $result === 0) {
            return;
        }

        foreach ($write as $socket) {
            $id = $this->findConnectionBySocket($socket);
            if ($id !== null) {
                $this->writeToSocket($id, $onComplete);
            }
        }

        foreach ($read as $socket) {
            $id = $this->findConnectionBySocket($socket);
            if ($id !== null) {
                $this->readFromSocket($id, $onComplete);
            }
        }
    }

    /**
     * Fecha conexão, trata resposta ou erro
     *
     * @param int $id ID interno da conexão
     * @param string|null $error Mensagem de erro, se houver
     * @param callable|null $onComplete Callback ao finalizar
     *
     * @return void
     */
    private function closeConnection(int $id, ?string $error, ?callable $onComplete): void
    {
        if (!isset($this->connections[$id])) {
            return;
        }

        $conn = $this->connections[$id];

        if (is_resource($conn['socket'])) {
            fclose($conn['socket']);
        }

        if ($error !== null) {
            $this->markFailed($conn['url'], $error);
        } else {
            try {
                $result = $this->parseHttpResponse($conn['response'], $conn['url'], $conn['method']);
                $this->markSuccess($conn['url'], $result, $onComplete);
            } catch (Throwable $e) {
                $this->markFailed($conn['url'], $e->getMessage());
            }
        }

        unset($this->connections[$id]);
    }

    /**
     * Parse da resposta HTTP
     *
     * @param string $response
     * @param string $url
     * @param string $method
     * @return array
     */
    private function parseHttpResponse(string $response, string $url, string $method): array
    {
        if (empty($response)) {
            throw new RuntimeException("Empty response");
        }

        $parts = explode("\r\n\r\n", $response, 2);

        if (count($parts) < 2) {
            throw new RuntimeException("Invalid HTTP response");
        }

        [$headerBlock, $body] = $parts;
        $headerLines = explode("\r\n", $headerBlock);
        $statusLine = array_shift($headerLines);

        if (!preg_match('/HTTP\/[\d.]+\s+(\d+)/', $statusLine, $matches)) {
            throw new RuntimeException("Invalid status line");
        }

        $statusCode = (int)$matches[1];

        $headers = [];
        foreach ($headerLines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return [
            'url' => $url,
            'method' => $method,
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
            'size' => strlen($body)
        ];
    }

    /**
     * Marca URL como sucesso
     *
     * @param string $url
     * @param array $result
     * @param callable|null $onComplete
     * @return void
     */
    private function markSuccess(string $url, array $result, ?callable $onComplete): void
    {
        $this->results[$url] = $result;
        $this->stats['completed']++;
        $this->stats['bytes'] += strlen($result['body'] ?? '');

        if ($onComplete) {
            $onComplete($url, $result);
        }
    }

    /**
     * Encontra ID da conexão pelo socket
     *
     * @param $socket
     * @return int|null
     */
    private function findConnectionBySocket($socket): ?int
    {
        foreach ($this->connections as $id => $conn) {
            if ($conn['socket'] === $socket) {
                return $id;
            }
        }
        return null;
    }

    /**
     * Escreve requisição no socket
     *
     * @param int $id
     * @param callable|null $onComplete
     * @return void
     */
    private function writeToSocket(int $id, ?callable $onComplete): void
    {
        if (!isset($this->connections[$id])) {
            return;
        }

        $conn = &$this->connections[$id];
        $remaining = substr($conn['request'], $conn['request_pos']);

        $written = @fwrite($conn['socket'], $remaining);

        if ($written === false) {
            $this->closeConnection($id, "Write failed", $onComplete);
            return;
        }

        $conn['request_pos'] += $written;

        if ($conn['request_pos'] >= strlen($conn['request'])) {
            $conn['state'] = 'reading';
        }
    }

    /**
     * Lê resposta do socket
     *
     * @param int $id
     * @param callable|null $onComplete
     * @return void
     */
    private function readFromSocket(int $id, ?callable $onComplete): void
    {
        if (!isset($this->connections[$id])) {
            return;
        }

        $conn = &$this->connections[$id];

        $chunk = @fread($conn['socket'], 8192);

        if ($chunk === false) {
            $this->closeConnection($id, "Read failed", $onComplete);
            return;
        }

        if ($chunk === '') {
            if (feof($conn['socket'])) {
                $this->closeConnection($id, null, $onComplete);
            }
            return;
        }

        $conn['response'] .= $chunk;
    }

    /**
     * Retorna estatísticas de execução em tempo real
     *
     * @return array{
     *   total:int,
     *   completed:int,
     *   failed:int,
     *   pending:int,
     *   active:int,
     *   bytes:int,
     *   mb:float,
     *   elapsed:float,
     *   requests_per_second:float,
     *   progress_percent:float
     * }
     */
    public function getStats(): array
    {
        $elapsed = microtime(true) - $this->stats['start_time'];
        $rps = $elapsed > 0 ? $this->stats['completed'] / $elapsed : 0;
        $progress = $this->stats['total'] > 0
            ? ($this->stats['completed'] + $this->stats['failed']) / $this->stats['total'] * 100
            : 0;

        return [
            'total' => $this->stats['total'],
            'completed' => $this->stats['completed'],
            'failed' => $this->stats['failed'],
            'pending' => $this->stats['total'] - $this->stats['completed'] - $this->stats['failed'],
            'active' => count($this->connections),
            'bytes' => $this->stats['bytes'],
            'mb' => round($this->stats['bytes'] / 1024 / 1024, 2),
            'elapsed' => round($elapsed, 2),
            'requests_per_second' => round($rps, 2),
            'progress_percent' => round($progress, 2)
        ];
    }

    /**
     * Método POST
     *
     * @param array<int, array<string, mixed>> $requests
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function post(array $requests, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $formatted = array_map(static fn($r) => array_merge($r, ['method' => 'POST']), $requests);
        return $this->scrapeRequests($formatted, $onComplete, $onProgress);
    }

    /**
     * Método PUT
     *
     * @param array<int, array<string, mixed>> $requests
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function put(array $requests, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $formatted = array_map(static fn($r) => array_merge($r, ['method' => 'PUT']), $requests);
        return $this->scrapeRequests($formatted, $onComplete, $onProgress);
    }

    /**
     * Método DELETE
     *
     * @param array<string, string> $urls
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function delete(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $requests = array_map(static fn($url) => ['url' => $url, 'method' => 'DELETE'], $urls);
        return $this->scrapeRequests($requests, $onComplete, $onProgress);
    }

    /**
     * Método PATCH
     *
     * @param array<int, array<string, mixed>> $requests
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function patch(array $requests, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $formatted = array_map(static fn($r) => array_merge($r, ['method' => 'PATCH']), $requests);
        return $this->scrapeRequests($formatted, $onComplete, $onProgress);
    }

    /**
     * Método HEAD
     *
     * @param array<string, string> $urls
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function head(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $requests = array_map(static fn($url) => ['url' => $url, 'method' => 'HEAD'], $urls);
        return $this->scrapeRequests($requests, $onComplete, $onProgress);
    }

    /**
     * Método OPTIONS
     *
     * @param array<string, string> $urls
     * @param callable|null $onComplete
     * @param callable|null $onProgress
     * @return array<string, array<string, mixed>>
     */
    public function options(array $urls, ?callable $onComplete = null, ?callable $onProgress = null): array
    {
        $requests = array_map(static fn($url) => ['url' => $url, 'method' => 'OPTIONS'], $urls);
        return $this->scrapeRequests($requests, $onComplete, $onProgress);
    }

    /**
     * Extrai dados do body das respostas usando regex
     *
     * @param array<string, string> $patterns Chave => Regex
     *
     * @return array<int, array<string, mixed>>
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
     * @return array<string, array<string, mixed>>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Filtra resultados por status HTTP
     *
     * @param int $status
     *
     * @return array<string, array<string, mixed>>
     */
    public function getResultsByStatus(int $status): array
    {
        return array_filter($this->results, static fn($r) => ($r['status'] ?? 0) === $status);
    }

    /**
     * Retorna apenas URLs com erro
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFailedUrls(): array
    {
        return array_filter($this->results, static fn($r) => isset($r['error']));
    }

    /**
     * Retorna apenas URLs bem sucedidas (2xx e 3xx)
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSuccessfulUrls(): array
    {
        return array_filter($this->results, static fn($r) => !isset($r['error']) && ($r['status'] ?? 0) >= 200 && ($r['status'] ?? 0) < 400);
    }
}