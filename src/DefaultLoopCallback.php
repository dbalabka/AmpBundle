<?php
/**
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle;

use Amp\Http\Server\Server;
use Amp\Loop;

class DefaultLoopCallback implements LoopCallback
{
    /** @var Server */
    private $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function __invoke()
    {
        yield $this->server->start();

        // Stop the server gracefully when SIGINT is received.
        // This is technically optional, but it is best to call Server::stop().
        Loop::onSignal(\SIGINT, function (string $watcherId) {
            Loop::cancel($watcherId);
            yield $this->server->stop();
        });
    }
}
