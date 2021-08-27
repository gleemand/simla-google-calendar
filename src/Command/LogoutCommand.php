<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Config;
use Psr\Log\LoggerInterface;

class LogoutCommand extends Command
{
    protected static $defaultName = 'app:logout';

    private $logger;

    private $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Unset Google token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config->set('google_token', '');

        $this->logger->info('Logged out');

        return Command::SUCCESS;
    }
}
