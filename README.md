# AmpBundle

This bundle enable [Symfony](http://symfony.com/) to run inside [Aerys](https://amphp.org/aerys/) HTTP server's coroutines 
which in turn provides possibility to use all [Amp](https://amphp.org/) framework features like await of promises 
inside Controller actions.
It is possible to use AmpBundle with regular HTTP server like Apache and Nginx. The only difference that state will 
not be persisted between requests.

Example of waiting for promise inside controller's action:
```php
<?php
class AcmeController
{
    public function indexAction()
    {
        // Instantiate the HTTP client
        $client = new Amp\Artax\DefaultClient();

        $request = (new AmpRequest('http://httpbin.org/post', 'POST'))
            ->withBody('woot!')
            ->withHeaders([
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cookie' => ['Cookie1=val1', 'Cookie2=val2']
            ])
        ;

        // Make an asynchronous HTTP request
        $responsePromise = $client->request($request);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        $response = yield $responsePromise;

        return new JsonResponse($response);
    }
}
```

## Installation

1) Install bundle:
    ```bash
    composer require torinaki/amp-bundle "dev-master"
    ``` 
2) Enable the bundle 
    ```php
    <?php
    // app/AppKernel.php
    
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Amp\AmpBundle\AmpBundle(),
            // ...
        );
    }
    ```
3) Create two files inside `./web` folder
   1) Production server [server.php](./server.php.dist) (equivalent of app.php) example:
        ```php
        <?php
        
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require_once __DIR__.'/../app/autoload.php';
        
        $kernel = new AppKernel('prod', false);
        $kernel->loadClassCache();
        
        $requestHandler = new \Amp\AmpBundle\RequestHandler($kernel);
        
        $host = 'localhost';
        $port = 1234;
        ($hosts[] = new Aerys\Host)
            ->name($host)
            ->expose('*', $port)
            ->use($requestHandler);
        
        return $hosts;
        ```
   2) Development server [server_dev.php](./server_dev.php.dist) (equivalent of app_dev.php) example:
        ```php
        <?php
        
        use Symfony\Component\Debug\Debug;
        
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require_once __DIR__.'/../app/autoload.php';
        Debug::enable();
        
        $kernel = new AppKernel('dev', true);
        $kernel->loadClassCache();
        
        $requestHandler = new \Amp\AmpBundle\RequestHandler($kernel);
        
        $host = 'localhost';
        $port = 1234;
        ($hosts[] = new \Aerys\Host)
            ->name($host)
            ->expose('*', $port)
            ->use($requestHandler);
        
        return $hosts;
        ```
        
## Run Aerys server

```bash

```