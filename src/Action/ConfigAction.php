<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;

class ConfigAction
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
        $settings = [];
        $errors = $request->getQueryParams()['errors'];
        $success = $request->getQueryParams()['success'];
        session_start();

        if (isset($_SESSION['userId'])) {
            $userId = $_SESSION['userId'];
            session_write_close();

            $settings['simla_api_url'] = $this->config->get($userId, 'simla_api_url');
            $settings['simla_api_key'] = $this->config->get($userId, 'simla_api_key');
            $settings['simla_order_status_code'] = $this->config->get($userId, 'simla_order_status_code');
            $settings['google_calendar_id'] = $this->config->get($userId, 'google_calendar_id');
            $settings['last_sync'] = $this->config->get($userId, 'last_sync');

            return $this->view->render($response, 'config.twig', [
                'settings' => $settings,
                'userId' => $userId,
                'errors' => $errors,
                'success' => $success,
            ]);
        } else {
            session_write_close();

            return $response->withHeader('Location', 'main')->withStatus(301);
        }


    }
}
