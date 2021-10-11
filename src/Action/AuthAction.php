<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

class AuthAction
{
    private $logger;

    private $config;

    private $view;

    private $googleApi;

    private $routeParser;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        GoogleApi $googleApi,
        RouteParserInterface $routeParser
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
        $this->routeParser = $routeParser;
        $this->googleApi = $googleApi;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $code = $request->getQueryParams()['code'];

        $this->googleApi->authClient($code);

        $userId = $this->googleApi->userId;

        if ($userId) {
            session_start();
            $_SESSION['userId'] = $userId;
            session_write_close();

            $path = 'config';
        } else {
            $path = 'main';
        }

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
