<?php
/*
 * (c) 2014, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Amp\AmpBundle\Tests\Functional;

use Amp\AmpBundle\Command\ServerRunCommand;
use Amp\AmpBundle\Tests\Functional\DefaultLoopCallbackTestDecorator;
use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Delayed;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Loop;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Amp\Promise;

class ServerRunCommandTest extends WebTestCase
{
    /** @var Application */
    private $application;

    protected function setUp()
    {
        static::bootKernel(['test_case' => 'default']);
        $this->application = $application = new Application(static::$kernel);
        // required for old version of Symfony
        $application->all();
    }

    public function testContainerSource()
    {
        $command = $this->application->find('amp:server:run');
        $this->assertInstanceOf(ServerRunCommand::class, $command);
    }

    /**
     * TODO: review possiblity to use https://github.com/amphp/phpunit-util to avoid using real process execution
     * Example: https://gist.github.com/cspray/88ce3d550a0e5ac74c5cafbc5b85df95
     */
    public function testRun()
    {
        $command = $this->application->find('amp:server:run');
        /** @var Server $server */
        $server = $this->application->getKernel()->getContainer()->get('amp.http_server');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        DefaultLoopCallbackTestDecorator::setPromiseHandler(function () use ($commandTester, $server) {
//            $output = $commandTester->getDisplay();
            $promise = (new DefaultClient())->request("http://127.0.0.1:8001/", [
                Client::OP_TRANSFER_TIMEOUT => 4000,
            ]);

            /** @var Response $response */
            $response = yield $promise;
            $this->assertSame(Status::OK, $response->getStatus());
            $this->assertJson('["OK"]', $response->getBody());

            // Ensure client already connected and sent request
            yield new Delayed(1000);
            yield $server->stop();
            Loop::stop();
        });

        $commandTester->execute(array(
            'command'  => $command->getName(),
        ));
    }
}
