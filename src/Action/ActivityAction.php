<?php

namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Config;
use App\HistoryReset;

class ActivityAction
{
    private $logger;

    private $config;

    private $history;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        HistoryReset $history
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->history = $history;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $post = (array) $request->getParsedBody();
        $activity = json_decode($post['activity'], true);

        $userId = $post['clientId'];
        $active = (int) ($activity['active'] && !$activity['freeze']);

        $this->config->set($userId, 'active', $active);
        $this->logger->info($userId . ': active = ' . $active);

        if ($active) {
            $this->history->reset($userId);
        }

        return $response->withStatus(200);
    }
}
