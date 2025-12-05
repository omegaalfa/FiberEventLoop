<?php


use Omegaalfa\FiberEventLoop\FiberEventLoop;

require 'vendor/autoload.php';

$loop = new FiberEventLoop();
$clients = [];

$server = stream_socket_server('tcp://0.0.0.0:9999');
stream_set_blocking($server, false);

echo "💬 Chat Server rodando em tcp://0.0.0.0:9999\n";

// Aceita novas conexões
$loop->listen($server, function ($client) use ($loop, &$clients) {
    $id = (int)$client;
    $clients[$id] = $client;

    $name = stream_socket_get_name($client, true);
    echo "✅ Cliente conectado: $name\n";

    // Broadcast de entrada
    $joinMsg = "[$name entrou no chat]\n";
    foreach ($clients as $c) {
        if ($c !== $client) {
            fwrite($c, $joinMsg);
        }
    }

    // Lê mensagens do cliente
    $loop->onReadable($client, function ($data) use ($client, $id, $name, &$clients, $loop) {

        if ($data === '' || $data === false) {
            fclose($client);
            unset($clients[$id]);

            echo "❌ Cliente desconectou: $name\n";

            // Broadcast de saída
            $leaveMsg = "[$name saiu do chat]\n";
            foreach ($clients as $c) {
                fwrite($c, $leaveMsg);
            }
            return;
        }

        echo "📨 $name: $data";

        // Broadcast para todos os outros clientes
        $message = "$name: $data";
        foreach ($clients as $c) {
            if ($c !== $client) {
                $loop->onWritable($c, $message, fn() => null);
            }
        }
    });
});

$loop->run();
