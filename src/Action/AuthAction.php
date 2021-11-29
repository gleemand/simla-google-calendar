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

    private $router;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        GoogleApi $googleApi,
        RouteParserInterface $router
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
        $this->googleApi = $googleApi;
        $this->router = $router;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $code = $request->getQueryParams()['code'];

        $this->googleApi->authClient($code);

        $userId = $this->googleApi->userId;

        if ($userId !== md5(0) && $userId !== md5('')) {
            session_start();
            $_SESSION['userId'] = $userId;
            session_write_close();

            $route = [
                'name' => 'config',
                'alert' => false,
                'message' => false,
            ];
        } else {
            $route = [
                'name' => 'home',
                'alert' => 'red',
                'message' => 'You are not logged in',
            ];
        }

        $path = $this->router->urlFor($route['name'], [], ['alert' => $route['alert'], 'message' => $route['message']]);

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
