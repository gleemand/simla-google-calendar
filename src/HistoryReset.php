<?php

namespace App;

use App\Config;
use App\Api\SimlaApi;
use Psr\Log\LoggerInterface;

class HistoryReset
{
    private $logger;

    private $simlaApi;

    private $config;

    private $userId;

    public function __construct(LoggerInterface $logger, Config $config, $userId = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->userId = $userId;
    }

    public function reset()
    {
        if ($this->userId == '') {

            return false;
        }

        $this->logger->info($this->userId . ': HystoryReset starting...');

        $this->simlaApi = new SimlaApi($this->logger, $this->config, $this->userId);

        if (!$this->simlaApi->checkApi()) {

            return false;
        }

        if (empty($history = $this->simlaApi->getHistory())) {
            $this->logger->info($this->userId . ': History index is up to date. Nothing to reset.');

            return true;
        }

        $historyId = end($history)->id;

        $storedHistoryId = $this->config->get($this->userId, 'simla_history_id');

        if ($historyId != $storedHistoryId) {
            $this->config->set($this->userId, 'simla_history_id', $historyId);

            $this->logger->info($this->userId . ': History index updated to ' . $historyId);
        }

        return true;
    }
}
