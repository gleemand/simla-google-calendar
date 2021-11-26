<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Config;
use App\HistoryReset;
use App\Api\SimlaApi;
use Psr\Log\LoggerInterface;

class HistoryResetCommand extends Command
{
    protected static $defaultName = 'app:reset';

    private $logger;

    private $simlaApi;

    private $config;

    private $history;

    public function __construct(LoggerInterface $logger, Config $config, HistoryReset $history)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->history = $history;

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

            $this->history->reset($userId);

            sleep(1);
        }

        return Command::SUCCESS;
    }
}
