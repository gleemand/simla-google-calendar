<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Config;
use App\Api\SimlaApi;
use App\Api\GoogleApi;
use Psr\Log\LoggerInterface;

class SyncCommand extends Command
{
    protected static $defaultName = 'app:sync';

    private $logger;

    private $simlaApi;

    private $config;

    private $googleApi;

    private $orderStatus;

    public function __construct(LoggerInterface $logger, Config $config, SimlaApi $simlaApi, GoogleApi $googleApi)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->simlaApi = $simlaApi;
        $this->googleApi = $googleApi;

        $this->orderStatus = $this->config->get('simla_order_status_code');

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Execute sync action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->simlaApi->isCustomFieldExist()) {
            $this->logger->error('Create custom field with code \'google_calendar_id\' for CRM orders first');
            return Command::FAILURE;
        }

        if (empty($history = $this->simlaApi->getHistory())) {
            $this->logger->info('History is up to date!');
            return Command::SUCCESS;
        }

        foreach ($history as $change) {
            $historyId = $change->id;
            $toUpdate = false;

            if ($change->order->status !== $this->orderStatus) {
                $this->logger->warning('#' . $change->order->id . ' Not in tracked status. Skipping...');
                continue;
            }

            if (($order = $this->simlaApi->getOrder($change)) === false) {
                continue;
            }

            if (($change->field === 'first_name'
                || $change->field === 'last_name'
                || $change->field === 'email'
                || $change->field === 'customer_comment'
                || $change->field === 'manager_comment'
                ) && isset($order->customFields['google_calendar_id'])
            ) {
                $toUpdate = true;
            }

            if ($toUpdate === false && isset($order->customFields['google_calendar_id'])) {
                $this->logger->warning('#' . $change->order->id . ' Nothing to update. Skipping...');
                continue;
            }

            if ($toUpdate === false && !isset($order->customFields['google_calendar_id'])) {
                $attachments = [];

                if (!empty($files = $this->simlaApi->getFileList($order->id))) {
                    foreach ($files as $file) {
                        if (($downloadedFile = $this->simlaApi->downloadFile($file->id)) === false) {
                            continue;
                        }

                        file_put_contents('./temp/' . $downloadedFile->fileName, $downloadedFile->data->getContents());

                        $result = $this->googleApi->uploadFile($downloadedFile, $order);

                        $attachments[] = [
                            'fileUrl' => 'https://drive.google.com/open?id=' . $result->id,
                            'title' => $result->name,
                            'mimeType' => $result->mimeType,
                        ];
                    }
                }

                $event = $this->googleApi->createEvent($order, $attachments);

                if ($this->simlaApi->putEventIdToOrder($order, $event->id) === false) {
                    continue;
                }
            } else {
                $this->googleApi->updateEvent($order);
            }
        }

        $this->config->set('simla_history_id', $historyId);

        $this->logger->info("History index updated to $historyId");

        return Command::SUCCESS;
    }
}
