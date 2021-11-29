<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;

class HomeAction
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
        if (isset($request->getQueryParams()['alert']) && isset($request->getQueryParams()['message'])) {
            $route['alert'] = $request->getQueryParams()['alert'];
            $route['message'] = $request->getQueryParams()['message'];
        }

        session_start();

        if (isset($_SESSION['userId']) && $_SESSION['userId'] !== md5(0) && $_SESSION['userId'] !== md5('')) {

            return $response->withHeader('Location', 'config')->withStatus(301);
        } else {
            session_write_close();

            $authUrl = $this->googleApi->generateAuthUrl();

            return $this->view->render($response, 'login.twig', [
                'authUrl' => $authUrl,
                'alert' => $route['alert'],
                'message' => $route['message'],
            ]);
        }
    }
}
