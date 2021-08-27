<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Config;
use App\Api\SimlaApi;
use Psr\Log\LoggerInterface;

class HistoryResetCommand extends Command
{
    protected static $defaultName = 'app:reset';

    private $logger;

    private $simlaApi;

    private $config;

    public function __construct(LoggerInterface $logger, Config $config, SimlaApi $simlaApi)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->simlaApi = $simlaApi;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Resets history index');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($history = $this->simlaApi->getHistory(true))) {
            $output->writeln('History is empty!');

            return Command::FAILURE;
        }

        $historyId = end($history)->id;

        $storedHistoryId = $this->config->get('simla_history_id');

        if ($historyId != $storedHistoryId) {
            $this->config->set('simla_history_id', $historyId);
            $output->writeln('History index updated to ' . $historyId);
        } else {
            $output->writeln('History index is up to date. Nothing to reset.');
        }

        return Command::SUCCESS;
    }
}
