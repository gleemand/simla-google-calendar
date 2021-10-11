<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Config;
use App\Api\SimlaApi;
use App\Api\GoogleApi;
use Psr\Log\LoggerInterface;

class HistoryResetCommand extends Command
{
    protected static $defaultName = 'app:reset';

    private $logger;

    private $simlaApi;

    private $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Resets history index');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->config->config as $userId => $config) {
            if ($userId == 'main' || $userId == '') {
                continue;
            }

            $output->writeln($userId . ': HystoryReset starting...');

            $this->simlaApi = new SimlaApi($this->logger, $this->config, $userId);
            $this->googleApi = new GoogleApi($this->logger, $this->config, $userId);

            if (!$this->simlaApi->checkApi()) {
                continue;
            }

            if (empty($history = $this->simlaApi->getHistory())) {
                $output->writeln($userId . ': History index is up to date. Nothing to reset.');

                return Command::FAILURE;
            }

            $historyId = end($history)->id;

            $storedHistoryId = $this->config->get($userId, 'simla_history_id');

            if ($historyId != $storedHistoryId) {
                $this->config->set($userId, 'simla_history_id', $historyId);

                $output->writeln($userId . ': History index updated to ' . $historyId);
            }

            sleep(1);
        }

        return Command::SUCCESS;
    }
}
