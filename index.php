<?php

use Swoole\http\Response;
use Swoole\http\Request;
use Swoole\http\Server;

use \FastRoute\simpleDispatcher;
use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

use \GuzzleHttp\Client;

require 'vendor/autoload.php';

error_reporting(E_ALL & ~E_NOTICE);

$client = new \Swoole\Client();

function handleRequest(Dispatcher $dispatcher, string $request_method, string $request_uri) {
    @list($code, $handler, $vars) = $dispatcher->dispatch($request_method, $request_uri);
    switch ($code) {
        case Dispatcher::NOT_FOUND:
            $result = [
                'status' => 404,
                'message' => 'Not Found',
                'errors' => [
                    sprintf('The URI "%s" was not found', $request_uri)
                ]
            ];
            break;
        case Dispatcher::METHOD_NOT_ALLOWED:
            $result = [
                'status' => 405,
                'message' => 'Method Not Allowed',
                'errors' => [
                    sprintf('Method "%s" is not allowed', $request_method)
                ]
            ];
            break;
        case Dispatcher::FOUND:
            try {
                $result = call_user_func($handler, $vars);
            }catch (Error $e){
                return [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                    'errors' => [
                        $e->getMessage()
                    ]
                ];
            }
            break;
    }
    return $result;
}

$cachedDispatcher = \FastRoute\cachedDispatcher(function (RouteCollector $r) use ($client) {

}, [
    'cacheFile' => __DIR__ . '/route.cache',
    'cacheDisabled' => true,
]);

$dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) use ($client) {
    $r->addRoute('GET', '/blog/posts/last', function () use ($client) {
        $response = $client->request('GET', 'https://m.vk.com/viewbox', [
            'headers' => [
                'user-agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Mobile Safari/537.36'
            ]
        ]);


        preg_match("/<div class=\"pi_text\">(.+?)<\/div>/",$response->getBody(), $arr);
        preg_match("/background-image: url\((.+?)\);\"/",$response->getBody(), $arr2);

        return [
            "text"  => $arr[1],
            "image" => $arr2[1]
        ];
    });
});


$server = new Server("127.0.0.1", 1337, SWOOLE_BASE);

$server->set([
    'workers' => 4
]);

$server->on('start', function (Server $server)  {
    echo 'Swoole http server is started.' . PHP_EOL;
});

$server->on('request', function (Request $request, Response $response) use ($dispatcher){
    $request_method = $request->server['request_method'];
    $request_uri = $request->server['request_uri'];

    $_SERVER['REQUEST_URI'] = $request_uri;
    $_SERVER['REQUEST_METHOD'] = $request_method;
    $_SERVER['REMOTE_ADDR'] = $request->server['remote_addr'];
    $_GET = $request->get ?? [];
    $_FILES = $request->files ?? [];

    if ($request_method === 'POST' && $request->header['content-type'] === 'application/json') {
        $body = $request->rawContent();
        $_POST = empty($body) ? [] : json_decode($body);
    } else {
        $_POST = $request->post ?? [];
    }

    $response->header('Content-Type', 'application/json');
    $result = handleRequest($dispatcher, $request_method, $request_uri);

    $response->end(json_encode($result));
});


$server->start();