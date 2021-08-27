<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;

class AuthAction
{
    private $logger;

    private $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $code = $request->getQueryParams()['code'];

        $client = new GoogleApi($this->logger, $this->config, $code);

        $response->getBody()->write('<p align=center>' . $client->message . '</p>');

        return $response;
    }
}
