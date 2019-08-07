<?php

use Swoole\http\Response;
use Swoole\http\Request;
use Swoole\http\Server;

use \FastRoute\simpleDispatcher;
use \FastRoute\Dispatcher;

function handleRequest(Dispatcher $dispatcher, string $request_method, string $request_uri) {

    list($code, $handler, $vars) = $dispatcher->dispatch($request_method, $request_uri);
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
            $allowedMethods = $handler;
            $result = [
                'status' => 405,
                'message' => 'Method Not Allowed',
                'errors' => [
                    sprintf('Method "%s" is not allowed', $request_method)
                ]
            ];
            break;
        case Dispatcher::FOUND:
            $result = call_user_func($handler, $vars);
            break;
    }
    return $result;
}
$dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r){
    $r->addRoute('GET', '/lastGroupPost', function (){
        return "чебурнет";
    });
});


$server = new Server("127.0.0.1", 1337, SWOOLE_BASE);

$server->set([
    'workers' => 4
]);

$server->on('start', function (Server $server)  {
    echo sprintf('Swoole http server is started');
});

$server->on('request', function (Request $request, Response $response){
    print_r($request);
    $response->end('k');
});

$server->start();