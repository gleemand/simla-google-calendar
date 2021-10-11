<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use Slim\Views\Twig;

class SaveAction
{
    private $logger;

    private $config;

    private $view;

    private $googleApi;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        GoogleApi $googleApi
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
        $this->googleApi = $googleApi;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $errors = [];
        $success = 'false';
        $settings = (array)$request->getParsedBody();

        session_start();
        $userId = $_SESSION['userId'];
        session_write_close();

        if (count($settings) > 0) {
            if (preg_match("/https:\/\/(.*).(retailcrm.(pro|ru|es)|simla.com)/", $settings['simla_api_url'])) {
                $this->config->set($userId, 'simla_api_url', $settings['simla_api_url']);
            } else {
                $errors[] = 'URL is not correct';
                unset($settings['simla_api_url']);
            }

            if (preg_match('/^[a-zA-Z0-9]+/', $settings['simla_api_key'])) {
                $this->config->set($userId, 'simla_api_key', $settings['simla_api_key']);
            } else {
                $errors[] = 'API key is not correct';
                unset($settings['simla_api_key']);
            }

            if (preg_match('/^[a-zA-Z0-9]+/', $settings['simla_order_status_code'])) {
                $this->config->set($userId, 'simla_order_status_code', $settings['simla_order_status_code']);
            } else {
                $errors[] = 'Status code is not correct';
                unset($settings['simla_order_status_code']);
            }

            if (preg_match('/^[a-zA-Z0-9]+/', $settings['simla_order_status_code'])) {
                $this->config->set($userId, 'google_calendar_id', $settings['google_calendar_id']);
            } else {
                $errors[] = 'Calendar ID is not correct';
                unset($settings['google_calendar_id']);
            }

            if (count($errors) < 1) {
                $success = 'true';
            }
        }

        return $this->view->render($response, 'config.twig', [
            'errors' => $errors,
            'success' => $success,
            'settings' => $settings,
            'userId' => $userId,
        ]);
    }
}
