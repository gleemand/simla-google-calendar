<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;

class MainAction
{
    private $view;

    private $googleApi;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        GoogleApi $googleApi
    )
    {
        $this->view = $view;
        $this->googleApi = $googleApi;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        session_start();

        if (isset($_SESSION['userId']) && $_SESSION['userId']) {

            return $response->withHeader('Location', 'config')->withStatus(301);
        } else {
            session_write_close();

            $authUrl = $this->googleApi->generateAuthUrl();

            return $this->view->render($response, 'login.twig', ['authUrl' => $authUrl]);
        }
    }
}
