<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Api\SimlaApi;
use App\Config;
use App\HistoryReset;
use Slim\Views\Twig;
use Slim\Interfaces\RouteParserInterface;

class SaveAction
{
    private $logger;

    private $config;

    private $router;

    private $history;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Twig $view,
        RouteParserInterface $router,
        HistoryReset $history
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->view = $view;
        $this->router = $router;
        $this->history = $history;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $errors = [];
        $success = 'false';
        $settings = (array)$request->getParsedBody();

        session_start();
        $userId = $_SESSION['userId'];
        session_write_close();

        if (isset($_SESSION['userId']) && $_SESSION['userId'] !== md5(0) && $_SESSION['userId'] !== md5('')) {
            if (
                htmlspecialchars($settings['simla_api_url'])
                && preg_match("/https:\/\/(.*).(retailcrm.(pro|ru|es)|simla.com|ecomlogic.com)/",
                htmlspecialchars($settings['simla_api_url']))
            ) {
                $this->config->set($userId, 'simla_api_url', htmlspecialchars($settings['simla_api_url']));
            } else {
                $errors[] = 'URL is not correct';
            }

            if (htmlspecialchars($settings['simla_api_key'])) {
                $this->config->set($userId, 'simla_api_key', htmlspecialchars($settings['simla_api_key']));
            } else {
                $errors[] = 'API key is empty';
            }

            if (htmlspecialchars($settings['simla_order_status_code'])) {
                $this->config->set($userId, 'simla_order_status_code', htmlspecialchars($settings['simla_order_status_code']));
            } else {
                $errors[] = 'Status code is empty';
            }

            if (htmlspecialchars($settings['google_calendar_id'])) {
                $this->config->set($userId, 'google_calendar_id', htmlspecialchars($settings['google_calendar_id']));
            } else {
                $errors[] = 'Calendar ID is empty';
            }

            $this->config->set($userId, 'time_zone', htmlspecialchars($settings['time_zone']));
            $this->config->set($userId, 'create_meet', htmlspecialchars($settings['create_meet'] ?? 0));

            if (count($errors) === 0) {

                if (!$this->config->get($userId, 'simla_connected')) {
                    $simlaApi = new SimlaApi($this->logger, $this->config, $userId);

                    if ($simlaApi->connectModule()) {
                        $simlaApi->createCustomFields();
                        $this->history->reset($userId);

                        $this->config->set($userId, 'simla_connected', 1);
                        $this->config->set($userId, 'active', 1);

                        $success = 'true';
                    } else {
                        $errors[] = 'Error while connecting to CRM';
                    }
                }
            }
        }

        if ($success) {
            $path = htmlspecialchars($settings['simla_api_url']) . 'admin/integration/list/';
        } else {
            $path = $this->router->urlFor('config', [], ['success' => $success, 'errors' => $errors]);
        }

        return $response->withHeader('Location', $path)->withStatus(301);
    }
}
