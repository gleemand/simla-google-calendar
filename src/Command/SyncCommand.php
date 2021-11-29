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

    private $config;

    private $simlaApi;

    private $googleApi;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Execute sync action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('=========================');

        foreach ($this->config->config as $userId => $config) {
            if ($userId == 'main') {
                continue;
            }

            if (!$this->config->get($userId, 'simla_api_url')) {
                continue;
            }

            if (!$this->config->get($userId, 'active')) {
                continue;
            }

            usleep(100000);

            $this->logger->info('-------------------------');
            $this->logger->info($userId . ': Start to sync');

            $this->simlaApi = new SimlaApi($this->logger, $this->config, $userId);
            $this->googleApi = new GoogleApi($this->logger, $this->config, $userId);

            $this->orderStatus = $this->config->get($userId, 'simla_order_status_code');

            if (!$this->simlaApi->checkApi()) {
                continue;
            }

            $history = $this->simlaApi->getHistory();

            if (empty($history)) {
                $this->logger->info($userId . ': History is up to date!');

                continue;
            }

            foreach ($history as $change) {
                $historyId = $change->id;
                $toUpdate = false;

                if ($change->order->status !== $this->orderStatus) {
                    continue;
                }

                if (($order = $this->simlaApi->getOrder($change)) === false) {
                    continue;
                }

                usleep(100000);

                if ((
                    $change->field === 'first_name'
                    || $change->field === 'last_name'
                    || $change->field === 'email'
                    || $change->field === 'customer_comment'
                    || $change->field === 'manager_comment'
                    || $change->field === 'custom_event_date'
                    || $change->field === 'custom_event_time_start'
                    || $change->field === 'custom_event_time_end'
                    ) && isset($order->customFields['event_id'])
                ) {
                    $toUpdate = true;
                }

                if ($toUpdate === false && isset($order->customFields['event_id'])) {
                    continue;
                }

                $manager = $this->simlaApi->getManager($order->managerId);

                if ($toUpdate === false && !isset($order->customFields['event_id'])) {
                    $attachments = [];

                    if (!empty($files = $this->simlaApi->getFileList($order->id))) {
                        foreach ($files as $file) {
                            if (($downloadedFile = $this->simlaApi->downloadFile($file->id)) === false) {
                                continue;
                            }

                            if (!is_dir('temp')) {
                                mkdir('temp');
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

                    $event = $this->googleApi->createEvent($order, $manager, $attachments);

                    if (!$event) {
                        continue;
                    }

                    if ($this->simlaApi->putEventDataToOrder($order, $event) === false) {
                        continue;
                    }
                } else {
                    $this->googleApi->updateEvent($order, $manager);
                }
            }

            $this->config->set($userId, 'simla_history_id', $historyId);

            $date_msk = new \DateTime('now', new \DateTimeZone('Europe/Moscow'));
            $lastSync = $date_msk->format('Y-m-d H:i:s (e)');
            $this->config->set($userId, 'last_sync', $lastSync);

            $this->logger->info($userId . ": Done! History index updated to $historyId");
        }

        $this->logger->info('Done');

        return Command::SUCCESS;
    }
}
