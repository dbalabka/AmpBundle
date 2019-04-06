<?php

namespace Amp\AmpBundle;

use Aerys;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Amp\File;
use Amp\ByteStream;
use Aerys\BodyParser;
use function Aerys\parseBody;
use Aerys\ParsedBody;
use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Amp\Uri\Uri;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;
use Symfony\Component\HttpKernel\TerminableInterface;
use Amp\Http\Server\RequestHandler as HttpRequestHandler;
use Amp\Http\Server\Request as AmpRequest;
use Amp\Http\Server\Response as AmpResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RequestHandler implements HttpRequestHandler
{
    /**
     * kernel
     *
     * @var Kernel
     */
    protected $kernel;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function __invoke(Aerys\Request $req, Aerys\Response $resp)
    {
//        $connectionInfo = $req->getConnectionInfo();
//        $headers = $req->getAllHeaders();
//        $server = [
//            'SERVER_NAME' => $connectionInfo['server_addr'],
//            'SERVER_PORT' => $connectionInfo['server_port'],
//            'REMOTE_ADDR' => $connectionInfo['client_addr'],
//            'SCRIPT_NAME' => '/app_dev.php',
//            'SCRIPT_FILENAME' => __DIR__ . '/app_dev.php',
//            'HTTPS' => $connectionInfo['is_encrypted'] ? 'on' : 'off',
//            'SERVER_PROTOCOL' => 'HTTP/' . $req->getProtocolVersion(),
//            'PATH_INFO' => preg_replace('#^/app_dev.php/#', '/', $req->getUri()),
//            'QUERY_STRING'
//        ];
//        foreach ($headers as $name => $header) {
//            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $header[0];
//        }
//        if (isset($server['HTTP_HOST'])) {
//            $server['HTTP_HOST'] = parse_url($server['HTTP_HOST'])['host'];
//        }
//        $params = $req->getAllParams();
//        foreach ($params as $name => $value) {
//            $params[$name] = $value[0];
//        }
//        $body = yield $req->getBody();
//        $request = Request::create($req->getUri(), $req->getMethod(), $params, [], [], $server, null/*$req->getBody()*/);

        $request = yield from $this->mapRequest($req);

        $response = yield from $this->kernel->handle($request);

        $this->mapResponse($resp, $response);

//        $headers = $response->headers->all();
//        foreach ($headers as $name => $value) {
//            $resp->setHeader($name, $value[0]);
//        }
//        $resp->setStatus($response->getStatusCode());
        $this->kernel->terminate($request, $response);
        $resp->end($response->getContent());
    }

    /** @inheritdoc */
    public function onRequest(Request $request, Response $response): Promise
    {
        if (null === $this->application) {
            return new Success;
        }

        return call(function () use ($request, $response) {
            $syRequest = yield from $this->mapRequest($request);

            // start buffering the output, so cgi is not sending any http headers
            // this is necessary because it would break session handling since
            // headers_sent() returns true if any unbuffered output reaches cgi stdout.
            ob_start();

            try {
                if ($this->bootstrap instanceof HooksInterface) {
                    $this->bootstrap->preHandle($this->application);
                }

                $syResponse = $this->application->handle($syRequest);
            } catch (\Exception $exception) {
                $response->setStatus(500); // internal server error
                $response->end();

                // end buffering if we need to throw
                @ob_end_clean();

                throw $exception;
            }

            // should not receive output from application->handle()
            @ob_end_clean();

            $this->mapResponse($response, $syResponse);

            if ($this->application instanceof TerminableInterface) {
                $this->application->terminate($syRequest, $syResponse);
            }

            if ($this->bootstrap instanceof HooksInterface) {
                $this->bootstrap->postHandle($this->application);
            }

            // Delete all files that have not been moved.
            foreach ($request->getLocalVar("php-pm.files") as $file) {
                @\unlink($file["tmp_name"]);
            }
        });
    }

    /**
     * Implementation taken from https://github.com/kelunik/php-pm-httpkernel/blob/amp/Bridges/HttpKernel.php
     */
    protected function mapRequest(AmpRequest $aerysRequest): \Generator
    {
        $method = $aerysRequest->getMethod();
        $headers = $aerysRequest->getHeaders();
        $query = $aerysRequest->getUri()->getQuery();

        $_COOKIE = [];

        $sessionCookieSet = false;

        foreach ($headers['cookie'] ?? [] as $cookieHeader) {
            $headersCookie = explode(';', $cookieHeader);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $_COOKIE[$name] = $value;

                if ($name === session_name()) {
                    session_id($value);
                    $sessionCookieSet = true;
                }
            }
        }

        if (!$sessionCookieSet && session_id()) {
            // session id already set from the last round but not got from the cookie header,
            // so generate a new one, since php is not doing it automatically with session_start() if session
            // has already been started.
            throw new \Exception('Not implemented');
            session_id(Utils::generateSessionId());
        }

        $contentType = $aerysRequest->getHeader('content-type') ?? '';
//        $content = yield $aerysRequest->getBody();
        $content = $aerysRequest->getBody();
        $files = [];
        $post = [];

        $isSimpleForm = !strncmp($contentType, "application/x-www-form-urlencoded", \strlen("application/x-www-form-urlencoded"));
        $isMultipart = preg_match('#^\s*multipart/(?:form-data|mixed)(?:\s*;\s*boundary\s*=\s*("?)([^"]*)\1)?$#', $contentType);

        if ($isMultipart) {
            /** @var ParsedBody $parsedBody */
            $parsedBody = yield parseBody($aerysRequest);
            $parsedData = $parsedBody->getAll();
            $parsedMeta = $parsedData["metadata"];
            $parsedFields = $parsedData["fields"];

            foreach ($parsedMeta as $key => $fileMetas) {
                $fileMeta = $fileMetas[0]; // TODO: Support for arrays of files with the same name
                $file = \tempnam(\sys_get_temp_dir(), "aerys-user-upload-");

                yield File\put($file, $parsedFields[$key][0]);

                $files[$key] = [
                    'name' => $fileMeta['filename'],
                    'type' => $fileMeta['mime'],
                    'size' => strlen($parsedFields[$key][0]),
                    'tmp_name' => $file,
                    'error' => UPLOAD_ERR_OK,
                ];

                unset($parsedFields[$key]);
            }

            // Re-parse, because Aerys doesn't build array for foo[bar]=baz
            \parse_str(\implode("&", \array_map(function (string $field, array $values) {
                $parts = [];

                foreach ($values as $value) {
                    $parts[] = \rawurlencode($field) . "=" . \rawurlencode($value);
                }

                return implode("&", $parts);
            }, array_keys($parsedFields), $parsedFields)), $post);
        } elseif ($isSimpleForm) {
            \parse_str($content, $post);
        }

//        if ($this->bootstrap instanceof RequestClassProviderInterface) {
//            $class = $this->bootstrap->requestClass();
//        } else {
//            $class = SymfonyRequest::class;
//        }

        $server = $this->getServerVar($aerysRequest);

        \parse_str($query, $queryParams);

        /** @var SymfonyRequest $syRequest */
        $syRequest = new SymfonyRequest($queryParams, $post, $attributes = [], $_COOKIE, $files, $server, $content);
        $syRequest->setMethod($method);

        if ($files) {
            throw new \Exception('Not implemented');
        }
//        $aerysRequest->setLocalVar("php-pm.files", $files);

        return $syRequest;
    }

    private function getServerVar(AmpRequest $aerysRequest)
    {
        $headers = $aerysRequest->getHeaders();
//        $connectionInfo = $aerysRequest->getConnectionInfo();
        $client = $aerysRequest->getClient();
        $server = [
//            'SERVER_NAME' => $connectionInfo['server_addr'],
            'SERVER_NAME' => $client->getLocalAddress(),
//            'SERVER_PORT' => $connectionInfo['server_port'],
            'SERVER_PORT' => $client->getLocalPort(),
//            'REMOTE_ADDR' => $connectionInfo['client_addr'],
            'REMOTE_ADDR' => $client->getRemoteAddress(),
            'SCRIPT_NAME' => '/app_dev.php',
            'SCRIPT_FILENAME' => __DIR__ . '/app_dev.php',
//            'HTTPS' => $connectionInfo['is_encrypted'] ? 'on' : 'off',
            'HTTPS' => $client->isEncrypted() ? 'on' : 'off',
            'SERVER_PROTOCOL' => 'HTTP/' . $aerysRequest->getProtocolVersion(),
            'PATH_INFO' => preg_replace('#^/app_dev.php/#', '/', $aerysRequest->getUri()),
            'QUERY_STRING'
        ];
        foreach ($headers as $name => $header) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $header[0];
        }
        if (isset($server['HTTP_HOST'])) {
            $server['HTTP_HOST'] = parse_url($server['HTTP_HOST'])['host'];
        }
        return $server;
    }

    /**
     * Implementation taken from https://github.com/kelunik/php-pm-httpkernel/blob/amp/Bridges/HttpKernel.php
     */
    protected function mapResponse(AmpResponse $aerysResponse, SymfonyResponse $syResponse)
    {
        // end active session
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
            session_unset(); // reset $_SESSION
        }

        $nativeHeaders = [];

        foreach (headers_list() as $header) {
            if (false !== $pos = strpos($header, ':')) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));

                $nativeHeaders[$name][] = $value;
            }
        }

        // after reading all headers we need to reset it, so next request
        // operates on a clean header.
