<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

class DeleteAction
{
    private $logger;

    private $config;

    private $view;

    private $router;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        RouteParserInterface $router
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
        $this->router = $router;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        session_start();

        if (isset($_SESSION['userId'])) {
            $this->config->delete($_SESSION['userId']);
            unset($_SESSION['userId']);
        }

        session_write_close();

        $this->logger->info('Deleted account');

        $route = [
            'name' => 'home',
            'alert' => 'green',
            'message' => 'Account deleted',
        ];

        $path = $this->router->urlFor($route['name'], [], ['alert' => $route['alert'], 'message' => $route['message']]);

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
