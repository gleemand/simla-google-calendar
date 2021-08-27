<?php

namespace App\Api;

use RetailCrm\Api\Enum\PaginationLimit;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Filter\Orders\OrderHistoryFilterV4Type;
use RetailCrm\Api\Model\Request\Orders\OrdersHistoryRequest;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;
use RetailCrm\Api\Model\Filter\Files\FileFilter;
use RetailCrm\Api\Model\Request\Files\FilesRequest;
use RetailCrm\Api\Enum\CustomFields\CustomFieldEntity;
use App\Config;
use Psr\Log\LoggerInterface;


class SimlaApi
{
    private $logger;

    private $config;

    private $apiUrl;

    private $apiKey;

    private $historyId;

    private $client;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->apiUrl = $this->config->get('simla_api_url');
        $this->apiKey = $this->config->get('simla_api_key');
        $this->historyId = $this->config->get('simla_history_id');

        $this->client = SimpleClientFactory::createClient($this->apiUrl, $this->apiKey);
    }

    public function getHistory($full = false)
    {
        $history = [];

        $apiRequest = new OrdersHistoryRequest();
        $apiRequest->filter = new OrderHistoryFilterV4Type();
        $apiRequest->limit = PaginationLimit::LIMIT_100;
        if (!$full) {
            $apiRequest->filter->sinceId = $this->historyId;
        }

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $apiResponse = $this->client->orders->history($apiRequest);
            } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
                $this->logger->error($exception->getMessage());
                continue;
            }

            $history = array_merge($history, $apiResponse->history);
            $apiRequest->filter->startDate = null;
            $apiRequest->filter->sinceId = end($apiResponse->history)->id;
        } while ($apiResponse->pagination->currentPage < $apiResponse->pagination->totalPageCount);

        return $history;
    }

    public function getOrder($change)
    {
        try {
            $apiResponse = $this->client->orders->get($change->order->id, new BySiteRequest(ByIdentifier::ID, $change->order->site));
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            return false;
        }

        return $apiResponse->order;
    }

    public function getFileList($orderId)
    {
        $apiRequest = new FilesRequest();
        $apiRequest->filter = new FileFilter();
        $apiRequest->filter->orderIds = [$orderId];

        try {
            $apiResponse = $this->client->files->list($apiRequest);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            return false;
        }

        return $apiResponse->files;
    }

    public function downloadFile($fileId)
    {
        try {
            $apiResponse = $this->client->files->download($fileId);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            return false;
        }

        return $apiResponse;
    }

    public function putEventIdToOrder($order, $eventId)
    {
        $apiRequest = new OrdersEditRequest();
        $apiRequest->order = new Order();
        $apiRequest->order->customFields['google_calendar_id'] = $eventId;
        $apiRequest->site = $order->site;
        $apiRequest->by = ByIdentifier::ID;

        try {
            $apiResponse = $this->client->orders->edit($order->id, $apiRequest);
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            return false;
        }

        return true;
    }

    public function isCustomFieldExist()
    {
        try {
            $apiResponse = $this->client->customFields->get(CustomFieldEntity::ORDER, 'google_calendar_id');
        } catch (ApiExceptionInterface | ClientExceptionInterface $exception) {
            $this->logger->error($exception->getMessage());
            
            if ($exception->getStatusCode() == 404) {
                return false;
            }
        }

        return true;
    }
}