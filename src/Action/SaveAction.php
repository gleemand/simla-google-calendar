<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\GoogleApi;
use App\Config;
use App\HistoryReset;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

class SaveAction
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
        $errors = [];
        $success = 'false';
        $settings = (array)$request->getParsedBody();

        session_start();
        $userId = $_SESSION['userId'];
        session_write_close();

        if ($userId && count($settings)) {
            if (
                htmlspecialchars($settings['simla_api_url'])
                && preg_match("/https:\/\/(.*).(retailcrm.(pro|ru|es)|simla.com)/",
                htmlspecialchars($settings['simla_api_url']))
            ) {
                $this->config->set($userId, 'simla_api_url', htmlspecialchars($settings['simla_api_url']));
            } else {
                $errors[] = 'URL is not correct';
            }

            if (
                htmlspecialchars($settings['simla_api_key'])
                && preg_match('/^[a-zA-Z0-9]+/', htmlspecialchars($settings['simla_api_key']))
            ) {
                $this->config->set($userId, 'simla_api_key', htmlspecialchars($settings['simla_api_key']));
            } else {
                $errors[] = 'API key is not correct';
            }

            if (
                htmlspecialchars($settings['simla_order_status_code'])
                && preg_match('/^[a-zA-Z0-9]+/', htmlspecialchars($settings['simla_order_status_code']))
            ) {
                $this->config->set($userId, 'simla_order_status_code', htmlspecialchars($settings['simla_order_status_code']));
            } else {
                $errors[] = 'Status code is not correct';
            }

            if (htmlspecialchars($settings['simla_order_status_code'])) {
                $this->config->set($userId, 'google_calendar_id', htmlspecialchars($settings['google_calendar_id']));
            } else {
                $errors[] = 'Calendar ID is not correct';
            }

            if (count($errors) == 0) {
                $history = new HistoryReset($this->logger, $this->config, $userId);
                $history->reset();

                $success = 'true';
            }
        }

        $path = $this->router->urlFor('config', [], ['success' => $success, 'errors' => $errors ]);

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
