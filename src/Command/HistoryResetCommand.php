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

            $history = new HistoryReset($userId);
            $history->reset();

            sleep(1);
        }

        return Command::SUCCESS;
    }
}
