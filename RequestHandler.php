<?php
/*
 * This file is part of the OpCart software.
 *
 * (c) 2017, OpticsPlanet, Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Aerys\AerysBundle;

use Aerys;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

class RequestHandler
{
    /**
     * kernel
     *
     * @var Kernel
     */
    protected $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function __invoke(Aerys\Request $req, Aerys\Response $resp)
    {
        $connectionInfo = $req->getConnectionInfo();
        $headers = $req->getAllHeaders();
        $server = [
            'SERVER_NAME' => $connectionInfo['server_addr'],
            'SERVER_PORT' => $connectionInfo['server_port'],
            'REMOTE_ADDR' => $connectionInfo['client_addr'],
            'SCRIPT_NAME' => '/app_dev.php',
            'SCRIPT_FILENAME' => __DIR__ . '/app_dev.php',
            'HTTPS' => $connectionInfo['is_encrypted'] ? 'on' : 'off',
            'SERVER_PROTOCOL' => 'HTTP/' . $req->getProtocolVersion(),
            'PATH_INFO' => preg_replace('#^/app_dev.php/#', '/', $req->getUri()),
            'QUERY_STRING'
        ];
        foreach ($headers as $name => $header) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $header[0];
        }
        if (isset($server['HTTP_HOST'])) {
            $server['HTTP_HOST'] = parse_url($server['HTTP_HOST'])['host'];
        }
        $params = $req->getAllParams();
        foreach ($params as $name => $value) {
            $params[$name] = $value[0];
        }
        $request = Request::create($req->getUri(), $req->getMethod(), $params, [], [], $server, null/*$req->getBody()*/);
        $response = yield from $this->kernel->handle($request);

        $headers = $response->headers->all();
        foreach ($headers as $name => $value) {
            $resp->setHeader($name, $value[0]);
        }
        $resp->setStatus($response->getStatusCode());
        $this->kernel->terminate($request, $response);
        $resp->end($response->getContent());
    }
}
