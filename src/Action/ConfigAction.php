<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

class ConfigAction
{
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
        $this->config = $config;
        $this->view = $view;
        $this->router = $router;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $settings = [];
        $errors = $request->getQueryParams()['errors'] ?? null;
        $success = $request->getQueryParams()['success'] ?? null;
        session_start();

        $post = (array)$request->getParsedBody();

        if (isset($post['clientId'])) {
            $_SESSION['userId'] = $post['clientId'];
        }

        if (isset($_SESSION['userId']) && $_SESSION['userId'] !== md5(0) && $_SESSION['userId'] !== md5('')) {
            $userId = $_SESSION['userId'];
            session_write_close();

            $settings['simla_api_url'] = $this->config->get($userId, 'simla_api_url');
            $settings['simla_api_key'] = $this->config->get($userId, 'simla_api_key');
            $settings['simla_order_status_code'] = $this->config->get($userId, 'simla_order_status_code');
            $settings['google_calendar_id'] = $this->config->get($userId, 'google_calendar_id');
            $settings['create_meet'] = $this->config->get($userId, 'create_meet');
            $settings['last_sync'] = $this->config->get($userId, 'last_sync');

            return $this->view->render($response, 'config.twig', [
                'settings' => $settings,
                'userId' => $userId,
                'email' => $this->config->get($userId, 'email'),
                'errors' => $errors,
                'success' => $success,
            ]);
        }

        session_write_close();

        $route = [
            'name' => 'home',
            'alert' => 'red',
            'message' => 'You are not logged in',
        ];

        $path = $this->router->urlFor($route['name'], [], ['alert' => $route['alert'], 'message' => $route['message']]);

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
