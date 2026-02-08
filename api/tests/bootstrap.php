<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

$stopServer = function () {
    exec('APP_ENV=test_ap_server symfony local:server:stop');
    echo "Server Stopped!\n";
};

register_shutdown_function($stopServer);

if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(\SIGINT, function () use ($stopServer) {
        $stopServer();
        exit(130);
    });
    pcntl_signal(\SIGTERM, function () use ($stopServer) {
        $stopServer();
        exit(143);
    });
}

exec('APP_ENV=test_ap_server symfony local:server:start --allow-http --allow-all-ip --no-tls --daemon');

$url = 'http://localhost:8000/docs';
$client = HttpClient::create();

try {
    $response = $client->request('GET', $url, [
        'timeout' => 5,
    ]);

    if (200 !== $response->getStatusCode()) {
        throw new RuntimeException(sprintf('Server returned HTTP %d instead of 200', $response->getStatusCode()));
    }

    if (!($content = $response->getContent())) {
        throw new RuntimeException('Server response is empty');
    }

    $data = json_decode($content, true);
    if (!isset($data['info']['title'])) {
        throw new RuntimeException('Server response is malformed');
    }

    echo $data['info']['title'] . " Server Started\n";
} catch (Exception $e) {
    throw new RuntimeException('Embedded server is not responding: ' . $e->getMessage(), 0, $e);
}
