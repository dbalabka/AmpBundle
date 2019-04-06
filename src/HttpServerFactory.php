<?php
/*
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle;

use Amp\Http\Server\Options;
use Amp\Http\Server\Server;
use Amp\Socket\Socket;
use Amp\Http\Server\RequestHandler as HttpRequestHandler;
use Psr\Log\LoggerInterface;

class HttpServerFactory
{
    public static function createServer(
        array $socketAddresses,
        HttpRequestHandler $handler,
        LoggerInterface $logger,
        Options $options
    ): Server
    {
        $sockets = \array_map('Amp\Socket\listen', $socketAddresses);
        return new Server($sockets, $handler, $logger, $options);
    }
}