//        header_remove();

        $headers = array_merge(
            \array_change_key_case($nativeHeaders, \CASE_LOWER),
            \array_change_key_case($syResponse->headers->allPreserveCase(), \CASE_LOWER)
        );

        $cookies = [];

        /** @var Cookie $cookie */
        foreach ($syResponse->headers->getCookies() as $cookie) {
            $cookieHeader = sprintf('%s=%s', $cookie->getName(), $cookie->getValue());

            if ($cookie->getPath()) {
                $cookieHeader .= '; Path=' . $cookie->getPath();
            }
            if ($cookie->getDomain()) {
                $cookieHeader .= '; Domain=' . $cookie->getDomain();
            }

            if ($cookie->getExpiresTime()) {
                $cookieHeader .= '; Expires=' . gmdate('D, d-M-Y H:i:s', $cookie->getExpiresTime()). ' GMT';
            }

            if ($cookie->isSecure()) {
                $cookieHeader .= '; Secure';
            }
            if ($cookie->isHttpOnly()) {
                $cookieHeader .= '; HttpOnly';
            }

            $cookies[] = $cookieHeader;
        }

        if (isset($headers['set-cookie'])) {
            $headers['set-cookie'] = array_merge((array) $headers['set-cookie'], $cookies);
        } else {
            $headers['set-cookie'] = $cookies;
        }

        if ($syResponse instanceof SymfonyStreamedResponse) {
            throw new \Exception('Not implemented');
            $aerysResponse->setStatus($syResponse->getStatusCode());

            foreach ($headers as $key => $values) {
                foreach ($values as $i => $value) {
                    if ($i === 0) {
                        $aerysResponse->setHeader($key, $value);
                    } else {
                        $aerysResponse->addHeader($key, $value);
                    }
                }
            }

            // asynchronously get content
            ob_start(function($buffer) use ($aerysResponse) {
                $aerysResponse->write($buffer);
                return '';
            }, 4096);

            $syResponse->sendContent();

            // flush remaining content
            @ob_end_flush();
            $aerysResponse->end();
        } else {
            ob_start();
            $content = $syResponse->getContent();
            @ob_end_flush();

            $aerysResponse->setStatus($syResponse->getStatusCode());

            foreach ($headers as $key => $values) {
                foreach ($values as $i => $value) {
                    if ($i === 0) {
                        $aerysResponse->setHeader($key, $value);
                    } else {
                        $aerysResponse->addHeader($key, $value);
                    }
                }
            }

            $aerysResponse->setBody($content);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handleRequest(AmpRequest $ampRequest): Promise
    {
        return call(function () use ($ampRequest) {
            $ampRequest = yield from $this->mapRequest($ampRequest);

            /** @var SymfonyResponse $symfonyResponse */
            $symfonyResponse = yield from $this->kernel->handle($ampRequest);

            $ampResponse = new AmpResponse();
            $this->mapResponse($ampResponse, $symfonyResponse);

//        $headers = $response->headers->all();
//        foreach ($headers as $name => $value) {
//            $resp->setHeader($name, $value[0]);
//        }
//        $resp->setStatus($response->getStatusCode());
            $this->kernel->terminate($ampRequest, $symfonyResponse);
            $ampResponse->setBody($symfonyResponse->getContent());
            return $ampResponse;
        });
    }
}
