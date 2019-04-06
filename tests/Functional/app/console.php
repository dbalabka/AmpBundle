<?php
declare(strict_types=1);
/*
 * Copyright (c) 2019, Dmitrijs Balabka
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


use Amp\AmpBundle\Tests\Functional\App\Application;
use Amp\AmpBundle\Tests\Functional\App\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Amp\AmpBundle\Tests\Functional\WebTestCase;

set_time_limit(0);

require __DIR__.'/../../../vendor/autoload.php';

if (!isset($_SERVER['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
    }
    $envFile = file_exists(__DIR__.'/../.env') ? __DIR__.'/../.env' : __DIR__.'/../.env.dist';
    (new Dotenv())->load($envFile);
}
$input = new ArgvInput();
$env = $input->getParameterOption(['--env', '-e'], $_SERVER['APP_ENV'] ?? 'dev', true);
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? ('prod' !== $env)) && !$input->hasParameterOption('--no-debug', true);
if ($debug) {
    umask(0000);
    if (class_exists(Debug::class)) {
        Debug::enable();
    }
}

$kernel = WebTestCase::createKernelForApplication();
$application = new Application($kernel);
$application->run($input);
