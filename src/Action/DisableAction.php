<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;

class DisableAction
{
    private $logger;

    private $config;

    private $view;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        session_start();

        if (isset($_SESSION['userId'])) {
            $this->config->set($_SESSION['userId'], 'google_token', '');
            unset($_SESSION['userId']);
        }

        session_write_close();

        $this->logger->info('Disabled account');

        return $response->withHeader('Location', 'main')->withStatus(301);
    }
}
